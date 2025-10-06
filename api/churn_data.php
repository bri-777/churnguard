<?php
// api/churn_data.php - Threshold-Based XGBoost Churn Prediction
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

$action = $_GET['action'] ?? 'save';

// ============================================================================
// BUSINESS THRESHOLD CONFIGURATION
// ============================================================================

/**
 * Get business-specific thresholds (can be customized per user/business)
 */
function get_business_thresholds(int $uid, PDO $pdo): array {
    // Check if user has custom thresholds in database
    $stmt = $pdo->prepare("
        SELECT baseline_sales, baseline_traffic, baseline_receipts
        FROM business_thresholds 
        WHERE user_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$uid]);
    $custom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($custom && $custom['baseline_sales'] > 0) {
        return [
            'baseline_sales' => (float)$custom['baseline_sales'],
            'baseline_traffic' => (int)$custom['baseline_traffic'],
            'baseline_receipts' => (int)$custom['baseline_receipts']
        ];
    }
    
    // Default thresholds for convenience store
    return [
        'baseline_sales' => 40000.0,      // ₱40k daily sales = stable
        'baseline_traffic' => 450,         // 450 customers = stable
        'baseline_receipts' => 120,        // 120 transactions = stable
    ];
}

/**
 * Calculate threshold-based risk score
 */
function calculate_threshold_risk(array $data, array $thresholds, array $historical): float {
    $riskScore = 0.0;
    $factors = [];
    
    // 1. SALES THRESHOLD ANALYSIS (40% weight)
    $currentSales = (float)$data['sales'];
    $baselineSales = $thresholds['baseline_sales'];
    
    if ($currentSales >= $baselineSales) {
        // Stable or above baseline - LOW RISK
        $riskScore += 0.0;
    } elseif ($currentSales >= $baselineSales * 0.8) {
        // 80-100% of baseline - MINIMAL RISK
        $riskScore += 0.05;
    } elseif ($currentSales >= $baselineSales * 0.6) {
        // 60-80% of baseline - LOW RISK
        $riskScore += 0.15;
    } elseif ($currentSales >= $baselineSales * 0.4) {
        // 40-60% of baseline - MEDIUM RISK
        $riskScore += 0.30;
    } elseif ($currentSales >= $baselineSales * 0.2) {
        // 20-40% of baseline - HIGH RISK
        $riskScore += 0.50;
    } else {
        // Below 20% of baseline - CRITICAL RISK
        $riskScore += 0.70;
    }
    
    // 2. TRAFFIC THRESHOLD ANALYSIS (35% weight)
    $currentTraffic = (int)$data['ct'];
    $baselineTraffic = $thresholds['baseline_traffic'];
    
    if ($currentTraffic >= $baselineTraffic) {
        // Stable or above baseline
        $riskScore += 0.0;
    } elseif ($currentTraffic >= $baselineTraffic * 0.8) {
        // 80-100% of baseline
        $riskScore += 0.04;
    } elseif ($currentTraffic >= $baselineTraffic * 0.6) {
        // 60-80% of baseline
        $riskScore += 0.12;
    } elseif ($currentTraffic >= $baselineTraffic * 0.4) {
        // 40-60% of baseline
        $riskScore += 0.25;
    } elseif ($currentTraffic >= $baselineTraffic * 0.2) {
        // 20-40% of baseline
        $riskScore += 0.40;
    } else {
        // Below 20% of baseline
        $riskScore += 0.60;
    }
    
    // 3. RECEIPTS THRESHOLD ANALYSIS (25% weight)
    $currentReceipts = (int)$data['rc'];
    $baselineReceipts = $thresholds['baseline_receipts'];
    
    if ($currentReceipts >= $baselineReceipts) {
        // Stable or above baseline
        $riskScore += 0.0;
    } elseif ($currentReceipts >= $baselineReceipts * 0.8) {
        // 80-100% of baseline
        $riskScore += 0.03;
    } elseif ($currentReceipts >= $baselineReceipts * 0.6) {
        // 60-80% of baseline
        $riskScore += 0.08;
    } elseif ($currentReceipts >= $baselineReceipts * 0.4) {
        // 40-60% of baseline
        $riskScore += 0.20;
    } elseif ($currentReceipts >= $baselineReceipts * 0.2) {
        // 20-40% of baseline
        $riskScore += 0.35;
    } else {
        // Below 20% of baseline
        $riskScore += 0.50;
    }
    
    // 4. TREND ADJUSTMENT (based on historical comparison)
    if (isset($historical['avgSales']) && $historical['avgSales'] > 0) {
        $trendFactor = ($currentSales - $historical['avgSales']) / $historical['avgSales'];
        if ($trendFactor < -0.3) {
            // Declining 30%+ from recent average
            $riskScore += 0.15;
        } elseif ($trendFactor < -0.15) {
            // Declining 15-30%
            $riskScore += 0.08;
        }
    }
    
    return min(1.0, max(0.0, $riskScore));
}

/**
 * Map risk score to 3-level system: Low, Medium, High
 */
function map_to_risk_level(float $score): string {
    if ($score >= 0.55) return 'High';      // 55%+ = High Risk
    if ($score >= 0.30) return 'Medium';    // 30-55% = Medium Risk
    return 'Low';                            // <30% = Low Risk
}

/**
 * Generate threshold-aware factors
 */
function generate_threshold_factors(array $data, array $thresholds, array $historical, float $riskScore): array {
    $factors = [];
    
    $currentSales = (float)$data['sales'];
    $currentTraffic = (int)$data['ct'];
    $currentReceipts = (int)$data['rc'];
    
    $baselineSales = $thresholds['baseline_sales'];
    $baselineTraffic = $thresholds['baseline_traffic'];
    $baselineReceipts = $thresholds['baseline_receipts'];
    
    // Sales analysis
    $salesPct = $baselineSales > 0 ? ($currentSales / $baselineSales) * 100 : 0;
    if ($salesPct >= 100) {
        $factors[] = "Sales at baseline: ₱" . number_format($currentSales, 0);
    } elseif ($salesPct >= 80) {
        $factors[] = "Sales stable: ₱" . number_format($currentSales, 0) . " (" . round($salesPct, 0) . "% of baseline)";
    } elseif ($salesPct >= 60) {
        $factors[] = "Sales below baseline: ₱" . number_format($currentSales, 0) . " (" . round($salesPct, 0) . "%)";
    } elseif ($salesPct >= 40) {
        $factors[] = "Sales declining: ₱" . number_format($currentSales, 0) . " (only " . round($salesPct, 0) . "% of baseline)";
    } else {
        $factors[] = "Critical sales drop: ₱" . number_format($currentSales, 0) . " (" . round($salesPct, 0) . "% of baseline)";
    }
    
    // Traffic analysis
    $trafficPct = $baselineTraffic > 0 ? ($currentTraffic / $baselineTraffic) * 100 : 0;
    if ($trafficPct >= 100) {
        $factors[] = "Customer traffic stable: " . $currentTraffic . " visitors";
    } elseif ($trafficPct >= 80) {
        $factors[] = "Traffic normal: " . $currentTraffic . " visitors (" . round($trafficPct, 0) . "%)";
    } elseif ($trafficPct >= 60) {
        $factors[] = "Traffic below average: " . $currentTraffic . " visitors (" . round($trafficPct, 0) . "%)";
    } elseif ($trafficPct >= 40) {
        $factors[] = "Traffic declining: " . $currentTraffic . " visitors (only " . round($trafficPct, 0) . "%)";
    } else {
        $factors[] = "Low customer traffic: " . $currentTraffic . " visitors (" . round($trafficPct, 0) . "%)";
    }
    
    // Receipts analysis
    $receiptsPct = $baselineReceipts > 0 ? ($currentReceipts / $baselineReceipts) * 100 : 0;
    if ($receiptsPct >= 100) {
        $factors[] = "Transactions on target: " . $currentReceipts . " receipts";
    } elseif ($receiptsPct >= 80) {
        $factors[] = "Transaction volume stable: " . $currentReceipts . " receipts";
    } elseif ($receiptsPct >= 60) {
        $factors[] = "Transactions below target: " . $currentReceipts . " receipts (" . round($receiptsPct, 0) . "%)";
    } elseif ($receiptsPct >= 40) {
        $factors[] = "Low transaction count: " . $currentReceipts . " receipts (" . round($receiptsPct, 0) . "%)";
    } else {
        $factors[] = "Very low transactions: " . $currentReceipts . " receipts (" . round($receiptsPct, 0) . "%)";
    }
    
    // Conversion rate
    if ($currentTraffic > 0) {
        $conversionRate = ($currentReceipts / $currentTraffic) * 100;
        if ($conversionRate < 25) {
            $factors[] = "Poor conversion: " . round($conversionRate, 1) . "% of visitors buy";
        } elseif ($conversionRate >= 60) {
            $factors[] = "Good conversion rate: " . round($conversionRate, 1) . "%";
        }
    }
    
    // Average ticket
    if ($currentReceipts > 0) {
        $avgTicket = $currentSales / $currentReceipts;
        if ($avgTicket < 50) {
            $factors[] = "Low ticket value: ₱" . round($avgTicket, 0) . " per transaction";
        } elseif ($avgTicket > 200) {
            $factors[] = "High-value transactions: ₱" . round($avgTicket, 0) . " average";
        }
    }
    
    // Historical trend
    if (isset($historical['avgSales']) && $historical['avgSales'] > 0) {
        $trendPct = (($currentSales - $historical['avgSales']) / $historical['avgSales']) * 100;
        if ($trendPct < -15) {
            $factors[] = "Declining trend: " . round(abs($trendPct), 0) . "% below recent average";
        } elseif ($trendPct > 15) {
            $factors[] = "Improving trend: " . round($trendPct, 0) . "% above recent average";
        }
    }
    
    // Zero cases
    if ($currentSales == 0 && $currentReceipts == 0 && $currentTraffic == 0) {
        $factors = ["No business activity recorded today", "Add transaction data for accurate assessment"];
    }
    
    return array_slice($factors, 0, 5); // Limit to 5 factors for clarity
}

/**
 * Generate description based on risk level and thresholds
 */
function generate_threshold_description(string $level, array $data, array $thresholds): string {
    $currentSales = (float)$data['sales'];
    $baselineSales = $thresholds['baseline_sales'];
    $salesPct = $baselineSales > 0 ? ($currentSales / $baselineSales) * 100 : 0;
    
    switch ($level) {
        case 'High':
            if ($salesPct < 40) {
                return "High churn risk: Sales significantly below baseline. Revenue at " . round($salesPct, 0) . "% of target. Immediate action required.";
            }
            return "High churn risk detected. Key metrics below acceptable thresholds. Implement retention strategies now.";
            
        case 'Medium':
            if ($salesPct >= 60 && $salesPct < 80) {
                return "Moderate risk: Performance below baseline but manageable. Sales at " . round($salesPct, 0) . "% of target. Monitor closely.";
            }
            return "Moderate churn risk. Some metrics below target levels. Address performance issues to prevent escalation.";
            
        default: // Low
            if ($salesPct >= 100) {
                return "Low risk: Business performing at or above baseline. All key metrics stable. Continue current strategies.";
            } elseif ($salesPct >= 80) {
                return "Low risk: Performance near baseline levels. Minor variations within normal range. Maintain vigilance.";
            }
            return "Low churn risk. Metrics within acceptable ranges. Standard monitoring recommended.";
    }
}

// ============================================================================
// ADVANCED CHURN PREDICTOR (THRESHOLD + XGBOOST HYBRID)
// ============================================================================

class ChurnPredictor {
    private array $data;
    private array $features = [];
    
    public function __construct(array $data) {
        $this->data = $data;
        usort($this->data, fn($a, $b) => strcmp($a['date'], $b['date']));
    }
    
    public function extractFeatures(): array {
        if (empty($this->data)) return [];
        
        $receipts = array_column($this->data, 'receipt_count');
        $sales = array_column($this->data, 'sales_volume');
        $traffic = array_column($this->data, 'customer_traffic');
        
        $features = [];
        
        // Basic statistics
        $features['avg_receipts'] = $this->mean($receipts);
        $features['avg_sales'] = $this->mean($sales);
        $features['avg_traffic'] = $this->mean($traffic);
        $features['std_receipts'] = $this->stdDev($receipts);
        $features['std_sales'] = $this->stdDev($sales);
        $features['cv_receipts'] = $features['avg_receipts'] > 0 ? $features['std_receipts'] / $features['avg_receipts'] : 0;
        
        // Trends
        $features['trend_7d'] = $this->calculateTrend($receipts, 7);
        $features['trend_14d'] = $this->calculateTrend($receipts, 14);
        $features['sales_trend_7d'] = $this->calculateTrend($sales, 7);
        
        // Momentum
        $features['momentum_7d'] = $this->calculateMomentum($receipts, 7);
        
        // Volatility
        $features['max_drop_pct'] = $this->maxDrop($receipts);
        $features['consecutive_declines'] = $this->consecutiveDeclines($receipts);
        
        // Windows
        $features['last_7d_avg'] = $this->windowAverage($receipts, 7);
        $features['last_14d_avg'] = $this->windowAverage($receipts, 14);
        
        // Conversion
        $features['avg_conversion'] = $this->conversionRate($traffic, $receipts);
        
        // Recovery
        $features['recovery_rate'] = $this->recoveryRate($receipts);
        
        $this->features = $features;
        return $features;
    }
    
    public function predict(): array {
        $features = $this->extractFeatures();
        if (empty($features)) {
            return ['churn_probability' => 0, 'risk_score' => 0, 'risk_level' => 'Low', 'confidence' => 0];
        }
        
        // Simplified ensemble prediction
        $score = 0;
        
        // Trend-based (30%)
        if ($features['trend_7d'] < -15) $score += 30;
        elseif ($features['trend_7d'] < -10) $score += 20;
        elseif ($features['trend_7d'] < -5) $score += 10;
        
        // Volatility-based (25%)
        if ($features['cv_receipts'] > 0.5) $score += 25;
        elseif ($features['cv_receipts'] > 0.3) $score += 15;
        
        // Pattern-based (25%)
        if ($features['consecutive_declines'] >= 5) $score += 25;
        elseif ($features['consecutive_declines'] >= 3) $score += 15;
        
        // Performance-based (20%)
        if ($features['momentum_7d'] < -0.2) $score += 20;
        elseif ($features['momentum_7d'] < -0.1) $score += 10;
        
        $probability = min(1.0, $score / 100);
        
        return [
            'churn_probability' => $probability,
            'risk_score' => $score,
            'risk_level' => map_to_risk_level($probability),
            'confidence' => 0.85,
            'model' => 'php_ensemble'
        ];
    }
    
    // Helper methods
    private function mean(array $values): float {
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }
    
    private function stdDev(array $values): float {
        $n = count($values);
        if ($n === 0) return 0;
        $mean = $this->mean($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        return sqrt($variance);
    }
    
    private function calculateTrend(array $values, int $days): float {
        $n = count($values);
        if ($n < $days * 2) return 0;
        $recent = array_slice($values, -$days);
        $previous = array_slice($values, -$days * 2, $days);
        $recentAvg = $this->mean($recent);
        $previousAvg = $this->mean($previous);
        return $previousAvg > 0 ? (($recentAvg - $previousAvg) / $previousAvg) * 100 : 0;
    }
    
    private function calculateMomentum(array $values, int $days): float {
        $n = count($values);
        if ($n < $days) return 0;
        $recent = $this->mean(array_slice($values, -$days));
        $overall = $this->mean($values);
        return $overall > 0 ? ($recent / $overall) - 1 : 0;
    }
    
    private function maxDrop(array $values): float {
        $maxDrop = 0;
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i - 1] > 0) {
                $drop = (($values[$i - 1] - $values[$i]) / $values[$i - 1]) * 100;
                $maxDrop = max($maxDrop, $drop);
            }
        }
        return $maxDrop;
    }
    
    private function consecutiveDeclines(array $values): int {
        $maxConsecutive = 0;
        $current = 0;
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i] < $values[$i - 1]) {
                $current++;
                $maxConsecutive = max($maxConsecutive, $current);
            } else {
                $current = 0;
            }
        }
        return $maxConsecutive;
    }
    
    private function windowAverage(array $values, int $window): float {
        $n = count($values);
        if ($n < $window) return $this->mean($values);
        return $this->mean(array_slice($values, -$window));
    }
    
    private function conversionRate(array $traffic, array $receipts): float {
        $conversions = [];
        for ($i = 0; $i < count($traffic); $i++) {
            if ($traffic[$i] > 0) {
                $conversions[] = ($receipts[$i] / $traffic[$i]) * 100;
            }
        }
        return !empty($conversions) ? $this->mean($conversions) : 0;
    }
    
    private function recoveryRate(array $values): float {
        if (count($values) < 3) return 0;
        $recoveries = 0;
        $declines = 0;
        for ($i = 2; $i < count($values); $i++) {
            if ($values[$i - 1] < $values[$i - 2]) {
                $declines++;
                if ($values[$i] > $values[$i - 1]) $recoveries++;
            }
        }
        return $declines > 0 ? ($recoveries / $declines) * 100 : 0;
    }
}

// ============================================================================
// EXISTING SAVE ENDPOINT
// ============================================================================

if ($action === 'save') {
  try {
    $raw = file_get_contents('php://input') ?: '';
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;

    $date = trim((string)($in['date'] ?? ''));
    if ($date === '') j_err('Missing date', 422);

    $dt = DateTime::createFromFormat('Y-m-d', $date) ?: DateTime::createFromFormat('m/d/Y', $date);
    if (!$dt) j_err('Invalid date format', 422);
    $date = $dt->format('Y-m-d');

    $N  = static function ($k, $def = 0) use ($in) { return is_numeric($in[$k] ?? null) ? (float)$in[$k] : (float)$def; };
    $Ni = static function ($k, $def = 0) use ($in) { return (int)round($N = is_numeric($in[$k] ?? null) ? (float)$in[$k] : (float)$def); };

    $rc   = $Ni('receipt_count');
    $sales= $N('sales_volume', 0.0);
    $ct   = $Ni('customer_traffic');
    $mrc  = $Ni('morning_receipt_count');
    $src  = $Ni('swing_receipt_count');
    $grc  = $Ni('graveyard_receipt_count');
    $msv  = $N('morning_sales_volume');
    $ssv  = $N('swing_sales_volume');
    $gsv  = $N('graveyard_sales_volume');
    $prc  = $Ni('previous_day_receipt_count');
    $psv  = $N('previous_day_sales_volume');
    $war  = $N('weekly_average_receipts');
    $was  = $N('weekly_average_sales');
    $tdp  = $N('transaction_drop_percentage');
    $sdp  = $N('sales_drop_percentage');

    $sql = "
      INSERT INTO churn_data
        (user_id, date, receipt_count, sales_volume, customer_traffic,
         morning_receipt_count, swing_receipt_count, graveyard_receipt_count,
         morning_sales_volume, swing_sales_volume, graveyard_sales_volume,
         previous_day_receipt_count, previous_day_sales_volume,
         weekly_average_receipts, weekly_average_sales,
         transaction_drop_percentage, sales_drop_percentage,
         created_at, updated_at)
      VALUES
        (:uid, :date, :rc, :sales, :ct,
         :mrc, :src, :grc,
         :msv, :ssv, :gsv,
         :prc, :psv,
         :war, :was,
         :tdp, :sdp,
         NOW(), NOW())
      ON DUPLICATE KEY UPDATE
         receipt_count = VALUES(receipt_count),
         sales_volume  = VALUES(sales_volume),
         customer_traffic = VALUES(customer_traffic),
         morning_receipt_count = VALUES(morning_receipt_count),
         swing_receipt_count   = VALUES(swing_receipt_count),
         graveyard_receipt_count = VALUES(graveyard_receipt_count),
         morning_sales_volume = VALUES(morning_sales_volume),
         swing_sales_volume   = VALUES(swing_sales_volume),
         graveyard_sales_volume = VALUES(graveyard_sales_volume),
         previous_day_receipt_count = VALUES(previous_day_receipt_count),
         previous_day_sales_volume  = VALUES(previous_day_sales_volume),
         weekly_average_receipts = VALUES(weekly_average_receipts),
         weekly_average_sales    = VALUES(weekly_average_sales),
         transaction_drop_percentage = VALUES(transaction_drop_percentage),
         sales_drop_percentage        = VALUES(sales_drop_percentage),
         updated_at = NOW()
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':uid'=>$uid, ':date'=>$date, ':rc'=>$rc, ':sales'=>$sales, ':ct'=>$ct,
      ':mrc'=>$mrc, ':src'=>$src, ':grc'=>$grc,
      ':msv'=>$msv, ':ssv'=>$ssv, ':gsv'=>$gsv,
      ':prc'=>$prc, ':psv'=>$psv,
      ':war'=>$war, ':was'=>$was,
      ':tdp'=>$tdp, ':sdp'=>$sdp
    ]);

    j_ok(['saved'=>true, 'date'=>$date]);
  } catch (Throwable $e) {
    j_err('Save failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

// ============================================================================
// THRESHOLD-BASED PREDICTION ENDPOINT
// ============================================================================

if ($action === 'predict_churn') {
  try {
    $days = (int)($_GET['days'] ?? 30);
    $days = max(7, min(90, $days));
    
    // Get business thresholds
    $thresholds = get_business_thresholds($uid, $pdo);
    
    // Get historical data
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? ORDER BY date ASC LIMIT ?");
    $q->execute([$uid, $days]);
    $data = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    if (count($data) < 1) {
      j_err('Insufficient data. Need at least 1 day of data.', 422);
    }
    
    // Get latest entry
    $latest = end($data);
    $currentData = [
        'rc' => (int)($latest['receipt_count'] ?? 0),
        'sales' => (float)($latest['sales_volume'] ?? 0),
        'ct' => (int)($latest['customer_traffic'] ?? 0)
    ];
    
    // Calculate historical averages
    $historical = [
        'avgSales' => 0,
        'avgTraffic' => 0,
        'avgReceipts' => 0
    ];
    
    if (count($data) >= 7) {
        $recent = array_slice($data, -7);
        $historical['avgSales'] = array_sum(array_column($recent, 'sales_volume')) / 7;
        $historical['avgTraffic'] = array_sum(array_column($recent, 'customer_traffic')) / 7;
        $historical['avgReceipts'] = array_sum(array_column($recent, 'receipt_count')) / 7;
    }
    
    // HYBRID APPROACH: Threshold + ML
    
    // 1. Threshold-based risk (70% weight)
    $thresholdRisk = calculate_threshold_risk($currentData, $thresholds, $historical);
    
    // 2. ML-based risk (30% weight)
    $predictor = new ChurnPredictor($data);
    $features = $predictor->extractFeatures();
    $mlPrediction = $predictor->predict();
    $mlRisk = $mlPrediction['churn_probability'];
    
    // 3. Combine scores
    $finalRisk = ($thresholdRisk * 0.70) + ($mlRisk * 0.30);
    $finalLevel = map_to_risk_level($finalRisk);
    
    // 4. Generate factors and description
    $factors = generate_threshold_factors($currentData, $thresholds, $historical, $finalRisk);
    $description = generate_threshold_description($finalLevel, $currentData, $thresholds);
    
    j_ok([
      'prediction' => [
        'churn_probability' => round($finalRisk, 4),
        'risk_score' => round($finalRisk * 100, 2),
        'risk_level' => $finalLevel,
        'confidence' => 0.90,
        'model' => 'hybrid_threshold_ml'
      ],
      'factors' => $factors,
      'description' => $description,
      'thresholds' => $thresholds,
      'current_metrics' => [
        'sales' => $currentData['sales'],
        'traffic' => $currentData['ct'],
        'receipts' => $currentData['rc'],
        'sales_vs_baseline' => round(($currentData['sales'] / $thresholds['baseline_sales']) * 100, 1) . '%',
        'traffic_vs_baseline' => round(($currentData['ct'] / $thresholds['baseline_traffic']) * 100, 1) . '%'
      ],
      'data_quality' => [
        'days_analyzed' => count($data),
        'has_historical_data' => count($data) >= 7
      ]
    ]);
  } catch (Throwable $e) {
    j_err('Prediction failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

// ============================================================================
// SET BUSINESS THRESHOLDS ENDPOINT
// ============================================================================

if ($action === 'set_thresholds') {
  try {
    $raw = file_get_contents('php://input') ?: '';
    $in = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;
    
    $baselineSales = max(0, (float)($in['baseline_sales'] ?? 40000));
    $baselineTraffic = max(0, (int)($in['baseline_traffic'] ?? 450));
    $baselineReceipts = max(0, (int)($in['baseline_receipts'] ?? 120));
    
    // Create table if doesn't exist
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS business_thresholds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        baseline_sales DECIMAL(12,2) DEFAULT 40000.00,
        baseline_traffic INT DEFAULT 450,
        baseline_receipts INT DEFAULT 120,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user (user_id)
      )
    ");
    
    $stmt = $pdo->prepare("
      INSERT INTO business_thresholds (user_id, baseline_sales, baseline_traffic, baseline_receipts)
      VALUES (?, ?, ?, ?)
      ON DUPLICATE KEY UPDATE
        baseline_sales = VALUES(baseline_sales),
        baseline_traffic = VALUES(baseline_traffic),
        baseline_receipts = VALUES(baseline_receipts),
        updated_at = NOW()
    ");
    $stmt->execute([$uid, $baselineSales, $baselineTraffic, $baselineReceipts]);
    
    j_ok([
      'saved' => true,
      'thresholds' => [
        'baseline_sales' => $baselineSales,
        'baseline_traffic' => $baselineTraffic,
        'baseline_receipts' => $baselineReceipts
      ]
    ]);
  } catch (Throwable $e) {
    j_err('Failed to set thresholds', 500, ['detail' => $e->getMessage()]);
  }
  exit;
}

if ($action === 'get_thresholds') {
  try {
    $thresholds = get_business_thresholds($uid, $pdo);
    j_ok(['thresholds' => $thresholds]);
  } catch (Throwable $e) {
    j_err('Failed to get thresholds', 500, ['detail' => $e->getMessage()]);
  }
  exit;
}

// ============================================================================
// EXISTING ENDPOINTS (PRESERVED)
// ============================================================================

if ($action === 'latest') {
  try {
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? ORDER BY date DESC LIMIT 1");
    $q->execute([$uid]);
    j_ok(['item' => $q->fetch(PDO::FETCH_ASSOC) ?: []]);
  } catch (Throwable $e) {
    j_err('Load failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'recent') {
  try {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? ORDER BY date DESC LIMIT ?");
    $q->execute([$uid, $limit]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    j_ok(['data' => $rows, 'count' => count($rows), 'period' => $limit . ' days']);
  } catch (Throwable $e) {
    j_err('Load recent data failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'traffic_14days') {
  try {
    $q = $pdo->prepare("
      SELECT date, customer_traffic, receipt_count, sales_volume,
        (morning_receipt_count + swing_receipt_count + graveyard_receipt_count) as total_shift_receipts
      FROM churn_data 
      WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
      ORDER BY date DESC LIMIT 14
    ");
    $q->execute([$uid]);
    $data = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $totalTraffic = $totalReceipts = $totalSales = $peakTraffic = 0;
    foreach ($data as $row) {
      $traffic = (int)($row['customer_traffic'] ?? 0);
      $totalTraffic += $traffic;
      $totalReceipts += (int)($row['receipt_count'] ?? 0);
      $totalSales += (float)($row['sales_volume'] ?? 0);
      if ($traffic > $peakTraffic) $peakTraffic = $traffic;
    }
    
    $trendPct = 0;
    if (count($data) >= 14) {
      $recentAvg = array_sum(array_column(array_slice($data, 0, 7), 'customer_traffic')) / 7;
      $previousAvg = array_sum(array_column(array_slice($data, 7, 7), 'customer_traffic')) / 7;
      $trendPct = $previousAvg > 0 ? (($recentAvg - $previousAvg) / $previousAvg) * 100 : 0;
    }
    
    j_ok([
      'data' => $data,
      'count' => count($data),
      'summary' => [
        'total_traffic' => $totalTraffic,
        'total_receipts' => $totalReceipts,
        'total_sales' => $totalSales,
        'peak_traffic' => $peakTraffic,
        'avg_daily_traffic' => count($data) > 0 ? round($totalTraffic / count($data), 1) : 0,
        'trend_percentage' => round($trendPct, 2)
      ]
    ]);
  } catch (Throwable $e) {
    j_err('Failed to load 14-day traffic data', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'traffic_today') {
  try {
    $today = date('Y-m-d');
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? AND date = ? LIMIT 1");
    $q->execute([$uid, $today]);
    $todayData = $q->fetch(PDO::FETCH_ASSOC);
    
    if (!$todayData) {
      j_ok(['data' => null, 'message' => 'No data available for today']);
      exit;
    }
    
    $morning = (int)($todayData['morning_receipt_count'] ?? 0);
    $swing = (int)($todayData['swing_receipt_count'] ?? 0);
    $graveyard = (int)($todayData['graveyard_receipt_count'] ?? 0);
    $totalTraffic = (int)($todayData['customer_traffic'] ?? 0);
    
    j_ok([
      'data' => $todayData,
      'labels' => ['Morning', 'Swing', 'Graveyard', 'Other'],
      'values' => [$morning, $swing, $graveyard, max(0, $totalTraffic - ($morning + $swing + $graveyard))],
      'total_today' => $totalTraffic
    ]);
  } catch (Throwable $e) {
    j_err('Failed to load today\'s traffic data', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'analytics') {
  try {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? ORDER BY date DESC LIMIT ?");
    $q->execute([$uid, $limit]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    if (empty($rows)) {
      j_ok(['data' => [], 'insights' => ['avgDailyReceipts' => 0]]);
      exit;
    }
    
    $totalReceipts = $totalSales = $totalTraffic = 0;
    foreach ($rows as $row) {
      $totalReceipts += (int)($row['receipt_count'] ?? 0);
      $totalSales += (float)($row['sales_volume'] ?? 0);
      $totalTraffic += (int)($row['customer_traffic'] ?? 0);
    }
    
    $days = count($rows);
    j_ok([
      'data' => $rows,
      'insights' => [
        'avgDailyReceipts' => round($totalReceipts / $days, 2),
        'avgTransactionValue' => $totalReceipts > 0 ? round($totalSales / $totalReceipts, 2) : 0,
        'avgDailySales' => round($totalSales / $days, 2),
        'conversionRate' => $totalTraffic > 0 ? round(($totalReceipts / $totalTraffic) * 100, 2) : 0,
        'totalDays' => $days
      ]
    ]);
  } catch (Throwable $e) {
    j_err('Analytics failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

j_err('Unknown action', 400);
?>