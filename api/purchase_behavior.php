<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
$uid = require_login();

header('Content-Type: application/json; charset=utf-8');

// last 14 days window
$tz = new DateTimeZone('Asia/Manila');
$today = (new DateTime('now', $tz))->format('Y-m-d');
$start = (new DateTime('now', $tz))->modify('-13 days')->format('Y-m-d');

// Pull daily aggregates
$sql = "
  SELECT date,
         COALESCE(SUM(receipt_count),0) AS receipts,
         COALESCE(SUM(sales_volume),0) AS sales,
         COALESCE(SUM(customer_traffic),0) AS traffic,
         COALESCE(SUM(morning_receipt_count),0) AS mrc,
         COALESCE(SUM(swing_receipt_count),0)   AS src,
         COALESCE(SUM(graveyard_receipt_count),0) AS grc,
         COALESCE(SUM(morning_sales_volume),0) AS m_sales,
         COALESCE(SUM(swing_sales_volume),0)   AS s_sales,
         COALESCE(SUM(graveyard_sales_volume),0) AS g_sales
  FROM churn_data
  WHERE user_id = :uid
    AND date BETWEEN :start AND :end
  GROUP BY date
  ORDER BY date ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid'=>$uid, ':start'=>$start, ':end'=>$today]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Reduce to 14D totals
$days = count($rows);
$tot = [
  'receipts'=>0, 'sales'=>0, 'traffic'=>0,
  'mrc'=>0, 'src'=>0, 'grc'=>0,
  'm_sales'=>0, 's_sales'=>0, 'g_sales'=>0
];
foreach ($rows as $r) {
  $tot['receipts'] += (int)$r['receipts'];
  $tot['sales']    += (float)$r['sales'];
  $tot['traffic']  += (int)$r['traffic'];
  $tot['mrc']      += (int)$r['mrc'];
  $tot['src']      += (int)$r['src'];
  $tot['grc']      += (int)$r['grc'];
  $tot['m_sales']  += (float)$r['m_sales'];
  $tot['s_sales']  += (float)$r['s_sales'];
  $tot['g_sales']  += (float)$r['g_sales'];
}

// Today line (if present)
$todayRow = null;
foreach ($rows as $r) {
  if ($r['date'] === $today) { $todayRow = $r; break; }
}
$todayReceipts = (int)($todayRow['receipts'] ?? 0);
$todaySales    = (float)($todayRow['sales'] ?? 0);
$todayTraffic  = (int)($todayRow['traffic'] ?? 0);

// Derived metrics
$avgDailyReceipts    = $days > 0 ? $tot['receipts'] / $days : 0;
$avgDailySales       = $days > 0 ? $tot['sales'] / $days : 0;
$avgTransactionValue = $tot['receipts'] > 0 ? $tot['sales'] / $tot['receipts'] : 0;
$revenuePerCustomer  = $tot['traffic'] > 0 ? $tot['sales'] / $tot['traffic'] : 0;

$totalShiftReceipts  = $tot['mrc'] + $tot['src'] + $tot['grc'];
$morningPercentage   = $totalShiftReceipts > 0 ? ($tot['mrc'] / $totalShiftReceipts) * 100 : 0;
$swingPercentage     = $totalShiftReceipts > 0 ? ($tot['src'] / $totalShiftReceipts) * 100 : 0;
$graveyardPercentage = $totalShiftReceipts > 0 ? ($tot['grc'] / $totalShiftReceipts) * 100 : 0;

$conversionRate      = $tot['traffic'] > 0 ? ($tot['receipts'] / $tot['traffic']) * 100 : 0;
$todayAvgTicket      = $todayReceipts > 0 ? ($todaySales / $todayReceipts) : 0;

$insights = [
  'totalDays' => $days,
  'totalReceipts' => $tot['receipts'],
  'totalSales' => $tot['sales'],
  'avgDailyReceipts' => round($avgDailyReceipts, 2),
  'avgDailySales' => round($avgDailySales, 2),
  'avgTransactionValue' => round($avgTransactionValue, 2),
  'revenuePerCustomer' => round($revenuePerCustomer, 2),
  'morningPercentage' => round($morningPercentage, 1),
  'swingPercentage' => round($swingPercentage, 1),
  'graveyardPercentage' => round($graveyardPercentage, 1),
  'conversionRate' => round($conversionRate, 1),
  'todayReceipts' => $todayReceipts,
  'todaySales' => round($todaySales, 2),
  'todayTraffic' => $todayTraffic,
  'todayAvgTicket' => round($todayAvgTicket, 2)
];

echo json_encode([
  'period' => '14d',
  'labels' => [
    'Daily Receipts (Avg)',
    'Transaction Value (₱)',
    'Revenue per Customer (₱)',
    'Morning Shift %',
    'Swing Shift %',
    'Graveyard Shift %',
    'Conversion Rate %'
  ],
  'values' => [
    $insights['avgDailyReceipts'],
    $insights['avgTransactionValue'],
    $insights['revenuePerCustomer'],
    $insights['morningPercentage'],
    $insights['swingPercentage'],
    $insights['graveyardPercentage'],
    $insights['conversionRate']
  ],
  'insights' => $insights
], JSON_UNESCAPED_UNICODE);
