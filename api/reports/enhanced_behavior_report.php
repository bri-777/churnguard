<?php
// api/reports/enhanced_behavior_report.php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

try {
  // Get last 7 days of data for behavior analysis
  $stmt = $pdo->prepare("
    SELECT 
      COALESCE(receipt_count, 0) AS rc, 
      COALESCE(sales_volume, 0) AS sales, 
      COALESCE(customer_traffic, 0) AS ct,
      date
    FROM churn_data
    WHERE user_id = ?
    ORDER BY date DESC
    LIMIT 7
  ");
  $stmt->execute([$uid]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  $sumRc = 0; 
  $sumSales = 0; 
  $sumCt = 0; 
  $n = count($rows);
  
  foreach ($rows as $r) {
    $sumRc += (int)$r['rc'];
    $sumSales += (float)$r['sales'];
    $sumCt += (int)$r['ct'];
  }
  
  // Calculate averages
  $avgFrequency = $n > 0 ? round($sumRc / $n, 0) : 0;
  $avgSales = $n > 0 ? $sumSales / $n : 0;
  $avgBasket = $avgFrequency > 0 ? round($avgSales / max(1, $avgFrequency), 2) : 0;
  
  // Get latest risk percentage for loyalty calculation
  $riskStmt = $pdo->prepare("
    SELECT COALESCE(risk_percentage, 0) AS risk_percentage 
    FROM churn_predictions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
  ");
  $riskStmt->execute([$uid]);
  $riskRow = $riskStmt->fetch(PDO::FETCH_ASSOC);
  $riskPct = (float)($riskRow['risk_percentage'] ?? 0);
  
  // Normalize risk percentage (ensure it's 0-100)
  if ($riskPct <= 1.0) $riskPct *= 100.0;
  $riskPct = min(100.0, max(0.0, $riskPct));
  
  // Calculate loyalty rate (inverse of churn risk)
  $loyaltyRate = round(max(0.0, 100.0 - $riskPct), 2);
  
  j_ok([
    'avgFrequency' => $avgFrequency,
    'avgValue' => $avgBasket,
    'loyaltyRate' => $loyaltyRate,
    'avgTraffic' => $n > 0 ? round($sumCt / $n, 0) : 0,
    'totalDays' => $n
  ]);
  
} catch (Throwable $e) {
  j_err('Behavior report error: ' . $e->getMessage(), 500);
}
?>

<?php
// api/reports/enhanced_revenue_report.php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

try {
  // Get latest business data
  $stmt = $pdo->prepare("
    SELECT 
      COALESCE(sales_volume, 0) AS sales, 
      COALESCE(receipt_count, 0) AS rc,
      COALESCE(customer_traffic, 0) AS ct
    FROM churn_data
    WHERE user_id = ? 
    ORDER BY date DESC
    LIMIT 1
  ");
  $stmt->execute([$uid]);
  $todayData = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['sales' => 0, 'rc' => 0, 'ct' => 0];
  
  $sales = (float)$todayData['sales'];
  $receipts = (int)$todayData['rc'];
  $traffic = (int)$todayData['ct'];
  $avgBasket = $receipts > 0 ? $sales / max(1, $receipts) : 0.0;
  
  // Get latest risk percentage
  $riskStmt = $pdo->prepare("
    SELECT COALESCE(risk_percentage, 0) AS risk_percentage 
    FROM churn_predictions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
  ");
  $riskStmt->execute([$uid]);
  $riskRow = $riskStmt->fetch(PDO::FETCH_ASSOC);
  $riskPct = (float)($riskRow['risk_percentage'] ?? 0);
  
  // Normalize risk percentage
  if ($riskPct <= 1.0) $riskPct *= 100.0;
  $riskPct = min(100.0, max(0.0, $riskPct));
  
  // Enhanced revenue impact calculations
  $preventableRate = 0.30; // 30% of churn risk is preventable
  $marginRate = 0.25; // 25% profit margin assumption
  
  // Revenue at risk calculation (more sophisticated)
  $customersAtRisk = round($traffic * ($riskPct / 100.0));
  $revenueAtRisk = $customersAtRisk * $avgBasket;
  $revenueSaved = round($revenueAtRisk * $preventableRate, 2);
  
  // CLV impact calculation (30-day horizon)
  $dailyFrequency = $receipts; // transactions per day
  $monthlyValue = $avgBasket * $dailyFrequency * 30;
  $monthlyProfit = $monthlyValue * $marginRate;
  $clvImpact = round($monthlyProfit * ($riskPct / 100.0) * $preventableRate, 2);
  
  // ROI calculation for retention efforts
  $retentionCost = round($sales * 0.03, 2); // 3% of sales for retention programs
  $roi = $retentionCost > 0 ? round((($revenueSaved - $retentionCost) / $retentionCost) * 100.0, 2) : 0.0;
  
  j_ok([
    'revenueSaved' => max(0, $revenueSaved),
    'clvImpact' => max(0, $clvImpact),
    'roi' => $roi,
    'customersAtRisk' => $customersAtRisk,
    'revenueAtRisk' => $revenueAtRisk,
    'retentionCost' => $retentionCost
  ]);
  
} catch (Throwable $e) {
  j_err('Revenue report error: ' . $e->getMessage(), 500);
}
?>

<?php
// api/reports/enhanced_retention_report.php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

try {
  // Get the latest date with data
  $maxDateStmt = $pdo->prepare("SELECT MAX(date) AS maxd FROM churn_data WHERE user_id = ?");
  $maxDateStmt->execute([$uid]);
  $maxDate = $maxDateStmt->fetchColumn();
  
  if (!$maxDate) {
    // No historical data - use prediction only
    $predStmt = $pdo->prepare("
      SELECT 
        COALESCE(risk_percentage, 0) AS risk_percentage, 
        created_at, 
        risk_level 
      FROM churn_predictions 
      WHERE user_id = ? 
      ORDER BY created_at DESC 
      LIMIT 1
    ");
    $predStmt->execute([$uid]);
    $pred = $predStmt->fetch(PDO::FETCH_ASSOC) ?: [
      'risk_percentage' => 0, 
      'created_at' => date('Y-m-d H:i:s'), 
      'risk_level' => 'Low'
    ];
    
    $riskPct = (float)$pred['risk_percentage'];
    if ($riskPct <= 1.0) $riskPct *= 100.0;
    $retentionRate = round(max(0.0, 100.0 - min(100.0, $riskPct)), 2);
    
    j_ok([
      'retentionRate' => $retentionRate,
      'churnRate' => round(100.0 - $retentionRate, 2),
      'atRiskCount' => 0,
      'retentionDeltaPts' => null,
      'churnDeltaPts' => null,
      'lastUpdated' => $pred['created_at'],
      'prediction' => $pred
    ]);
    exit;
  }
  
  // Get 21 days of data for trend analysis
  $dataStmt = $pdo->prepare("
    SELECT 
      date, 
      COALESCE(receipt_count, 0) AS rc, 
      COALESCE(customer_traffic, 0) AS ct
    FROM churn_data
    WHERE user_id = ? AND date BETWEEN DATE_SUB(?, INTERVAL 20 DAY) AND ?
    ORDER BY date ASC
  ");
  $dataStmt->execute([$uid, $maxDate, $maxDate]);
  $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
  
  $totalRows = count($rows);
  if ($totalRows === 0) {
    // Fallback to prediction
    $predStmt = $pdo->prepare("
      SELECT 
        COALESCE(risk_percentage, 0) AS risk_percentage, 
        created_at, 
        risk_level 
      FROM churn_predictions 
      WHERE user_id = ? 
      ORDER BY created_at DESC 
      LIMIT 1
    ");
    $predStmt->execute([$uid]);
    $pred = $predStmt->fetch(PDO::FETCH_ASSOC) ?: [
      'risk_percentage' => 0, 
      'created_at' => date('Y-m-d H:i:s'), 
      'risk_level' => 'Low'
    ];
    
    $riskPct = (float)$pred['risk_percentage'];
    if ($riskPct <= 1.0) $riskPct *= 100.0;
    $retentionRate = round(max(0.0, 100.0 - min(100.0, $riskPct)), 2);
    
    j_ok([
      'retentionRate' => $retentionRate,
      'churnRate' => round(100.0 - $retentionRate, 2),
      'atRiskCount' => 0,
      'retentionDeltaPts' => null,
      'churnDeltaPts' => null,
      'lastUpdated' => $pred['created_at'],
      'prediction' => $pred
    ]);
    exit;
  }
  
  // Calculate retention using week-over-week analysis
  $endIdx = $totalRows - 1;
  $has21Days = $totalRows >= 21;
  
  // Sum function for week ranges
  $sumWeek = function($startIdx, $endIdx, $rows) {
    $rcSum = 0;
    $ctSum = 0;
    for ($i = max(0, $startIdx); $i <= min(count($rows) - 1, $endIdx); $i++) {
      $rcSum += (int)$rows[$i]['rc'];
      $ctSum += (int)$rows[$i]['ct'];
    }
    return ['rc' => $rcSum, 'ct' => $ctSum];
  };
  
  // Current week (W0): last 7 days
  $w0 = $sumWeek($endIdx - 6, $endIdx, $rows);
  
  // Previous week (W1): 8-14 days ago
  $w1 = $sumWeek($endIdx - 13, $endIdx - 7, $rows);
  
  // Pre-previous week (W2): 15-21 days ago
  $w2 = $has21Days ? $sumWeek($endIdx - 20, $endIdx - 14, $rows) : ['rc' => 0, 'ct' => 0];
  
  // Calculate retention proxy (receipt retention rate)
  $retentionRate0 = ($w1['rc'] > 0) ? ($w0['rc'] / $w1['rc']) * 100.0 : null;
  $retentionRate1 = ($has21Days && $w2['rc'] > 0) ? ($w1['rc'] / $w2['rc']) * 100.0 : null;
  
  // Fallback to prediction if calculation fails
  if ($retentionRate0 === null) {
    $predStmt = $pdo->prepare("
      SELECT 
        COALESCE(risk_percentage, 0) AS risk_percentage, 
        created_at, 
        risk_level 
      FROM churn_predictions 
      WHERE user_id = ? 
      ORDER BY created_at DESC 
      LIMIT 1
    ");
    $predStmt->execute([$uid]);
    $pred = $predStmt->fetch(PDO::FETCH_ASSOC) ?: [
      'risk_percentage' => 0, 
      'created_at' => date('Y-m-d H:i:s'), 
      'risk_level' => 'Low'
    ];
    
    $riskPct = (float)$pred['risk_percentage'];
    if ($riskPct <= 1.0) $riskPct *= 100.0;
    $retentionRate0 = max(0.0, 100.0 - min(100.0, $riskPct));
  }
  
  $finalRetentionRate = round($retentionRate0, 2);
  $finalChurnRate = round(100.0 - $finalRetentionRate, 2);
  $retentionDelta = ($retentionRate1 !== null) ? round($retentionRate0 - $retentionRate1, 2) : null;
  $churnDelta = ($retentionRate1 !== null) ? round((100.0 - $retentionRate0) - (100.0 - $retentionRate1), 2) : null;
  
  // Calculate at-risk customers
  $latestTraffic = (int)($rows[$endIdx]['ct'] ?? 0);
  $predStmt = $pdo->prepare("
    SELECT 
      COALESCE(risk_percentage, 0) AS risk_percentage, 
      created_at, 
      risk_level 
    FROM churn_predictions 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
  ");
  $predStmt->execute([$uid]);
  $pred = $predStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'risk_percentage' => 0, 
    'created_at' => date('Y-m-d H:i:s'), 
    'risk_level' => 'Low'
  ];
  
  $riskPct = (float)$pred['risk_percentage'];
  if ($riskPct <= 1.0) $riskPct *= 100.0;
  $atRiskCount = (int)round($latestTraffic * ($riskPct / 100.0));
  
  j_ok([
    'retentionRate' => $finalRetentionRate,
    'churnRate' => $finalChurnRate,
    'atRiskCount' => $atRiskCount,
    'retentionDeltaPts' => $retentionDelta,
    'churnDeltaPts' => $churnDelta,
    'lastUpdated' => $maxDate . ' 23:59:59',
    'prediction' => $pred
  ]);
  
} catch (Throwable $e) {
  j_err('Retention report error: ' . $e->getMessage(), 500);
}
?>