<?php
// api/reports/behavior_report.php
require __DIR__ . '/../_bootstrap.php';
$uid = require_login();

try {
  $stmt = $pdo->prepare("
    SELECT COALESCE(receipt_count,0) rc, COALESCE(sales_volume,0) sales, COALESCE(customer_traffic,0) ct
    FROM churn_data
    WHERE user_id = ?
    ORDER BY date DESC
    LIMIT 7
  ");
  $stmt->execute([$uid]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $sumRc=0; $sumSales=0; $sumCt=0; $n=0;
  foreach ($rows as $r) {
    $sumRc += (int)$r['rc'];
    $sumSales += (float)$r['sales'];
    $sumCt += (int)$r['ct'];
    $n++;
  }
  $avgRc = $n ? $sumRc / $n : 0;
  $avgSales = $n ? $sumSales / $n : 0;
  $avgBasket = $avgRc > 0 ? ($avgSales / max(1,$avgRc)) : 0;

  // Loyalty rate = 100 - latest risk%
  $p = $pdo->prepare("SELECT risk_percentage FROM churn_predictions WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
  $p->execute([$uid]);
  $row = $p->fetch(PDO::FETCH_ASSOC);
  $rp  = isset($row['risk_percentage']) ? (float)$row['risk_percentage'] : 0.0;
  if ($rp <= 1.0) $rp *= 100.0;
  $loyalty = round(max(0.0, 100.0 - min(100.0,$rp)), 2);

  json_ok([
    'avgFrequency' => round($avgRc, 0),
    'avgValue'     => round($avgBasket, 2),
    'loyaltyRate'  => $loyalty
  ]);
} catch (Throwable $e) {
  json_error('Behavior report error', 500, ['detail'=>$e->getMessage()]);
}
