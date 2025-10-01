<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
$uid = require_login();

header('Content-Type: application/json; charset=utf-8');

// 14-day window (Manila)
$tz    = new DateTimeZone('Asia/Manila');
$now   = new DateTime('now', $tz);
$today = $now->format('Y-m-d');
$start = (clone $now)->modify('-13 days')->format('Y-m-d');

$dayStartTs = (clone $now)->setTime(0, 0, 0)->format('Y-m-d H:i:s');
$rangeStartTs = (new DateTime($start . ' 00:00:00', $tz))->format('Y-m-d H:i:s');
$rangeEndTs   = (clone $now)->setTime(23, 59, 59)->format('Y-m-d H:i:s');

/*
 * Pull rows if EITHER:
 *  - DATE(`date`) is within [start..today], OR
 *  - created_at is within [start 00:00..today 23:59] Manila
 *
 * This catches rows where `date` was missing/different, but `created_at` is correct.
 */
$sql = "
  SELECT
    `date`,               -- DATE or DATETIME or VARCHAR
    created_at,           -- DATETIME
    COALESCE(customer_traffic, 0) AS customer_traffic,
    COALESCE(receipt_count, 0)    AS receipt_count,
    COALESCE(morning_receipt_count, 0)   AS mrc,
    COALESCE(swing_receipt_count, 0)     AS src,
    COALESCE(graveyard_receipt_count, 0) AS grc
  FROM churn_data
  WHERE user_id = :uid
    AND (
      (DATE(`date`) BETWEEN :start AND :end)
      OR
      (created_at BETWEEN :rstart AND :rend)
    )
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
  ':uid'   => $uid,
  ':start' => $start,
  ':end'   => $today,
  ':rstart'=> $rangeStartTs,
  ':rend'  => $rangeEndTs
]);

// Build day => sum map using a robust dayKey and "effective traffic"
$map = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
  // Normalize possible DATE/DATETIME/STRING `date`
  $rawDate = isset($r['date']) ? (string)$r['date'] : '';
  $dFromDate = $rawDate !== '' && $rawDate !== '0000-00-00' ? substr($rawDate, 0, 10) : null;

  // Derive Manila day from created_at if needed
  $createdAt = isset($r['created_at']) ? (string)$r['created_at'] : '';
  $dFromCreated = null;
  if ($createdAt !== '') {
    // created_at assumed stored in DB server time; treat as naive and just take 'YYYY-MM-DD'
    // (If you store UTC, still safe for day bucketing because we also filter by range)
    $dFromCreated = substr($createdAt, 0, 10);
  }

  // Choose dayKey: prefer a valid `date` inside window; else use created_at's day
  $dayKey = null;
  if ($dFromDate && $dFromDate >= $start && $dFromDate <= $today) {
    $dayKey = $dFromDate;
  } elseif ($dFromCreated && $dFromCreated >= $start && $dFromCreated <= $today) {
    $dayKey = $dFromCreated;
  } else {
    // Row is outside our window; skip
    continue;
  }

  // Effective traffic so today doesn't appear 0 when only receipts/shift counts exist
  $shiftSum = (int)$r['mrc'] + (int)$r['src'] + (int)$r['grc'];
  $eff = max((int)$r['customer_traffic'], (int)$r['receipt_count'], $shiftSum);

  if (!isset($map[$dayKey])) $map[$dayKey] = 0;
  $map[$dayKey] += $eff;
}

// Build continuous 14-day series
$labels = [];
$values = [];
$cur = new DateTime($start, $tz);
$end = new DateTime($today, $tz);
while ($cur <= $end) {
  $d = $cur->format('Y-m-d');
  $labels[] = $d;
  $values[] = (int)($map[$d] ?? 0);
  $cur->modify('+1 day');
}

// Summary
$totalToday = !empty($values) ? (int)end($values) : 0;
$first7   = array_slice($values, 0, 7);
$last7    = array_slice($values, -7);
$sumFirst = array_sum($first7);
$sumLast  = array_sum($last7);
$trendPct = ($sumFirst > 0) ? round((($sumLast - $sumFirst) / $sumFirst) * 100, 1) : 0.0;
$peak     = !empty($values) ? max($values) : 0;

// Output with aliases your JS might read
echo json_encode([
  'period'     => '14d',
  'labels'     => $labels,
  'hours'      => $labels,   // alias
  'values'     => $values,
  'counts'     => $values,   // alias
  'totalToday' => $totalToday,
  'peak'       => (int)$peak,
  'trendPct'   => $trendPct
], JSON_UNESCAPED_UNICODE);
