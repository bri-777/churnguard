<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
$uid = require_login();

header('Content-Type: application/json; charset=utf-8');

// last 14 days window based on for_date
$tz = new DateTimeZone('Asia/Manila');
$today = (new DateTime('now', $tz))->format('Y-m-d');
$start = (new DateTime('now', $tz))->modify('-13 days')->format('Y-m-d');

// Count Low, Medium, High from saved predictions
// prefer risk_level, fallback to level
$sql = "
  SELECT
    CASE
      WHEN (COALESCE(risk_level, level) = 'Low') THEN 'Low'
      WHEN (COALESCE(risk_level, level) = 'Medium') THEN 'Medium'
      WHEN (COALESCE(risk_level, level) = 'High') THEN 'High'
      ELSE 'Unknown'
    END AS grp,
    COUNT(*) AS c
  FROM churn_predictions
  WHERE user_id = :uid
    AND for_date BETWEEN :start AND :end
  GROUP BY grp
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':uid'=>$uid, ':start'=>$start, ':end'=>$today]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$low = 0; $medium = 0; $high = 0;
foreach ($rows as $r) {
  if ($r['grp'] === 'Low') $low = (int)$r['c'];
  elseif ($r['grp'] === 'Medium') $medium = (int)$r['c'];
  elseif ($r['grp'] === 'High') $high = (int)$r['c'];
}

// Average risk score over the same window
$stmt2 = $pdo->prepare("
  SELECT AVG(risk_score) AS avgRisk
  FROM churn_predictions
  WHERE user_id = :uid
    AND for_date BETWEEN :start AND :end
");
$stmt2->execute([':uid'=>$uid, ':start'=>$start, ':end'=>$today]);
$avgRisk = (float)($stmt2->fetchColumn() ?: 0);

// Latest prediction detail for context
$stmt3 = $pdo->prepare("
  SELECT risk_score, risk_percentage, COALESCE(risk_level, level) AS level, description, factors, for_date, created_at
  FROM churn_predictions
  WHERE user_id = :uid
  ORDER BY created_at DESC
  LIMIT 1
");
$stmt3->execute([':uid'=>$uid]);
$latest = $stmt3->fetch(PDO::FETCH_ASSOC) ?: null;

$riskDescription = 'Churn risk distribution for last 14 days';
$predictions = [];
if ($latest) {
  $riskDescription = $latest['description'] ?: $riskDescription;
  $factors = [];
  if (!empty($latest['factors'])) {
    $dec = json_decode($latest['factors'], true);
    if (is_array($dec)) $factors = $dec;
  }
  $predictions[] = [
    'risk_score' => (float)($latest['risk_score'] ?? 0),
    'risk_percentage' => (float)($latest['risk_percentage'] ?? 0),
    'risk_level' => (string)($latest['level'] ?? ''),
    'description' => (string)$riskDescription,
    'factors' => $factors,
    'for_date' => (string)($latest['for_date'] ?? ''),
    'created_at' => (string)($latest['created_at'] ?? '')
  ];
}

echo json_encode([
  'period' => '14d',
  'low' => $low,
  'medium' => $medium,
  'high' => $high,
  'avgRiskScore' => round($avgRisk, 4),
  'riskDescription' => $riskDescription,
  'predictions' => $predictions
], JSON_UNESCAPED_UNICODE);
