<?php
// api/churn_risk.php
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');
ini_set('precision', '14');
ini_set('serialize_precision', '14');

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []): void { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []): void { json_error($m, $c, $extra); }

function risk_level_from_pct(float $pct): string {
    if ($pct < 25.0) return 'Low';
    if ($pct < 65.0) return 'Medium';
    return 'High';
}

function compute_rollups(PDO $pdo, int $uid, string $refDate): array {
    $stmt = $pdo->prepare("
        SELECT receipt_count rc, sales_volume sales, customer_traffic ct,
               morning_receipt_count mrc, swing_receipt_count src, graveyard_receipt_count grc,
               morning_sales_volume msv, swing_sales_volume ssv, graveyard_sales_volume gsv
        FROM churn_data
        WHERE user_id = :uid AND date < :d
        ORDER BY date DESC
        LIMIT 14
    ");
    $stmt->execute([':uid' => $uid, ':d' => $refDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $n = count($rows);
    if ($n === 0) {
        return [
            'avgRc' => 0.0, 'avgSales' => 0.0, 'avgCt' => 0.0,
            'avgMrc' => 0.0, 'avgSrc' => 0.0, 'avgGrc' => 0.0,
            'avgMsv' => 0.0, 'avgSsv' => 0.0, 'avgGsv' => 0.0,
            'days' => 0
        ];
    }

    $sum = [
        'rc' => 0, 'sales' => 0.0, 'ct' => 0,
        'mrc' => 0, 'src' => 0, 'grc' => 0,
        'msv' => 0.0, 'ssv' => 0.0, 'gsv' => 0.0
    ];
    foreach ($rows as $r) {
        $sum['rc'] += (int)($r['rc'] ?? 0);
        $sum['sales'] += (float)($r['sales'] ?? 0);
        $sum['ct'] += (int)($r['ct'] ?? 0);
        $sum['mrc'] += (int)($r['mrc'] ?? 0);
        $sum['src'] += (int)($r['src'] ?? 0);
        $sum['grc'] += (int)($r['grc'] ?? 0);
        $sum['msv'] += (float)($r['msv'] ?? 0);
        $sum['ssv'] += (float)($r['ssv'] ?? 0);
        $sum['gsv'] += (float)($r['gsv'] ?? 0);
    }

    return [
        'avgRc' => $sum['rc'] / $n,
        'avgSales' => $sum['sales'] / $n,
        'avgCt' => $sum['ct'] / $n,
        'avgMrc' => $sum['mrc'] / $n,
        'avgSrc' => $sum['src'] / $n,
        'avgGrc' => $sum['grc'] / $n,
        'avgMsv' => $sum['msv'] / $n,
        'avgSsv' => $sum['ssv'] / $n,
        'avgGsv' => $sum['gsv'] / $n,
        'days' => $n
    ];
}

function get_manila_date(): string {
    $tz = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $tz);
    return $now->format('Y-m-d');
}

/**
 * XGBoost model predictor class
 */
final class XGBPredictor {
    private array $trees = [];
    private float $base_score = 0.5;
    private string $objective = 'binary:logistic';
    private ?array $feature_names = null;

    public static function loadFrom(string $path): self {
        static $cache = [];
        if (isset($cache[$path])) return $cache[$path];

        if (!is_file($path)) {
            throw new RuntimeException("Model not found: {$path}");
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException("Empty or unreadable model file: {$path}");
        }

        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new RuntimeException("Invalid JSON structure in model file: {$path}");
        }

        $p = new self();

        // handle both modern and legacy XGBoost formats
        $learner = $json['learner'] ?? null;
        if ($learner && isset($learner['gradient_booster']['model']['trees'])) {
            $p->trees = $learner['gradient_booster']['model']['trees'];
            $p->base_score = (float)($learner['learner_model_param']['base_score'] ?? 0.5);
            $p->objective = (string)($learner['objective']['name'] ?? 'binary:logistic');
            $p->feature_names = $learner['feature_names'] ?? null;
        } elseif (isset($json['trees'])) {
            $p->trees = $json['trees'];
            $p->base_score = (float)($json['learner_model_param']['base_score'] ?? 0.5);
            $p->objective = (string)($json['objective']['name'] ?? 'binary:logistic');
            $p->feature_names = $json['feature_names'] ?? null;
        } else {
            throw new RuntimeException("No trees found in model JSON: {$path}");
        }

        return $cache[$path] = $p;
    }

    public function predict_proba(array $feat): float {
        $margin = log($this->base_score / (1.0 - $this->base_score));
        foreach ($this->trees as $tree) {
            $margin += $this->score_tree($tree, $feat);
        }
        return 1.0 / (1.0 + exp(-$margin));
    }

    private function score_tree(array $tree, array $feat): float {
        return $this->follow($tree, $feat);
    }

    private function follow(array $node, array $feat): float {
        if (isset($node['leaf'])) {
            return (float)$node['leaf'];
        }

        $split = $node['split'] ?? null;
        $th = (float)($node['split_condition'] ?? 0);
        $yes = $node['yes'] ?? null;
        $no = $node['no'] ?? null;

        $children = [];
        foreach ($node['children'] ?? [] as $c) {
            $children[$c['nodeid']] = $c;
        }

        $x = $this->get_feature($feat, $split);
        $next = ($x !== null && $x < $th) ? $yes : $no;

        return isset($children[$next])
            ? $this->follow($children[$next], $feat)
            : 0.0;
    }

    private function get_feature(array $feat, ?string $name): ?float {
        if (!$name) return null;
        if (array_key_exists($name, $feat)) return (float)$feat[$name];
        if (preg_match('/^f(\d+)$/', $name, $m)) {
            $i = (int)$m[1];
            return isset($feat['_vector'][$i]) ? (float)$feat['_vector'][$i] : null;
        }
        return null;
    }
}

// -------- Controller --------
$action = $_GET['action'] ?? 'latest';

try {
    if ($action === 'latest') {
        $stmt = $pdo->prepare("
            SELECT id, user_id, risk_score, risk_level, level, description, factors, risk_percentage, for_date, created_at
            FROM churn_predictions
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            j_ok(['has' => false]);
        } else {
            $factors = [];
            if (!empty($row['factors'])) {
                $decoded = json_decode($row['factors'], true);
                $factors = is_array($decoded) ? $decoded : [];
            }

            j_ok([
                'has' => true,
                'id' => (int)$row['id'],
                'for_date' => $row['for_date'],
                'risk_percentage' => (float)$row['risk_percentage'],
                'risk_score' => (float)$row['risk_score'],
                'risk_level' => $row['risk_level'] ?: $row['level'],
                'description' => $row['description'] ?? '',
                'factors' => $factors,
            ]);
        }
        exit;
    }

    if ($action === 'run') {
        $modelPath = __DIR__ . '/models/churn_xgb.json';
        if (!file_exists($modelPath)) {
            throw new RuntimeException("Model file missing: {$modelPath}");
        }

        $pred = XGBPredictor::loadFrom($modelPath);
        $prob = $pred->predict_proba(['rc' => 10, 'sales' => 200, 'ct' => 30, 'tdp' => 5, 'sdp' => 10, 't_drop' => 4, 'imbalance' => 15]);

        j_ok(['risk_probability' => $prob, 'risk_level' => risk_level_from_pct($prob * 100)]);
        exit;
    }

    j_err('Invalid action');
} catch (Throwable $e) {
    j_err('Error', 500, ['detail' => $e->getMessage()]);
}
