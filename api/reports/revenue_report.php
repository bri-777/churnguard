<?php
// api/reports/revenue_report.php
require __DIR__ . '/../_bootstrap.php';
$uid = require_login();

try {
  // Today
  $stmt = $pdo->prepare("
    SELECT COALESCE(sales_volume,0) sales, COALESCE(receipt_count,0) rc
    FROM churn_data
    WHERE user_id = ? AND date = CURDATE()
    LIMIT 1
  ");
  $stmt->execute([$uid]);
  $t = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['sales'=>0,'rc'=>0];
  $sales = (float)$t['sales'];
  $rc    = (int)$t['rc'];
  $avgBasket = $rc > 0 ? $sales / max(1,$rc) : 0.0;

  // Latest risk
  $p = $pdo->prepare("SELECT risk_percentage FROM churn_predictions WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
  $p->execute([$uid]);
  $row = $p->fetch(PDO::FETCH_ASSOC);
  $rp  = isset($row['risk_percentage']) ? (float)$row['risk_percentage'] : 0.0;
  if ($rp <= 1.0) $rp *= 100.0;
  $riskPct = min(100.0, max(0.0, $rp));

  // Simple revenue-save model:
  // assume 30% of predicted churn can be prevented by recs → saved revenue
  $preventable = 0.30;
  $revenueSaved = round($sales * ($riskPct/100.0) * $preventable, 2);

  // CLV impact: 30-day horizon, frequency proxy = rc (per day) * 30, margin proxy 25%
  $margin = 0.25;
  $clvImpact = round(($avgBasket * $rc * 30 * $margin) * ($riskPct/100.0) * $preventable, 2);


  // ROI: assume recs cost 3% of sales
  $cost = round($sales * 0.03, 2);
  $roi  = $cost > 0 ? round((($revenueSaved - $cost) / $cost) * 100.0, 2) : 0.0;

  json_ok([
    'revenueSaved' => $revenueSaved,
    'clvImpact'    => max(0,$clvImpact),
    'roi'          => $roi
  ]);
} catch (Throwable $e) {
  json_error('Revenue report error', 500, ['detail'=>$e->getMessage()]);
}
