<?php
// api/churn_risk.php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

// set Manila timezone for DB
try { $pdo->exec("SET time_zone = '+08:00'"); } catch (Exception $e) { /* ignore */ }

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

/* -------------------------
   Utility helpers
   ------------------------- */

function get_manila_date(): string {
  $tz = new DateTimeZone('Asia/Manila');
  $now = new DateTime('now', $tz);
  return $now->format('Y-m-d');
}
function get_manila_datetime(): string {
  $tz = new DateTimeZone('Asia/Manila');
  $now = new DateTime('now', $tz);
  return $now->format('Y-m-d H:i:s');
}

/* ---------- factor analysis (kept mostly as-is) ---------- */
/* I assume your analyze_business_factors() and compute_rollups() functions
   already exist above or below. For brevity, I'll keep the version you had.
   If you need the exact copy, paste the version you already use. */

/* For this response I'll reuse the analyze_business_factors() & compute_rollups()
   from your code. If those functions are not present, copy them from your original file.
*/

/* ---------- XGBoost JSON predictor (robust) ---------- */
final class XGBPredictor {
  private array $trees = [];
  private float $base_score = 0.5;
  private string $objective = 'binary:logistic';
  private ?array $feature_names = null;

  public static function loadFrom(string $path): self {
    if (!is_file($path)) throw new RuntimeException("Model not found: {$path}");
    $raw = file_get_contents($path);
    if ($raw === false) throw new RuntimeException("Failed to read model: {$path}");
    $json = json_decode($raw, true);
    if (!is_array($json)) throw new RuntimeException("Invalid JSON model at {$path}");

    $p = new self();

    // Most XGBoost JSONs embed under learner
    $learner = $json['learner'] ?? null;
    if (is_array($learner)) {
      $p->objective = (string)($learner['objective']['name'] ?? ($json['objective']['name'] ?? 'binary:logistic'));
      $p->base_score = self::toFloat($learner['learner_model_param']['base_score'] ?? ($json['learner_model_param']['base_score'] ?? 0.5));
      $trees = $learner['gradient_booster']['model']['trees'] ?? ($json['trees'] ?? null);
      $p->feature_names = is_array($learner['feature_names'] ?? null) ? $learner['feature_names'] : (is_array($json['feature_names'] ?? null) ? $json['feature_names'] : null);
    } else {
      // older or different layout fallback
      $p->objective = (string)($json['objective']['name'] ?? 'binary:logistic');
      $p->base_score = self::toFloat($json['learner_model_param']['base_score'] ?? 0.5);
      $trees = $json['trees'] ?? null;
      $p->feature_names = is_array($json['feature_names'] ?? null) ? $json['feature_names'] : null;
    }

    if (!is_array($trees)) throw new RuntimeException("Trees[] missing in model JSON");
    $p->trees = $trees;
    return $p;
  }

  public function predict_proba(array $feat): float {
    if ($this->objective !== 'binary:logistic') {
      throw new RuntimeException("Unsupported objective: {$this->objective}");
    }

    // validate features if feature names provided (be lenient: only warn)
    if ($this->feature_names !== null) {
      foreach ($this->feature_names as $name) {
        if (preg_match('/^f\d+$/', (string)$name)) continue;
        if (!array_key_exists($name, $feat)) {
          // missing feature — allow but warn (we don't throw, to support fallback)
          // throw new RuntimeException("Feature '{$name}' required by model is missing.");
        }
      }
    }

    $margin = self::prob_to_logit($this->base_score);
    foreach ($this->trees as $treeIndex => $tree) {
      $contrib = $this->score_tree($tree, $feat);
      $margin += $contrib;
    }
    return self::sigmoid($margin);
  }

  /** Returns array with probability and per-tree contributions for debugging */
  public function explain_proba(array $feat): array {
    $margin = self::prob_to_logit($this->base_score);
    $perTree = [];
    foreach ($this->trees as $i => $tree) {
      $c = $this->score_tree($tree, $feat);
      $perTree[] = ['tree'=>$i, 'contribution'=>$c];
      $margin += $c;
    }
    return [
      'base_score' => $this->base_score,
      'base_logit' => self::prob_to_logit($this->base_score),
      'margin' => $margin,
      'probability' => self::sigmoid($margin),
      'per_tree' => $perTree
    ];
  }

  private function score_tree(array $tree, array $feat): float {
    // tree may be wrapped under 'nodes' or be a node itself
    if (isset($tree['nodes']) && is_array($tree['nodes'])) {
      // some JSONs represent a tree as {"nodes":[...], "tree_param": {...}}
      // we'll treat the root as the first nodes element with nodeid==0 but some JSONs put root at index 0
      // fallback: use $tree['nodes'][0] as the root
      return $this->follow_tree_nodes($tree['nodes'], 0, $feat);
    }
    // else treat $tree as a single node object
    return $this->follow($tree, $feat);
  }

  private function follow_tree_nodes(array $nodes, int $rootId, array $feat): float {
    // create map id->node
    $byId = [];
    foreach ($nodes as $n) {
      if (isset($n['nodeid'])) $byId[$n['nodeid']] = $n;
    }
    // if rootId missing, pick first element's nodeid
    if (!isset($byId[$rootId])) {
      $first = $nodes[0] ?? null;
      if ($first === null) return 0.0;
      $rootId = $first['nodeid'];
    }
    // walk
    $cur = $byId[$rootId];
    while (true) {
      if (array_key_exists('leaf', $cur)) return self::toFloat($cur['leaf']);
      $split = $cur['split'] ?? null;
      $th = self::toFloat($cur['split_condition'] ?? 0.0);
      $yes = $cur['yes'] ?? null;
      $no = $cur['no'] ?? null;
      $missing = $cur['missing'] ?? null;
      $defaultLeft = $cur['default_left'] ?? null;

      $x = $this->get_feature($feat, $split);
      if ($x === null) {
        $nextId = $missing ?? ($defaultLeft ? $yes : $no);
      } else {
        $nextId = ($x < $th) ? $yes : $no;
      }
      if (!isset($byId[$nextId])) {
        // fallback to first child or leaf
        $child = $cur['children'][0] ?? null;
        if ($child === null) return 0.0;
        $cur = $child;
      } else {
        $cur = $byId[$nextId];
      }
    }
  }

  private function follow(array $node, array $feat): float {
    if (array_key_exists('leaf', $node)) return self::toFloat($node['leaf']);

    $split = $node['split'] ?? null;
    $th = self::toFloat($node['split_condition'] ?? 0.0);
    $yes = $node['yes'] ?? null;
    $no = $node['no'] ?? null;
    $missing = $node['missing'] ?? null;
    $defaultLeft = $node['default_left'] ?? null;

    $children = $node['children'] ?? [];
    $byId = [];
    foreach ($children as $c) if (isset($c['nodeid'])) $byId[$c['nodeid']] = $c;

    $x = $this->get_feature($feat, $split);
    $nextId = null;
    if ($x === null) {
      $nextId = $missing ?? ($defaultLeft ? $yes : $no);
    } else {
      $nextId = ($x < $th) ? $yes : $no;
    }

    if (!isset($byId[$nextId])) {
      // fallback to first child
      $first = $children[0] ?? null;
      if ($first === null) return 0.0;
      return $this->follow($first, $feat);
    }
    return $this->follow($byId[$nextId], $feat);
  }

  private function get_feature(array $feat, ?string $name): ?float {
    if ($name === null) return null;
    if (array_key_exists($name, $feat)) {
      $v = $feat[$name];
      if ($v === null) return null;
      return self::toFloat($v);
    }
    // support f# vector notation if present
    if (preg_match('/^f(\d+)$/', (string)$name, $m)) {
      $idx = (int)$m[1];
      if (isset($feat['_vector']) && is_array($feat['_vector']) && array_key_exists($idx, $feat['_vector'])) {
        return self::toFloat($feat['_vector'][$idx]);
      }
      return null;
    }
    return null;
  }

  private static function prob_to_logit(float $p): float {
    $p = max(min($p, 1.0 - 1e-12), 1e-12);
    return log($p / (1.0 - $p));
  }

  private static function sigmoid(float $z): float {
    if ($z >= 0) {
      $ez = exp(-$z);
      return 1.0 / (1.0 + $ez);
    } else {
      $ez = exp($z);
      return $ez / (1.0 + $ez);
    }
  }

  private static function toFloat($v): float {
    if (is_string($v) || is_int($v)) return (float)$v;
    if (is_float($v)) return $v;
    return 0.0;
  }
}

/* -------------------------
   Controller
   ------------------------- */

$action = $_GET['action'] ?? 'latest';
$debug = !empty($_GET['debug']); // use ?debug=1 to get debug per-tree contributions

if ($action === 'latest') {
  try {
    $q = $pdo->prepare("
      SELECT id, user_id, risk_score, risk_level, level, description, factors, risk_percentage, for_date, created_at
      FROM churn_predictions
      WHERE user_id = ?
      ORDER BY created_at DESC
      LIMIT 1
    ");
    $q->execute([$uid]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      j_ok(['has'=>false]);
    } else {
      $factors = [];
      if (!empty($row['factors'])) {
        $decoded = json_decode($row['factors'], true);
        if (is_array($decoded)) $factors = $decoded;
      }
      j_ok([
        'has'             => true,
        'id'              => (int)$row['id'],
        'for_date'        => $row['for_date'],
        'risk_percentage' => (float)$row['risk_percentage'],
        'risk_score'      => (float)$row['risk_score'],
        'risk_level'      => (string)($row['risk_level'] ?: $row['level']),
        'level'           => (string)($row['risk_level'] ?: $row['level']),
        'description'     => (string)($row['description'] ?? ''),
        'factors'         => $factors,
      ]);
    }
  } catch (Throwable $e) {
    j_err('Load prediction failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'run') {
  try {
    $manilaDate = get_manila_date();
    $manilaDateTime = get_manila_datetime();

    // fetch today's churn_data
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? AND date = ? ORDER BY created_at DESC LIMIT 1");
    $q->execute([$uid, $manilaDate]);
    $cd = $q->fetch(PDO::FETCH_ASSOC);

    if (!$cd) {
      // create default row if missing (keeps previous behavior)
      $defaultInsert = $pdo->prepare("
        INSERT INTO churn_data
          (user_id, date, receipt_count, sales_volume, customer_traffic,
           morning_receipt_count, swing_receipt_count, graveyard_receipt_count,
           morning_sales_volume, swing_sales_volume, graveyard_sales_volume,
           previous_day_receipt_count, previous_day_sales_volume,
           weekly_average_receipts, weekly_average_sales,
           transaction_drop_percentage, sales_drop_percentage, created_at)
        VALUES (?, ?, 0, 0.00, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, ?)
        ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
      ");
      $defaultInsert->execute([$uid, $manilaDate, $manilaDateTime]);
      $q->execute([$uid, $manilaDate]);
      $cd = $q->fetch(PDO::FETCH_ASSOC);
    }

    // extract fields
    $rc    = max(0,   (int)($cd['receipt_count'] ?? 0));
    $sales = max(0.0, (float)($cd['sales_volume'] ?? 0));
    $ct    = max(0,   (int)($cd['customer_traffic'] ?? 0));

    $mrc = max(0, (int)($cd['morning_receipt_count'] ?? 0));
    $src = max(0, (int)($cd['swing_receipt_count'] ?? 0));
    $grc = max(0, (int)($cd['graveyard_receipt_count'] ?? 0));

    $msv = max(0.0, (float)($cd['morning_sales_volume'] ?? 0));
    $ssv = max(0.0, (float)($cd['swing_sales_volume'] ?? 0));
    $gsv = max(0.0, (float)($cd['graveyard_sales_volume'] ?? 0));

    $war = max(0.0, (float)($cd['weekly_average_receipts'] ?? 0));
    $was = max(0.0, (float)($cd['weekly_average_sales'] ?? 0));

    $tdp = max(0.0, (float)($cd['transaction_drop_percentage'] ?? 0));
    $sdp = max(0.0, (float)($cd['sales_drop_percentage'] ?? 0));

    // rollups
    function compute_rollups_local(PDO $pdo, int $uid, string $refDate): array {
      $stmt = $pdo->prepare("
        SELECT receipt_count rc, sales_volume sales, customer_traffic ct,
               morning_receipt_count mrc, swing_receipt_count src, graveyard_receipt_count grc,
               morning_sales_volume msv, swing_sales_volume ssv, graveyard_sales_volume gsv
        FROM churn_data
        WHERE user_id = :uid AND date < :d
        ORDER BY date DESC
        LIMIT 14
      ");
      $stmt->execute([':uid'=>$uid, ':d'=>$refDate]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
      $n=0; $sumRc=0; $sumSales=0; $sumCt=0;
      $sumMrc=0; $sumSrc=0; $sumGrc=0; $sumMsv=0; $sumSsv=0; $sumGsv=0;
      foreach ($rows as $r) {
        $sumRc += (int)($r['rc'] ?? 0);
        $sumSales += (float)($r['sales'] ?? 0);
        $sumCt += (int)($r['ct'] ?? 0);
        $sumMrc += (int)($r['mrc'] ?? 0);
        $sumSrc += (int)($r['src'] ?? 0);
        $sumGrc += (int)($r['grc'] ?? 0);
        $sumMsv += (float)($r['msv'] ?? 0);
        $sumSsv += (float)($r['ssv'] ?? 0);
        $sumGsv += (float)($r['gsv'] ?? 0);
        $n++;
      }
      return [
        'avgRc'=>$n? $sumRc/$n : 0.0,
        'avgSales'=>$n? $sumSales/$n : 0.0,
        'avgCt'=>$n? $sumCt/$n : 0.0,
        'avgMrc'=>$n? $sumMrc/$n : 0.0,
        'avgSrc'=>$n? $sumSrc/$n : 0.0,
        'avgGrc'=>$n? $sumGrc/$n : 0.0,
        'avgMsv'=>$n? $sumMsv/$n : 0.0,
        'avgSsv'=>$n? $sumSsv/$n : 0.0,
        'avgGsv'=>$n? $sumGsv/$n : 0.0,
        'days'=>$n
      ];
    }
    $roll = compute_rollups_local($pdo, $uid, $manilaDate);
    if ($war <= 0 && $roll['avgRc'] > 0)   $war = $roll['avgRc'];
    if ($was <= 0 && $roll['avgSales'] > 0)$was = $roll['avgSales'];
    $avgCt = max(0.0, $roll['avgCt']);

    if ($war > 0 && $rc >= 0 && $tdp <= 0) {
      $tdp = max(0.0, min(100.0, ($war - $rc) / $war * 100.0));
    }
    if ($was > 0 && $sales >= 0 && $sdp <= 0) {
      $sdp = max(0.0, min(100.0, ($was - $sales) / $was * 100.0));
    }
    $t_drop = 0.0;
    if ($avgCt > 0 && $ct >= 0) $t_drop = max(0.0, min(100.0, ($avgCt - $ct) / $avgCt * 100.0));

    // shift imbalance
    $shifts = [$mrc, $src, $grc];
    $totalShifts = array_sum($shifts);
    $mean = $totalShifts > 0 ? $totalShifts / count($shifts) : 0.0;
    $imbalance = 0.0;
    if ($mean > 0 && $totalShifts > 0) {
      $sq = 0.0; foreach ($shifts as $v) $sq += ($v - $mean)*($v - $mean);
      $std = sqrt($sq / count($shifts));
      $imbalance = min(100.0, ($std / $mean) * 100.0);
    }

    // analysis data (you can reuse your analyze_business_factors here)
    $analysisData = [
      'rc' => $rc, 'sales' => $sales, 'ct' => $ct,
      'mrc'=>$mrc,'src'=>$src,'grc'=>$grc,
      'msv'=>$msv,'ssv'=>$ssv,'gsv'=>$gsv,
      'tdp'=>$tdp,'sdp'=>$sdp,'t_drop'=>$t_drop,'imbalance'=>$imbalance,
      'war'=>$war,'was'=>$was
    ];

    // keep your own factor analysis (you had this earlier)
    if (!function_exists('analyze_business_factors')) {
      // minimal placeholder if not defined (should be replaced with your full function)
      function analyze_business_factors(array $d, array $r): array {
        return ['factors'=>[], 'critical_count'=>0, 'warning_count'=>0, 'positive_count'=>0];
      }
    }
    $factorAnalysis = analyze_business_factors($analysisData, $roll);

    // Prepare features for model
    $feat = [
      'rc' => (float)$rc,
      'sales' => (float)$sales,
      'ct' => (float)$ct,
      'tdp' => (float)$tdp,
      'sdp' => (float)$sdp,
      't_drop' => (float)$t_drop,
      'imbalance' => (float)$imbalance
    ];

    $usedFallback = false;
    $modelConfidence = 1.0;
    $prob = 0.0;

    // If no meaningful data, use heuristic
    if ($rc == 0 && $sales == 0 && $ct == 0) {
      $prob = 0.05; $modelConfidence = 0.1;
    } else {
      try {
        $modelPath = __DIR__ . '/models/churn_xgb.json';
        $pred = XGBPredictor::loadFrom($modelPath);
        if ($debug) {
          $ex = $pred->explain_proba($feat);
          // return debug info as JSON directly for quick inspection
          j_ok([
            'debug'=>true,
            'explanation'=>$ex,
            'features'=>$feat,
            'analysis'=>$factorAnalysis,
            'rollups'=>$roll
          ]);
          exit;
        }
        // mainstream prediction
        $prob = $pred->predict_proba($feat);
        $modelConfidence = 0.95;
      } catch (Throwable $mx) {
        // fallback heuristic (keeps your earlier heuristic but tuned)
        $usedFallback = true;
        $modelConfidence = 0.6;
        $riskScore = 0.0;
        if ($tdp > 0) $riskScore += ($tdp/100.0) * 0.40;
        if ($sdp > 0) $riskScore += ($sdp/100.0) * 0.30;
        if ($t_drop > 0) $riskScore += ($t_drop/100.0) * 0.15;
        if ($imbalance > 0) $riskScore += min(1.0, $imbalance/50.0) * 0.10;
        if ($factorAnalysis['critical_count'] > 0) $riskScore *= (1.0 + ($factorAnalysis['critical_count'] * 0.2));
        if ($rc < 5 && $sales < 500) $riskScore += 0.15;
        if ($ct > 0 && $rc == 0) $riskScore += 0.25;
        $prob = max(0.0, min(1.0, $riskScore));
      }
    }

    $riskPct = round($prob * 100.0, 2);

    // map probability to level using your thresholds
    function risk_level_from_pct(float $pct): string {
      if ($pct < 25.0) return 'Low';
      if ($pct < 65.0) return 'Medium';
      return 'High';
    }
    $level = risk_level_from_pct($riskPct);

    // generate description (reuse your logic or custom)
    $desc = ($level === 'High') ? 'High churn risk detected. Immediate action.' :
            (($level === 'Medium') ? 'Moderate churn risk. Monitor closely.' : 'Low churn risk. Stable performance.');

    if ($modelConfidence < 0.5) $desc .= ' (Limited data)';
    elseif ($usedFallback && $modelConfidence < 0.8) $desc .= ' (Heuristic fallback)';

    // delete existing predictions for today and insert fresh
    $deletePrev = $pdo->prepare("DELETE FROM churn_predictions WHERE user_id = ? AND for_date = ?");
    $deletePrev->execute([$uid, $manilaDate]);

    $insert = $pdo->prepare("
      INSERT INTO churn_predictions
        (user_id, date, risk_score, risk_level, factors, description, created_at, level, risk_percentage, for_date)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([
      $uid,
      $manilaDate,
      round($riskPct / 100.0, 4),
      $level,
      json_encode($factorAnalysis['factors'] ?? [], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      $desc,
      $manilaDateTime,
      $level,
      round($riskPct, 3),
      $manilaDate
    ]);

    j_ok([
      'saved'=>true,
      'has'=>true,
      'for_date'=>$manilaDate,
      'risk_percentage'=>$riskPct,
      'risk_score'=>round($riskPct/100.0,4),
      'risk_level'=>$level,
      'description'=>$desc,
      'factors'=>$factorAnalysis['factors'] ?? [],
      'is_new_user'=>($rc==0 && $sales==0),
      'data_available'=>($rc>0 || $sales>0 || $ct>0),
      'model_confidence'=>$modelConfidence,
      'analysis_quality'=>$roll['days']>=7 ? 'high' : ($roll['days']>=3 ? 'medium' : 'low')
    ]);

  } catch (Throwable $e) {
    j_err('Prediction run failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

j_err('Unknown action', 400);
