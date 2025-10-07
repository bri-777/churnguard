<?php
// api/churn_risk.php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
$uid = require_login();

// ✅ Always force Manila timezone (fixes Hostinger vs XAMPP mismatch)
date_default_timezone_set('Asia/Manila');

// ✅ Unified date variables
$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$manilaDate = $now->format('Y-m-d H:i:s');
$forDate = $now->format('Y-m-d');

try {
    // Load model safely
    $modelPath = __DIR__ . '/model.json';
    $usedFallback = false;

    if (!file_exists($modelPath)) {
        throw new RuntimeException("Model file missing: $modelPath");
    }

    // Load predictor
    $pred = XGBPredictor::loadFrom($modelPath);
    $prob = round($pred->predict_proba($feat), 6); // normalize float precision
    $modelConfidence = 0.95;

} catch (Throwable $mx) {
    // Fallback heuristic model
    $usedFallback = true;
    $modelConfidence = 0.6;
    $riskScore = 0.0;

    // Transaction risk (40% weight)
    if ($tdp > 0) $riskScore += ($tdp / 100.0) * 0.40;
    // Sales risk (35% weight)
    if ($sdp > 0) $riskScore += ($sdp / 100.0) * 0.35;
    // Traffic risk (15% weight)
    if ($t_drop > 0) $riskScore += ($t_drop / 100.0) * 0.15;
    // Operational risk (10% weight)
    if ($imbalance > 0) $riskScore += min(1.0, $imbalance / 50.0) * 0.10;

    // Critical factor multiplier
    if ($factorAnalysis['critical_count'] > 0) {
        $riskScore *= (1.0 + ($factorAnalysis['critical_count'] * 0.2));
    }

    // Low activity penalty
    if ($rc < 5 && $sales < 500) $riskScore += 0.15;

    // Zero conversion penalty
    if ($ct > 0 && $rc == 0) $riskScore += 0.25;

    $prob = max(0.0, min(1.0, $riskScore));
}

// ✅ Calculate risk level and description
$riskPct = round($prob * 100.0, 2);
$level = risk_level_from_pct($riskPct);

$criticalCount = $factorAnalysis['critical_count'];
$warningCount = $factorAnalysis['warning_count'];
$positiveCount = $factorAnalysis['positive_count'];

$desc = match ($level) {
    'High' => $criticalCount > 2
        ? 'URGENT: Multiple critical issues detected. Immediate intervention required to prevent customer loss.'
        : ($criticalCount > 0
            ? 'High churn risk with critical performance issues. Implement retention strategies immediately.'
            : 'High churn risk identified. Review operations and engage customer retention tactics now.'),
    'Medium' => $warningCount > 2
        ? 'Moderate risk with multiple warning indicators. Monitor closely and prepare intervention strategies.'
        : ($warningCount > 0
            ? 'Moderate churn risk detected. Address performance issues and watch trends closely.'
            : 'Moderate churn risk. Maintain vigilance and consider proactive customer engagement.'),
    default => ($rc == 0 && $sales == 0)
        ? 'New business profile. Add transaction data for accurate churn prediction and insights.'
        : ($positiveCount > 0
            ? 'Low churn risk with positive performance indicators. Maintain current successful strategies.'
            : 'Low churn risk detected. Continue monitoring metrics for early warning signs.')
};

if ($modelConfidence < 0.5) {
    $desc .= ' (Limited data - add more transaction history for improved accuracy)';
} elseif ($usedFallback && $modelConfidence < 0.8) {
    $desc .= ' (Using heuristic analysis - model optimization recommended)';
}

// ✅ Database operations (always remove today’s previous prediction first)
$pdo->beginTransaction();

try {
    $deletePrev = $pdo->prepare("
        DELETE FROM churn_predictions 
        WHERE user_id = ? AND for_date = ?
    ");
    $deletePrev->execute([$uid, $forDate]);

    $insert = $pdo->prepare("
        INSERT INTO churn_predictions
            (user_id, date, risk_score, risk_level, factors, description, created_at, level, risk_percentage, for_date)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insert->execute([
        $uid,
        $manilaDate,
        round($riskPct / 100.0, 4),
        $level,
        json_encode($factorAnalysis['factors'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $desc,
        $manilaDate,
        $level,
        round($riskPct, 3),
        $forDate
    ]);

    $pdo->commit();
} catch (Throwable $dbErr) {
    $pdo->rollBack();
    j_err('Database error: ' . $dbErr->getMessage(), 500);
}

j_ok([
    'saved'             => true,
    'has'               => true,
    'for_date'          => $forDate,
    'risk_percentage'   => $riskPct,
    'risk_score'        => round($riskPct / 100.0, 4),
    'risk_level'        => $level,
    'level'             => $level,
    'description'       => $desc,
    'factors'           => $factorAnalysis['factors'],
    'is_new_user'       => ($rc == 0 && $sales == 0),
    'data_available'    => ($rc > 0 || $sales > 0 || $ct > 0),
    'model_confidence'  => $modelConfidence,
    'analysis_quality'  => $roll['days'] >= 7 ? 'high' : ($roll['days'] >= 3 ? 'medium' : 'low'),
    'server_timezone'   => date_default_timezone_get(), // ✅ helps you verify Hostinger’s timezone
    'timestamp'         => $manilaDate
]);

exit;

} catch (Throwable $e) {
    j_err('Prediction run failed', 500, ['detail' => $e->getMessage()]);
}
exit;

j_err('Unknown action', 400);
