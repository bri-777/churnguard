<?php
/* ULTIMATE ACCURATE SOLUTION - 100% Medium Risk Fix */
require __DIR__ . '/_bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    $uid = require_login();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

function safeFloat($value) { return (float)($value ?? 0); }
function safeInt($value) { return (int)($value ?? 0); }
function safeString($value) { return trim($value ?? ''); }

try {
    // STEP 1: Get ALL churn data first (14 days)
    $churnStmt = $pdo->prepare("
        SELECT 
            date,
            receipt_count,
            sales_volume,
            customer_traffic,
            morning_receipt_count,
            swing_receipt_count,
            graveyard_receipt_count,
            morning_sales_volume,
            swing_sales_volume,
            graveyard_sales_volume
        FROM churn_data 
        WHERE user_id = ? 
        AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        AND date <= CURDATE()
        ORDER BY date DESC
    ");
    $churnStmt->execute([$uid]);
    $churnRecords = $churnStmt->fetchAll(PDO::FETCH_ASSOC);

    // STEP 2: Get ALL predictions and map them properly
    $predStmt = $pdo->prepare("
        SELECT 
            id,
            for_date,
            risk_level,
            risk_percentage,
            description,
            created_at,
            level
        FROM churn_predictions 
        WHERE user_id = ? 
        AND for_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        AND for_date <= CURDATE()
        ORDER BY for_date DESC, created_at DESC
    ");
    $predStmt->execute([$uid]);
    $allPredictions = $predStmt->fetchAll(PDO::FETCH_ASSOC);

    // STEP 3: Create accurate prediction map (latest per date)
    $predictionMap = [];
    $predictionStats = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'total' => 0];
    
    foreach ($allPredictions as $pred) {
        $predDate = $pred['for_date'];
        
        // Only keep the latest prediction per date
        if (!isset($predictionMap[$predDate])) {
            // Clean and normalize the risk level
            $riskLevel = safeString($pred['risk_level']);
            $riskLevel = ucfirst(strtolower($riskLevel)); // Normalize: medium -> Medium
            
            // Use both risk_level and level columns (your schema has both)
            if (empty($riskLevel) && !empty($pred['level'])) {
                $riskLevel = safeString($pred['level']);
                $riskLevel = ucfirst(strtolower($riskLevel));
            }
            
            // Ensure valid risk level
            if (!in_array($riskLevel, ['Low', 'Medium', 'High'])) {
                $riskLevel = 'Low';
            }
            
            $predictionMap[$predDate] = [
                'id' => $pred['id'],
                'risk_level' => $riskLevel,
                'risk_percentage' => safeFloat($pred['risk_percentage']),
                'description' => safeString($pred['description']),
                'created_at' => $pred['created_at']
            ];
            
            $predictionStats[$riskLevel]++;
            $predictionStats['total']++;
        }
    }

    // STEP 4: Combine churn data with accurate predictions
    $finalData = [];
    
    foreach ($churnRecords as $churnRecord) {
        $recordDate = $churnRecord['date'];
        $combined = $churnRecord;
        
        // Add prediction data if exists
        if (isset($predictionMap[$recordDate])) {
            $prediction = $predictionMap[$recordDate];
            
            $combined['risk_level'] = $prediction['risk_level'];
            $combined['risk_percentage'] = $prediction['risk_percentage'];
            $combined['prediction_description'] = $prediction['description'];
            $combined['prediction_time'] = $prediction['created_at'];
            $combined['prediction_id'] = $prediction['id'];
            $combined['has_real_prediction'] = true;
            
            // Ensure percentage is in correct range (0-100)
            if ($combined['risk_percentage'] > 0 && $combined['risk_percentage'] <= 1) {
                $combined['risk_percentage'] *= 100;
            }
        } else {
            // No prediction - use intelligent fallback
            $traffic = safeInt($churnRecord['customer_traffic']);
            $revenue = safeFloat($churnRecord['sales_volume']);
            
            // Smart fallback logic based on business metrics
            if ($traffic < 200 && $revenue < 50000) {
                $combined['risk_level'] = 'High';
                $combined['risk_percentage'] = 75.0;
            } else if ($traffic < 250 && $revenue < 75000) {
                $combined['risk_level'] = 'Medium';
                $combined['risk_percentage'] = 50.0;
            } else {
                $combined['risk_level'] = 'Low';
                $combined['risk_percentage'] = 20.0;
            }
            
            $combined['prediction_description'] = 'Estimated based on metrics';
            $combined['prediction_time'] = null;
            $combined['prediction_id'] = null;
            $combined['has_real_prediction'] = false;
        }
        
        $finalData[] = $combined;
    }

    // STEP 5: Calculate metrics from accurate data
    $todayRecord = $finalData[0] ?? null;
    $yesterdayRecord = $finalData[1] ?? null;
    
    if (!$todayRecord) {
        echo json_encode([
            'success' => false,
            'message' => 'No current data available',
            'debug' => [
                'churn_records' => count($churnRecords),
                'predictions' => count($allPredictions),
                'prediction_stats' => $predictionStats
            ]
        ]);
        exit;
    }
    
    // Calculate 14-day averages (excluding today)
    $comparisonRecords = array_slice($finalData, 1);
    $totalCompareDays = count($comparisonRecords);
    
    $avgTraffic = $avgRevenue = $avgTransactions = 0;
    if ($totalCompareDays > 0) {
        foreach ($comparisonRecords as $record) {
            $avgTraffic += safeInt($record['customer_traffic']);
            $avgRevenue += safeFloat($record['sales_volume']);
            $avgTransactions += safeInt($record['receipt_count']);
        }
        $avgTraffic /= $totalCompareDays;
        $avgRevenue /= $totalCompareDays;
        $avgTransactions /= $totalCompareDays;
    }
    
    // Today's metrics
    $todayTraffic = safeInt($todayRecord['customer_traffic']);
    $todayRevenue = safeFloat($todayRecord['sales_volume']);
    $todayTransactions = safeInt($todayRecord['receipt_count']);
    $todayRiskLevel = $todayRecord['risk_level'];
    $todayRiskPercentage = safeFloat($todayRecord['risk_percentage']);
    
    $atRiskCustomers = $todayTraffic > 0 ? round($todayTraffic * ($todayRiskPercentage / 100)) : 0;
    
    // Calculate trends
    $trafficTrend = $avgTraffic > 0 ? round((($todayTraffic - $avgTraffic) / $avgTraffic) * 100, 2) : 0;
    $revenueTrend = $avgRevenue > 0 ? round((($todayRevenue - $avgRevenue) / $avgRevenue) * 100, 2) : 0;
    $transactionsTrend = $avgTransactions > 0 ? round((($todayTransactions - $avgTransactions) / $avgTransactions) * 100, 2) : 0;
    
    // STEP 6: Calculate final risk distribution
    $finalRiskStats = ['Low' => 0, 'Medium' => 0, 'High' => 0];
    foreach ($finalData as $record) {
        $risk = $record['risk_level'];
        $finalRiskStats[$risk]++;
    }
    
    // COMPREHENSIVE DEBUG LOGGING
    error_log("=== ULTIMATE ACCURACY DEBUG ===");
    error_log("User: $uid");
    error_log("Churn records found: " . count($churnRecords));
    error_log("Raw predictions found: " . count($allPredictions));
    error_log("Unique prediction dates: " . count($predictionMap));
    error_log("Prediction stats from DB: " . json_encode($predictionStats));
    error_log("Final combined records: " . count($finalData));
    error_log("Final risk distribution: " . json_encode($finalRiskStats));
    
    foreach ($finalData as $i => $record) {
        $source = $record['has_real_prediction'] ? 'REAL' : 'ESTIMATED';
        error_log("Record $i: {$record['date']} - {$record['risk_level']} ({$record['risk_percentage']}%) - $source");
    }
    
    // Build final response
    $response = [
        'success' => true,
        'todayTraffic' => $todayTraffic,
        'todayRevenue' => $todayRevenue,
        'todayTransactions' => $todayTransactions,
        'yesterdayTraffic' => $yesterdayRecord ? safeInt($yesterdayRecord['customer_traffic']) : 0,
        'traffic14DayAvg' => round($avgTraffic),
        'revenue14DayAvg' => round($avgRevenue, 2),
        'transactions14DayAvg' => round($avgTransactions),
        'trafficTrend' => $trafficTrend,
        'revenueTrend' => $revenueTrend,
        'transactionsTrend' => $transactionsTrend,
        'riskPercentage' => round($todayRiskPercentage, 1),
        'riskLevel' => $todayRiskLevel,
        'atRiskCustomers' => $atRiskCustomers,
        'overallHealthScore' => $todayRiskLevel === 'High' ? 'Critical' : ($todayRiskLevel === 'Medium' ? 'Warning' : 'Good'),
        'historicalData' => array_reverse($finalData), // Oldest to newest
        'hasData' => true,
        'dataDate' => $todayRecord['date'],
        'recordCount' => count($finalData),
        'lastUpdated' => date('Y-m-d H:i:s'),
        'accuracy_report' => [
            'raw_predictions_found' => count($allPredictions),
            'unique_prediction_dates' => count($predictionMap),
            'prediction_distribution' => $predictionStats,
            'final_risk_distribution' => $finalRiskStats,
            'data_quality' => $predictionStats['total'] > 0 ? 'HIGH' : 'ESTIMATED_ONLY',
            'medium_risk_count' => $finalRiskStats['Medium'],
            'has_medium_predictions' => $finalRiskStats['Medium'] > 0 ? 'YES' : 'NO'
        ]
    ];
    
    echo json_encode($response, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("CRITICAL ERROR in customer_monitoring.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'System error: ' . $e->getMessage(),
        'debug' => [
            'error_line' => $e->getLine(),
            'error_file' => basename($e->getFile()),
            'user_id' => $uid ?? 'unknown'
        ]
    ]);
}
?>