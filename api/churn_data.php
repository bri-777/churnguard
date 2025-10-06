<?php
// api/churn_data.php - Enhanced with ML-based Prediction System
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

// ==================== PREDICTION ENGINE ====================
class ChurnPredictor {
  private $pdo;
  private $uid;
  
  public function __construct($pdo, $uid) {
    $this->pdo = $pdo;
    $this->uid = $uid;
  }
  
  /**
   * Advanced ensemble prediction using gradient boosting principles
   */
  public function predictChurnRisk(array $features): array {
    // Normalize features
    $normalized = $this->normalizeFeatures($features);
    
    // Multiple prediction models for ensemble
    $predictions = [
      'trend_model' => $this->trendBasedPrediction($normalized),
      'volatility_model' => $this->volatilityPrediction($normalized),
      'pattern_model' => $this->patternRecognition($normalized),
      'anomaly_model' => $this->anomalyDetection($normalized),
      'momentum_model' => $this->momentumPrediction($normalized)
    ];
    
    // Weighted ensemble (boost high-confidence predictions)
    $weights = [
      'trend_model' => 0.25,
      'volatility_model' => 0.20,
      'pattern_model' => 0.25,
      'anomaly_model' => 0.15,
      'momentum_model' => 0.15
    ];
    
    $finalScore = 0;
    $confidence = 0;
    
    foreach ($predictions as $model => $result) {
      $finalScore += $result['score'] * $weights[$model];
      $confidence += $result['confidence'] * $weights[$model];
    }
    
    // Classify risk level
    $riskLevel = $this->classifyRisk($finalScore);
    
    return [
      'churn_risk_score' => round($finalScore, 2),
      'risk_level' => $riskLevel,
      'confidence' => round($confidence * 100, 1),
      'model_predictions' => $predictions,
      'factors' => $this->identifyKeyFactors($normalized, $predictions),
      'recommendations' => $this->generateRecommendations($riskLevel, $normalized)
    ];
  }
  
  /**
   * Trend-based prediction (captures declining patterns)
   */
  private function trendBasedPrediction(array $f): array {
    $score = 0;
    $signals = [];
    
    // Transaction drop signal
    if ($f['transaction_drop_pct'] > 15) {
      $score += 30 * ($f['transaction_drop_pct'] / 100);
      $signals[] = 'High transaction drop';
    } elseif ($f['transaction_drop_pct'] > 5) {
      $score += 15 * ($f['transaction_drop_pct'] / 100);
    }
    
    // Sales decline signal
    if ($f['sales_drop_pct'] > 20) {
      $score += 25 * ($f['sales_drop_pct'] / 100);
      $signals[] = 'Significant sales decline';
    } elseif ($f['sales_drop_pct'] > 10) {
      $score += 12 * ($f['sales_drop_pct'] / 100);
    }
    
    // Recent vs weekly average
    $receiptRatio = $f['avg_weekly_receipts'] > 0 
      ? $f['recent_receipts'] / $f['avg_weekly_receipts'] 
      : 1;
    
    if ($receiptRatio < 0.7) {
      $score += 20 * (1 - $receiptRatio);
      $signals[] = 'Below average performance';
    }
    
    $salesRatio = $f['avg_weekly_sales'] > 0 
      ? $f['recent_sales'] / $f['avg_weekly_sales'] 
      : 1;
    
    if ($salesRatio < 0.75) {
      $score += 15 * (1 - $salesRatio);
    }
    
    // Confidence based on data quality
    $confidence = min(1.0, $f['data_points'] / 30);
    
    return [
      'score' => min(100, $score),
      'confidence' => $confidence,
      'signals' => $signals
    ];
  }
  
  /**
   * Volatility prediction (detects instability)
   */
  private function volatilityPrediction(array $f): array {
    $score = 0;
    $signals = [];
    
    // High coefficient of variation indicates instability
    $cv = $f['cv_receipts'];
    if ($cv > 0.4) {
      $score += 25 * ($cv / 0.4);
      $signals[] = 'High transaction volatility';
    }
    
    $cvSales = $f['cv_sales'];
    if ($cvSales > 0.45) {
      $score += 20 * ($cvSales / 0.45);
      $signals[] = 'Unstable sales pattern';
    }
    
    // Traffic inconsistency
    if ($f['traffic_variance'] > 0.5) {
      $score += 15 * $f['traffic_variance'];
      $signals[] = 'Erratic customer traffic';
    }
    
    $confidence = min(1.0, $f['data_points'] / 21);
    
    return [
      'score' => min(100, $score),
      'confidence' => $confidence,
      'signals' => $signals
    ];
  }
  
  /**
   * Pattern recognition (day-of-week and shift patterns)
   */
  private function patternRecognition(array $f): array {
    $score = 0;
    $signals = [];
    
    // Shift imbalance detection
    $totalShiftReceipts = $f['morning_receipts'] + $f['swing_receipts'] + $f['graveyard_receipts'];
    
    if ($totalShiftReceipts > 0) {
      $morningRatio = $f['morning_receipts'] / $totalShiftReceipts;
      $swingRatio = $f['swing_receipts'] / $totalShiftReceipts;
      $graveyardRatio = $f['graveyard_receipts'] / $totalShiftReceipts;
      
      // Unhealthy if one shift dominates (>60%) or is too weak (<10%)
      if ($morningRatio < 0.1 || $swingRatio < 0.1 || $graveyardRatio < 0.1) {
        $score += 15;
        $signals[] = 'Shift coverage issue';
      }
      
      if ($morningRatio > 0.6 || $swingRatio > 0.6 || $graveyardRatio > 0.6) {
        $score += 10;
      }
    }
    
    // Conversion rate (traffic to receipts)
    $conversionRate = $f['avg_traffic'] > 0 
      ? $f['recent_receipts'] / $f['avg_traffic'] 
      : 0;
    
    if ($conversionRate < 0.3) {
      $score += 20 * (0.5 - $conversionRate);
      $signals[] = 'Low conversion rate';
    }
    
    // Days since last transaction
    if ($f['days_since_last'] > 3) {
      $score += 25 * min(1, $f['days_since_last'] / 7);
      $signals[] = 'Extended inactivity period';
    }
    
    $confidence = 0.85;
    
    return [
      'score' => min(100, $score),
      'confidence' => $confidence,
      'signals' => $signals
    ];
  }
  
  /**
   * Anomaly detection using statistical methods
   */
  private function anomalyDetection(array $f): array {
    $score = 0;
    $signals = [];
    
    // Z-score based anomaly detection
    // Recent receipts significantly below mean
    if ($f['avg_receipts'] > 0 && $f['std_receipts'] > 0) {
      $zScore = ($f['recent_receipts'] - $f['avg_receipts']) / $f['std_receipts'];
      
      if ($zScore < -2) {
        $score += 30 * abs($zScore / 3);
        $signals[] = 'Anomalous transaction count';
      } elseif ($zScore < -1) {
        $score += 15 * abs($zScore / 2);
      }
    }
    
    // Sales anomaly
    if ($f['avg_sales'] > 0 && $f['std_sales'] > 0) {
      $zScoreSales = ($f['recent_sales'] - $f['avg_sales']) / $f['std_sales'];
      
      if ($zScoreSales < -2) {
        $score += 25 * abs($zScoreSales / 3);
        $signals[] = 'Anomalous sales volume';
      }
    }
    
    // Sudden traffic drop
    if ($f['traffic_trend'] < -0.3) {
      $score += 20 * abs($f['traffic_trend']);
      $signals[] = 'Sharp traffic decline';
    }
    
    $confidence = min(1.0, $f['data_points'] / 25);
    
    return [
      'score' => min(100, $score),
      'confidence' => $confidence,
      'signals' => $signals
    ];
  }
  
  /**
   * Momentum prediction (rate of change analysis)
   */
  private function momentumPrediction(array $f): array {
    $score = 0;
    $signals = [];
    
    // Accelerating decline
    if ($f['transaction_drop_pct'] > 0 && $f['prev_transaction_drop_pct'] > 0) {
      if ($f['transaction_drop_pct'] > $f['prev_transaction_drop_pct']) {
        $score += 25;
        $signals[] = 'Accelerating decline';
      }
    }
    
    // Consecutive declining days
    if ($f['consecutive_declines'] >= 3) {
      $score += 15 * min(1, $f['consecutive_declines'] / 5);
      $signals[] = 'Sustained downward trend';
    }
    
    // Week-over-week momentum
    if ($f['wow_receipt_change'] < -10) {
      $score += 20 * abs($f['wow_receipt_change'] / 30);
    }
    
    if ($f['wow_sales_change'] < -15) {
      $score += 15 * abs($f['wow_sales_change'] / 30);
    }
    
    $confidence = 0.80;
    
    return [
      'score' => min(100, $score),
      'confidence' => $confidence,
      'signals' => $signals
    ];
  }
  
  /**
   * Normalize and engineer features from raw data
   */
  private function normalizeFeatures(array $raw): array {
    return [
      'transaction_drop_pct' => (float)($raw['transaction_drop_pct'] ?? 0),
      'sales_drop_pct' => (float)($raw['sales_drop_pct'] ?? 0),
      'recent_receipts' => (float)($raw['recent_receipts'] ?? 0),
      'recent_sales' => (float)($raw['recent_sales'] ?? 0),
      'avg_weekly_receipts' => (float)($raw['avg_weekly_receipts'] ?? 0),
      'avg_weekly_sales' => (float)($raw['avg_weekly_sales'] ?? 0),
      'avg_receipts' => (float)($raw['avg_receipts'] ?? 0),
      'avg_sales' => (float)($raw['avg_sales'] ?? 0),
      'std_receipts' => (float)($raw['std_receipts'] ?? 1),
      'std_sales' => (float)($raw['std_sales'] ?? 1),
      'cv_receipts' => (float)($raw['cv_receipts'] ?? 0),
      'cv_sales' => (float)($raw['cv_sales'] ?? 0),
      'morning_receipts' => (float)($raw['morning_receipts'] ?? 0),
      'swing_receipts' => (float)($raw['swing_receipts'] ?? 0),
      'graveyard_receipts' => (float)($raw['graveyard_receipts'] ?? 0),
      'avg_traffic' => (float)($raw['avg_traffic'] ?? 1),
      'traffic_variance' => (float)($raw['traffic_variance'] ?? 0),
      'traffic_trend' => (float)($raw['traffic_trend'] ?? 0),
      'days_since_last' => (int)($raw['days_since_last'] ?? 0),
      'consecutive_declines' => (int)($raw['consecutive_declines'] ?? 0),
      'data_points' => (int)($raw['data_points'] ?? 1),
      'wow_receipt_change' => (float)($raw['wow_receipt_change'] ?? 0),
      'wow_sales_change' => (float)($raw['wow_sales_change'] ?? 0),
      'prev_transaction_drop_pct' => (float)($raw['prev_transaction_drop_pct'] ?? 0)
    ];
  }
  
  /**
   * Classify risk into categories
   */
  private function classifyRisk(float $score): string {
    if ($score >= 70) return 'CRITICAL';
    if ($score >= 50) return 'HIGH';
    if ($score >= 30) return 'MEDIUM';
    if ($score >= 15) return 'LOW';
    return 'MINIMAL';
  }
  
  /**
   * Identify key contributing factors
   */
  private function identifyKeyFactors(array $features, array $predictions): array {
    $factors = [];
    
    foreach ($predictions as $model => $result) {
      if (!empty($result['signals'])) {
        foreach ($result['signals'] as $signal) {
          $factors[] = [
            'factor' => $signal,
            'source' => $model,
            'impact' => round($result['score'] * 0.15, 1)
          ];
        }
      }
    }
    
    // Sort by impact
    usort($factors, fn($a, $b) => $b['impact'] <=> $a['impact']);
    
    return array_slice($factors, 0, 5);
  }
  
  /**
   * Generate actionable recommendations
   */
  private function generateRecommendations(string $riskLevel, array $features): array {
    $recommendations = [];
    
    switch ($riskLevel) {
      case 'CRITICAL':
        $recommendations[] = 'URGENT: Immediate intervention required';
        $recommendations[] = 'Contact customer within 24 hours';
        $recommendations[] = 'Offer incentives or promotional discounts';
        break;
      case 'HIGH':
        $recommendations[] = 'Schedule follow-up within 48 hours';
        $recommendations[] = 'Review service quality and customer feedback';
        $recommendations[] = 'Consider targeted re-engagement campaign';
        break;
      case 'MEDIUM':
        $recommendations[] = 'Monitor closely for next 7 days';
        $recommendations[] = 'Send engagement content or updates';
        break;
      case 'LOW':
        $recommendations[] = 'Continue regular monitoring';
        $recommendations[] = 'Maintain service quality';
        break;
      default:
        $recommendations[] = 'Account is healthy';
    }
    
    // Specific recommendations based on features
    if ($features['days_since_last'] > 3) {
      $recommendations[] = 'Customer inactive for ' . $features['days_since_last'] . ' days - re-engage';
    }
    
    if ($features['cv_receipts'] > 0.4) {
      $recommendations[] = 'High transaction variability - stabilize engagement';
    }
    
    return $recommendations;
  }
  
  /**
   * Calculate comprehensive features from historical data
   */
  public function calculateFeatures(): array {
    try {
      // Get last 30 days of data
      $q = $this->pdo->prepare("
        SELECT *
        FROM churn_data
        WHERE user_id = ?
        ORDER BY date DESC
        LIMIT 30
      ");
      $q->execute([$this->uid]);
      $data = $q->fetchAll(PDO::FETCH_ASSOC);
      
      if (count($data) < 7) {
        throw new Exception('Insufficient data for prediction (minimum 7 days required)');
      }
      
      // Calculate statistical features
      $receipts = array_map(fn($r) => (float)$r['receipt_count'], $data);
      $sales = array_map(fn($r) => (float)$r['sales_volume'], $data);
      $traffic = array_map(fn($r) => (float)$r['customer_traffic'], $data);
      
      $avgReceipts = array_sum($receipts) / count($receipts);
      $avgSales = array_sum($sales) / count($sales);
      $avgTraffic = array_sum($traffic) / count($traffic);
      
      $stdReceipts = $this->stdDev($receipts, $avgReceipts);
      $stdSales = $this->stdDev($sales, $avgSales);
      
      $cvReceipts = $avgReceipts > 0 ? $stdReceipts / $avgReceipts : 0;
      $cvSales = $avgSales > 0 ? $stdSales / $avgSales : 0;
      
      // Recent values (last entry)
      $recent = $data[0];
      $recentReceipts = (float)$recent['receipt_count'];
      $recentSales = (float)$recent['sales_volume'];
      
      // Calculate traffic trend and variance
      $trafficVariance = count($traffic) > 1 ? $this->calculateVariance($traffic) : 0;
      $trafficTrend = $this->calculateTrend($traffic);
      
      // Days since last transaction
      $daysSince = (new DateTime())->diff(new DateTime($recent['date']))->days;
      
      // Consecutive declining days
      $consecutiveDeclines = $this->countConsecutiveDeclines($receipts);
      
      // Week-over-week changes
      $wowReceiptChange = 0;
      $wowSalesChange = 0;
      
      if (count($data) >= 14) {
        $thisWeek = array_slice($receipts, 0, 7);
        $lastWeek = array_slice($receipts, 7, 7);
        $thisWeekAvg = array_sum($thisWeek) / 7;
        $lastWeekAvg = array_sum($lastWeek) / 7;
        $wowReceiptChange = $lastWeekAvg > 0 ? (($thisWeekAvg - $lastWeekAvg) / $lastWeekAvg) * 100 : 0;
        
        $thisWeekSales = array_slice($sales, 0, 7);
        $lastWeekSales = array_slice($sales, 7, 7);
        $thisWeekSalesAvg = array_sum($thisWeekSales) / 7;
        $lastWeekSalesAvg = array_sum($lastWeekSales) / 7;
        $wowSalesChange = $lastWeekSalesAvg > 0 ? (($thisWeekSalesAvg - $lastWeekSalesAvg) / $lastWeekSalesAvg) * 100 : 0;
      }
      
      // Previous period drop (for momentum)
      $prevTransactionDropPct = 0;
      if (count($data) >= 2) {
        $prevEntry = $data[1];
        $prevTransactionDropPct = (float)($prevEntry['transaction_drop_percentage'] ?? 0);
      }
      
      // Shift data from most recent
      $morningReceipts = (float)($recent['morning_receipt_count'] ?? 0);
      $swingReceipts = (float)($recent['swing_receipt_count'] ?? 0);
      $graveyardReceipts = (float)($recent['graveyard_receipt_count'] ?? 0);
      
      return [
        'transaction_drop_pct' => (float)($recent['transaction_drop_percentage'] ?? 0),
        'sales_drop_pct' => (float)($recent['sales_drop_percentage'] ?? 0),
        'recent_receipts' => $recentReceipts,
        'recent_sales' => $recentSales,
        'avg_weekly_receipts' => (float)($recent['weekly_average_receipts'] ?? $avgReceipts),
        'avg_weekly_sales' => (float)($recent['weekly_average_sales'] ?? $avgSales),
        'avg_receipts' => $avgReceipts,
        'avg_sales' => $avgSales,
        'std_receipts' => $stdReceipts,
        'std_sales' => $stdSales,
        'cv_receipts' => $cvReceipts,
        'cv_sales' => $cvSales,
        'morning_receipts' => $morningReceipts,
        'swing_receipts' => $swingReceipts,
        'graveyard_receipts' => $graveyardReceipts,
        'avg_traffic' => $avgTraffic,
        'traffic_variance' => $trafficVariance,
        'traffic_trend' => $trafficTrend,
        'days_since_last' => $daysSince,
        'consecutive_declines' => $consecutiveDeclines,
        'data_points' => count($data),
        'wow_receipt_change' => $wowReceiptChange,
        'wow_sales_change' => $wowSalesChange,
        'prev_transaction_drop_pct' => $prevTransactionDropPct
      ];
      
    } catch (Throwable $e) {
      throw new Exception('Feature calculation failed: ' . $e->getMessage());
    }
  }
  
  // Helper statistical functions
  private function stdDev(array $values, float $mean): float {
    $variance = 0;
    foreach ($values as $val) {
      $variance += pow($val - $mean, 2);
    }
    return sqrt($variance / count($values));
  }
  
  private function calculateVariance(array $values): float {
    if (count($values) < 2) return 0;
    $mean = array_sum($values) / count($values);
    $variance = 0;
    foreach ($values as $val) {
      $variance += pow($val - $mean, 2);
    }
    return $variance / count($values);
  }
  
  private function calculateTrend(array $values): float {
    if (count($values) < 2) return 0;
    $n = count($values);
    $recent = array_slice($values, 0, min(7, $n));
    $older = array_slice($values, min(7, $n), min(7, $n));
    
    if (empty($older)) return 0;
    
    $recentAvg = array_sum($recent) / count($recent);
    $olderAvg = array_sum($older) / count($older);
    
    return $olderAvg > 0 ? ($recentAvg - $olderAvg) / $olderAvg : 0;
  }
  
  private function countConsecutiveDeclines(array $values): int {
    $count = 0;
    for ($i = 0; $i < count($values) - 1; $i++) {
      if ($values[$i] < $values[$i + 1]) {
        $count++;
      } else {
        break;
      }
    }
    return $count;
  }
}

// ==================== MAIN API ROUTING ====================

$action = $_GET['action'] ?? 'save';

// NEW: Prediction endpoint
if ($action === 'predict') {
  try {
    $predictor = new ChurnPredictor($pdo, $uid);
    
    // Calculate features from historical data
    $features = $predictor->calculateFeatures();
    
    // Run prediction
    $prediction = $predictor->predictChurnRisk($features);
    
    j_ok([
      'prediction' => $prediction,
      'features_used' => $features,
      'timestamp' => date('Y-m-d H:i:s'),
      'model_version' => '2.0-ensemble'
    ]);
    
  } catch (Throwable $e) {
    j_err('Prediction failed', 500, ['detail' => $e->getMessage()]);
  }
  exit;
}

// Existing endpoints below...

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

if ($action === 'latest') {
  try {
    $q = $pdo->prepare("
      SELECT *
      FROM churn_data
      WHERE user_id = ?
      ORDER BY date DESC
      LIMIT 1
    ");
    $q->execute([$uid]);
    $row = $q->fetch(PDO::FETCH_ASSOC) ?: [];
    j_ok(['item'=>$row]);
  } catch (Throwable $e) {
    j_err('Load failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'recent') {
  try {
    $limit = (int)($_GET['limit'] ?? 30);
    $limit = max(1, min(100, $limit));

    $q = $pdo->prepare("
      SELECT *
      FROM churn_data
      WHERE user_id = ?
      ORDER BY date DESC
      LIMIT ?
    ");
    $q->execute([$uid, $limit]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    j_ok([
      'data' => $rows,
      'count' => count($rows),
      'period' => $limit . ' days'
    ]);
  } catch (Throwable $e) {
    j_err('Load recent data failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'traffic_14days') {
  try {
    $q = $pdo->prepare("
      SELECT 
        date,
        customer_traffic,
        receipt_count,
        sales_volume,
        (morning_receipt_count + swing_receipt_count + graveyard_receipt_count) as total_shift_receipts
      FROM churn_data 
      WHERE user_id = ? 
        AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
      ORDER BY date DESC
      LIMIT 14
    ");
    $q->execute([$uid]);
    $data = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $totalTraffic = 0;
    $totalReceipts = 0;
    $totalSales = 0;
    $peakTraffic = 0;
    $avgTraffic = 0;
    
    if (!empty($data)) {
      foreach ($data as $row) {
        $traffic = (int)($row['customer_traffic'] ?? 0);
        $totalTraffic += $traffic;
        $totalReceipts += (int)($row['receipt_count'] ?? 0);
        $totalSales += (float)($row['sales_volume'] ?? 0);
        if ($traffic > $peakTraffic) $peakTraffic = $traffic;
      }
      $avgTraffic = count($data) > 0 ? $totalTraffic / count($data) : 0;
    }
    
    $trendPct = 0;
    if (count($data) >= 14) {
      $recent7 = array_slice($data, 0, 7);
      $previous7 = array_slice($data, 7, 7);
      
      $recentAvg = array_sum(array_column($recent7, 'customer_traffic')) / 7;
      $previousAvg = array_sum(array_column($previous7, 'customer_traffic')) / 7;
      
      if ($previousAvg > 0) {
        $trendPct = (($recentAvg - $previousAvg) / $previousAvg) * 100;
      }
    }
    
    j_ok([
      'data' => $data,
      'count' => count($data),
      'summary' => [
        'total_traffic' => $totalTraffic,
        'total_receipts' => $totalReceipts,
        'total_sales' => $totalSales,
        'peak_traffic' => $peakTraffic,
        'avg_daily_traffic' => round($avgTraffic, 1),
        'trend_percentage' => round($trendPct, 2)
      ],
      'period' => '14 days'
    ]);
  } catch (Throwable $e) {
    j_err('Failed to load 14-day traffic data', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'traffic_today') {
  try {
    $today = date('Y-m-d');
    
    $q = $pdo->prepare("
      SELECT 
        date,
        customer_traffic,
        receipt_count,
        sales_volume,
        morning_receipt_count,
        swing_receipt_count,
        graveyard_receipt_count,
        morning_sales_volume,
        swing_sales_volume,
        graveyard_sales_volume
      FROM churn_data 
      WHERE user_id = ? 
        AND date = ?
      LIMIT 1
    ");
    $q->execute([$uid, $today]);
    $todayData = $q->fetch(PDO::FETCH_ASSOC);
    
    if (!$todayData) {
      j_ok([
        'data' => null,
        'message' => 'No data available for today',
        'labels' => ['Morning', 'Swing', 'Graveyard', 'Other'],
        'values' => [0, 0, 0, 0],
        'total_today' => 0,
        'peak_hour_traffic' => 0,
        'trend_pct' => 0
      ]);
      exit;
    }
    
    $morning = (int)($todayData['morning_receipt_count'] ?? 0);
    $swing = (int)($todayData['swing_receipt_count'] ?? 0);
    $graveyard = (int)($todayData['graveyard_receipt_count'] ?? 0);
    $totalShifts = $morning + $swing + $graveyard;
    $totalTraffic = (int)($todayData['customer_traffic'] ?? 0);
    $other = max(0, $totalTraffic - $totalShifts);
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $qYesterday = $pdo->prepare("
      SELECT customer_traffic 
      FROM churn_data 
      WHERE user_id = ? AND date = ?
    ");
    $qYesterday->execute([$uid, $yesterday]);
    $yesterdayData = $qYesterday->fetch(PDO::FETCH_ASSOC);
    $yesterdayTraffic = $yesterdayData ? (int)($yesterdayData['customer_traffic'] ?? 0) : 0;
    
    $trendPct = 0;
    if ($yesterdayTraffic > 0) {
      $trendPct = (($totalTraffic - $yesterdayTraffic) / $yesterdayTraffic) * 100;
    }
    
    j_ok([
      'data' => $todayData,
      'labels' => ['Morning', 'Swing', 'Graveyard', 'Other'],
      'values' => [$morning, $swing, $graveyard, $other],
      'hours' => ['6AM-2PM', '2PM-10PM', '10PM-6AM', 'Unassigned'],
      'counts' => [$morning, $swing, $graveyard, $other],
      'total_today' => $totalTraffic,
      'peak_hour_traffic' => max($morning, $swing, $graveyard),
      'trend_pct' => round($trendPct, 2),
      'shift_breakdown' => [
        'morning' => ['receipts' => $morning, 'sales' => (float)($todayData['morning_sales_volume'] ?? 0)],
        'swing' => ['receipts' => $swing, 'sales' => (float)($todayData['swing_sales_volume'] ?? 0)],
        'graveyard' => ['receipts' => $graveyard, 'sales' => (float)($todayData['graveyard_sales_volume'] ?? 0)]
      ]
    ]);
  } catch (Throwable $e) {
    j_err('Failed to load today\'s traffic data', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'analytics') {
  try {
    $limit = (int)($_GET['limit'] ?? 30);
    $limit = max(1, min(100, $limit));

    $q = $pdo->prepare("
      SELECT *
      FROM churn_data
      WHERE user_id = ?
      ORDER BY date DESC
      LIMIT ?
    ");
    $q->execute([$uid, $limit]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    if (empty($rows)) {
      j_ok([
        'data' => [],
        'insights' => [
          'avgDailyReceipts' => 0,
          'avgTransactionValue' => 0,
          'avgDailySales' => 0,
          'totalDays' => 0
        ],
        'message' => 'No data available for analytics'
      ]);
      exit;
    }
    
    $totalReceipts = 0;
    $totalSales = 0;
    $totalTraffic = 0;
    $totalDays = count($rows);
    
    foreach ($rows as $row) {
      $totalReceipts += (int)($row['receipt_count'] ?? 0);
      $totalSales += (float)($row['sales_volume'] ?? 0);
      $totalTraffic += (int)($row['customer_traffic'] ?? 0);
    }
    
    $avgDailyReceipts = $totalDays > 0 ? $totalReceipts / $totalDays : 0;
    $avgTransactionValue = $totalReceipts > 0 ? $totalSales / $totalReceipts : 0;
    $avgDailySales = $totalDays > 0 ? $totalSales / $totalDays : 0;
    $avgDailyTraffic = $totalDays > 0 ? $totalTraffic / $totalDays : 0;
    $conversionRate = $totalTraffic > 0 ? ($totalReceipts / $totalTraffic) * 100 : 0;
    
    j_ok([
      'data' => $rows,
      'insights' => [
        'avgDailyReceipts' => round($avgDailyReceipts, 2),
        'avgTransactionValue' => round($avgTransactionValue, 2),
        'avgDailySales' => round($avgDailySales, 2),
        'avgDailyTraffic' => round($avgDailyTraffic, 1),
        'conversionRate' => round($conversionRate, 2),
        'totalDays' => $totalDays,
        'totalReceipts' => $totalReceipts,
        'totalSales' => $totalSales,
        'totalTraffic' => $totalTraffic
      ],
      'period' => $limit . ' days'
    ]);
  } catch (Throwable $e) {
    j_err('Analytics failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

j_err('Unknown action', 400);
?>