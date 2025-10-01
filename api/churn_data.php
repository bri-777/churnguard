<?php
// api/churn_data.php - Enhanced with 14-day traffic analysis
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

$action = $_GET['action'] ?? 'save';

if ($action === 'save') {
  try {
    // Accept JSON or form-encoded
    $raw = file_get_contents('php://input') ?: '';
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;

    $date = trim((string)($in['date'] ?? ''));
    if ($date === '') j_err('Missing date', 422);

    // Normalize date (YYYY-MM-DD)
    $dt = DateTime::createFromFormat('Y-m-d', $date) ?: DateTime::createFromFormat('m/d/Y', $date);
    if (!$dt) j_err('Invalid date format', 422);
    $date = $dt->format('Y-m-d');

    // Numeric helpers
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

    // UPSERT into churn_data (UNIQUE user_id+date)
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

// Enhanced: Get recent data with limit support
if ($action === 'recent') {
  try {
    $limit = (int)($_GET['limit'] ?? 30);
    $limit = max(1, min(100, $limit)); // Clamp between 1-100

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

// Enhanced: 14-day traffic analysis
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
    
    // Calculate aggregated metrics
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
    
    // Calculate trend (compare recent 7 days vs previous 7 days)
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

// Enhanced: Today's traffic breakdown
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
    
    // Extract shift data
    $morning = (int)($todayData['morning_receipt_count'] ?? 0);
    $swing = (int)($todayData['swing_receipt_count'] ?? 0);
    $graveyard = (int)($todayData['graveyard_receipt_count'] ?? 0);
    $totalShifts = $morning + $swing + $graveyard;
    $totalTraffic = (int)($todayData['customer_traffic'] ?? 0);
    $other = max(0, $totalTraffic - $totalShifts);
    
    // Get yesterday for trend calculation
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $qYesterday = $pdo->prepare("
      SELECT customer_traffic 
      FROM churn_data 
      WHERE user_id = ? AND date = ?
    ");
    $qYesterday->execute([$uid, $yesterday]);
    $yesterdayData = $qYesterday->fetch(PDO::FETCH_ASSOC);
    $yesterdayTraffic = $yesterdayData ? (int)($yesterdayData['customer_traffic'] ?? 0) : 0;
    
    // Calculate trend
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

// Enhanced: Analytics summary
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
    
    // Calculate comprehensive analytics
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