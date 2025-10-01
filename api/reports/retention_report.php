<?php
// api/reports/retention_report.php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
$uid = require_login();

// Fallback JSON helpers (if not provided by bootstrap)
if (!function_exists('json_ok')) {
  function json_ok(array $data=[]): void { header('Content-Type: application/json'); echo json_encode($data+['ok'=>true]); exit; }
}
if (!function_exists('json_error')) {
  function json_error(string $msg, int $code=400): void { http_response_code($code); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
}

try {
  // figure out the latest date we have data for (not always CURDATE)
  $stmt = $pdo->prepare("SELECT MAX(`date`) AS maxd FROM churn_data WHERE user_id=?");
  $stmt->execute([$uid]);
  $maxd = $stmt->fetchColumn();
  if (!$maxd) {
    // No history — derive from latest prediction
    $p = $pdo->prepare("SELECT risk_percentage, created_at, risk_level FROM churn_predictions WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $p->execute([$uid]);
    $pred = $p->fetch(PDO::FETCH_ASSOC) ?: ['risk_percentage'=>0,'created_at'=>date('Y-m-d H:i:s'),'risk_level'=>'Low'];
    $rp = (float)$pred['risk_percentage'];
    if ($rp <= 1.0) $rp *= 100.0;
    $ret = round(max(0.0, 100.0 - min(100.0,$rp)), 2);
    json_ok([
      'retentionRate'=>$ret,
      'churnRate'=>round(100.0-$ret,2),
      'atRiskCount'=>0,
      'retentionDeltaPts'=>null,
      'churnDeltaPts'=>null,
      'lastUpdated'=>$pred['created_at'],
      'prediction'=>$pred
    ]);
  }

  // build three 7-day windows ending at $maxd:
  // W0: [maxd-6, maxd]  (current)
  // W1: [maxd-13, maxd-7] (previous)
  // W2: [maxd-20, maxd-14] (pre-previous) for delta baseline
  $q = $pdo->prepare("
    SELECT `date`, COALESCE(receipt_count,0) rc, COALESCE(customer_traffic,0) ct
    FROM churn_data
    WHERE user_id=? AND `date` BETWEEN DATE_SUB(?, INTERVAL 20 DAY) AND ?
    ORDER BY `date` ASC
  ");
  $q->execute([$uid, $maxd, $maxd]);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $sum = fn($fromIdx,$toIdx,$arr) => array_reduce(
    array_slice($arr,$fromIdx,$toIdx-$fromIdx+1),
    fn($acc,$r)=>['rc'=>$acc['rc']+(int)$r['rc'], 'ct'=>$acc['ct']+(int)$r['ct']],
    ['rc'=>0,'ct'=>0]
  );

  // map rows to contiguous indices 0..N-1
  $N = count($rows);
  if ($N === 0) {
    // Same as no history case
    $p = $pdo->prepare("SELECT risk_percentage, created_at, risk_level FROM churn_predictions WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $p->execute([$uid]);
    $pred = $p->fetch(PDO::FETCH_ASSOC) ?: ['risk_percentage'=>0,'created_at'=>date('Y-m-d H:i:s'),'risk_level'=>'Low'];
    $rp = (float)$pred['risk_percentage']; if ($rp <= 1.0) $rp *= 100.0;
    $ret = round(max(0.0, 100.0 - min(100.0,$rp)), 2);
    json_ok(['retentionRate'=>$ret,'churnRate'=>round(100.0-$ret,2),'atRiskCount'=>0,'retentionDeltaPts'=>null,'churnDeltaPts'=>null,'lastUpdated'=>$pred['created_at'],'prediction'=>$pred]);
  }

  // last index corresponds to $maxd
  // ensure at least 21 days (else pad deltas as null)
  $have21 = $N >= 21;

  // compute window sums by counting from the end
  $idxEnd = $N - 1;
  $w0 = $sum(max(0,$idxEnd-6), $idxEnd, $rows);
  $w1 = $sum(max(0,$idxEnd-13), max(0,$idxEnd-7), $rows);
  $w2 = $have21 ? $sum(max(0,$idxEnd-20), max(0,$idxEnd-14), $rows) : ['rc'=>0,'ct'=>0];

  // "retention proxy": week-over-week receipts ratio
  $ret0 = ($w1['rc']>0) ? ($w0['rc']/$w1['rc'])*100.0 : null;
  // baseline previous retention (for delta)
  $ret1 = ($have21 && $w2['rc']>0) ? ($w1['rc']/$w2['rc'])*100.0 : null;

  // fallback to prediction when division by zero
  if ($ret0 === null) {
    $p = $pdo->prepare("SELECT risk_percentage, created_at, risk_level FROM churn_predictions WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $p->execute([$uid]);
    $pred = $p->fetch(PDO::FETCH_ASSOC) ?: ['risk_percentage'=>0,'created_at'=>date('Y-m-d H:i:s'),'risk_level'=>'Low'];
    $rp = (float)$pred['risk_percentage']; if ($rp <= 1.0) $rp *= 100.0;
    $ret0 = max(0.0, 100.0 - min(100.0,$rp));
  }

  $retentionRate = round($ret0, 2);
  $churnRate     = round(100.0 - $retentionRate, 2);
  $retentionDeltaPts = is_null($ret1) ? null : round($ret0 - $ret1, 2);
  $churnDeltaPts     = is_null($ret1) ? null : round((100.0-$ret0) - (100.0-$ret1), 2);

  // at-risk = latest day traffic * latest risk%
  $latestCt = (int)($rows[$idxEnd]['ct'] ?? 0);
  $p = $pdo->prepare("SELECT risk_percentage, created_at, risk_level FROM churn_predictions WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
  $p->execute([$uid]);
  $pred = $p->fetch(PDO::FETCH_ASSOC) ?: ['risk_percentage'=>0,'created_at'=>date('Y-m-d H:i:s'),'risk_level'=>'Low'];
  $rp = (float)$pred['risk_percentage']; if ($rp <= 1.0) $rp *= 100.0;
  $atRisk = (int)round($latestCt * ($rp/100.0));

  json_ok([
    'retentionRate'=>$retentionRate,
    'churnRate'=>$churnRate,
    'atRiskCount'=>$atRisk,
    'retentionDeltaPts'=>$retentionDeltaPts,
    'churnDeltaPts'=>$churnDeltaPts,
    'lastUpdated'=>$maxd . ' 23:59:59',
    'prediction'=>$pred
  ]);

} catch (Throwable $e) {
  json_error('Retention report error: '.$e->getMessage(), 500);
}
