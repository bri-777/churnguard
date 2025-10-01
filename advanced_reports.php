<?php
// advanced_reports.php - Enhanced reporting endpoints

session_start();
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// DB Configuration
$db_host = 'localhost';
$db_name = 'churnguard';
$db_user = 'root';
$db_pass = '';

try {
    // Authentication check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    $report_type = $_GET['report'] ?? 'predictions';
    
    // Database connection
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    $response = [];
    
    switch ($report_type) {
        case 'predictions':
            $response = getPredictiveAnalytics($pdo, $user_id);
            break;
        case 'segmentation':
            $response = getSegmentationAnalysis($pdo, $user_id);
            break;
        case 'cohort':
            $response = getCohortAnalysis($pdo, $user_id);
            break;
        case 'benchmarks':
            $response = getPerformanceBenchmarks($pdo, $user_id);
            break;
        case 'insights':
            $response = getActionableInsights($pdo, $user_id);
            break;
        default:
            $response = ['status' => 'error', 'message' => 'Invalid report type'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Advanced Reports Error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}

function getPredictiveAnalytics($pdo, $user_id) {
    $today = date('Y-m-d');
    
    // 7-day forecast based on recent predictions
    $stmt = $pdo->prepare("
        SELECT 
            AVG(risk_percentage) as avg_risk_7d,
            MAX(risk_percentage) as max_risk_7d,
            MIN(risk_percentage) as min_risk_7d,
            COUNT(DISTINCT for_date) as prediction_days
        FROM churn_predictions
        WHERE user_id = :user_id 
        AND for_date BETWEEN DATE_SUB(:today, INTERVAL 7 DAY) AND :today2
    ");
    $stmt->execute([':user_id' => $user_id, ':today' => $today, ':today2' => $today]);
    $forecast = $stmt->fetch();
    
    // Calculate CLV
    $stmt = $pdo->prepare("
        SELECT 
            AVG(sales_volume) as avg_daily_revenue,
            COUNT(DISTINCT date) as active_days,
            AVG(receipt_count) as avg_transactions
        FROM churn_data
        WHERE user_id = :user_id
        AND date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ");
    $stmt->execute([':user_id' => $user_id]);
    $clv_data = $stmt->fetch();
    
    $avg_daily = (float)($clv_data['avg_daily_revenue'] ?? 0);
    $retention_multiplier = 365; // Assumed 1-year retention
    $clv = $avg_daily * $retention_multiplier;
    
    // Risk trend analysis
    $stmt = $pdo->prepare("
        SELECT 
            risk_percentage,
            for_date
        FROM churn_predictions
        WHERE user_id = :user_id
        AND for_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY for_date ASC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $risk_history = $stmt->fetchAll();
    
    $trend_direction = 'stable';
    $trend_percentage = 0;
    
    if (count($risk_history) >= 2) {
        $first_half = array_slice($risk_history, 0, floor(count($risk_history)/2));
        $second_half = array_slice($risk_history, floor(count($risk_history)/2));
        
        $first_avg = array_sum(array_column($first_half, 'risk_percentage')) / count($first_half);
        $second_avg = array_sum(array_column($second_half, 'risk_percentage')) / count($second_half);
        
        $trend_percentage = $second_avg - $first_avg;
        $trend_direction = $trend_percentage > 5 ? 'increasing' : ($trend_percentage < -5 ? 'decreasing' : 'stable');
    }
    
    return [
        'status' => 'success',
        'predictions' => [
            'forecast_7d' => round((float)($forecast['avg_risk_7d'] ?? 0), 1),
            'forecast_range' => [
                'min' => round((float)($forecast['min_risk_7d'] ?? 0), 1),
                'max' => round((float)($forecast['max_risk_7d'] ?? 0), 1)
            ],
            'clv' => round($clv, 2),
            'clv_breakdown' => [
                'daily_avg' => round($avg_daily, 2),
                'active_days' => (int)($clv_data['active_days'] ?? 0),
                'avg_transactions' => round((float)($clv_data['avg_transactions'] ?? 0), 1)
            ],
            'risk_trend' => [
                'direction' => $trend_direction,
                'percentage' => round($trend_percentage, 1),
                'history' => $risk_history
            ]
        ]
    ];
}

function getSegmentationAnalysis($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            risk_level,
            COUNT(DISTINCT date) as days_in_segment,
            AVG(sales_volume) as avg_revenue,
            AVG(receipt_count) as avg_receipts,
            AVG(customer_traffic) as avg_traffic,
            SUM(sales_volume) as total_revenue,
            AVG(risk_percentage) as avg_risk_score
        FROM churn_data
        WHERE user_id = :user_id
        AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY risk_level
    ");
    $stmt->execute([':user_id' => $user_id]);
    $segments = $stmt->fetchAll();
    
    $processed_segments = [];
    foreach ($segments as $segment) {
        $customer_count = (int)$segment['days_in_segment'] * 150; // As per original logic
        $aov = $segment['avg_receipts'] > 0 
            ? $segment['avg_revenue'] / $segment['avg_receipts'] 
            : 0;
            
        $processed_segments[] = [
            'level' => $segment['risk_level'],
            'customers' => $customer_count,
            'revenue' => round((float)$segment['total_revenue'], 2),
            'avg_daily_revenue' => round((float)$segment['avg_revenue'], 2),
            'aov' => round($aov, 2),
            'avg_risk_score' => round((float)$segment['avg_risk_score'], 1),
            'days' => (int)$segment['days_in_segment']
        ];
    }
    
    return [
        'status' => 'success',
        'segments' => $processed_segments
    ];
}

function getCohortAnalysis($pdo, $user_id) {
    // Weekly cohort retention analysis
    $stmt = $pdo->prepare("
        SELECT 
            YEARWEEK(date, 1) as cohort_week,
            COUNT(DISTINCT date) as cohort_days,
            AVG(CASE WHEN risk_level = 'Low' THEN 100 ELSE 0 END) as retention_rate,
            AVG(sales_volume) as avg_revenue,
            AVG(customer_traffic) as avg_traffic
        FROM churn_data
        WHERE user_id = :user_id
        AND date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
        GROUP BY YEARWEEK(date, 1)
        ORDER BY cohort_week ASC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $cohorts = $stmt->fetchAll();
    
    return [
        'status' => 'success',
        'cohorts' => $cohorts
    ];
}

function getPerformanceBenchmarks($pdo, $user_id) {
    // Calculate various performance metrics
    $stmt = $pdo->prepare("
        SELECT 
            AVG(sales_volume) as avg_sales,
            MAX(sales_volume) as max_sales,
            MIN(sales_volume) as min_sales,
            AVG(receipt_count) as avg_receipts,
            AVG(customer_traffic) as avg_traffic,
            AVG(CASE WHEN risk_level = 'Low' THEN 100 
                     WHEN risk_level = 'Medium' THEN 50 
                     ELSE 0 END) as retention_score,
            (AVG(sales_volume) / NULLIF(AVG(customer_traffic), 0)) as conversion_rate
        FROM churn_data
        WHERE user_id = :user_id
        AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([':user_id' => $user_id]);
    $metrics = $stmt->fetch();
    
    // Peak vs off-peak analysis
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN morning_sales_volume >= swing_sales_volume 
                 AND morning_sales_volume >= graveyard_sales_volume THEN 'Morning'
                WHEN swing_sales_volume >= graveyard_sales_volume THEN 'Swing'
                ELSE 'Graveyard'
            END as peak_shift,
            date,
            morning_sales_volume,
            swing_sales_volume,
            graveyard_sales_volume
        FROM churn_data
        WHERE user_id = :user_id
        AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY date DESC
    ");
    $stmt->execute([':user_id' => $user_id]);
    $peak_data = $stmt->fetchAll();
    
    // Calculate benchmark scores
    $sales_efficiency = min(100, ($metrics['avg_sales'] / max(1, $metrics['max_sales'])) * 100);
    $customer_satisfaction = (float)($metrics['retention_score'] ?? 0);
    $retention_performance = 100 - ((float)($metrics['avg_receipts'] ?? 0) > 200 ? 20 : 0);
    
    return [
        'status' => 'success',
        'benchmarks' => [
            'sales_efficiency' => round($sales_efficiency, 1),
            'customer_satisfaction' => round($customer_satisfaction, 1),
            'retention_performance' => round($retention_performance, 1),
            'conversion_rate' => round((float)($metrics['conversion_rate'] ?? 0) * 100, 1)
        ],
        'peak_analysis' => $peak_data,
        'metrics' => $metrics
    ];
}

function getActionableInsights($pdo, $user_id) {
    $insights = [];
    $actions = [];
    
    // Get recent performance data
    $stmt = $pdo->prepare("
        SELECT 
            AVG(risk_percentage) as avg_risk,
            MAX(risk_percentage) as max_risk,
            COUNT(CASE WHEN risk_level = 'High' THEN 1 END) as high_risk_days,
            AVG(sales_volume) as avg_sales,
            AVG(customer_traffic) as avg_traffic
        FROM churn_data
        WHERE user_id = :user_id
        AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([':user_id' => $user_id]);
    $recent = $stmt->fetch();
    
    // Generate insights based on data
    if ((float)($recent['avg_risk'] ?? 0) > 60) {
        $insights[] = [
            'type' => 'critical',
            'title' => 'High Churn Risk Alert',
            'description' => 'Average risk score above 60%. Immediate intervention recommended.',
            'priority' => 1
        ];
        $actions[] = [
            'action' => 'Launch retention campaign',
            'urgency' => 'immediate',
            'impact' => 'high'
        ];
    }
    
    if ((int)($recent['high_risk_days'] ?? 0) > 2) {
        $insights[] = [
            'type' => 'warning',
            'title' => 'Multiple High-Risk Days',
            'description' => sprintf('%d high-risk days in the past week', $recent['high_risk_days']),
            'priority' => 2
        ];
        $actions[] = [
            'action' => 'Review customer feedback',
            'urgency' => 'high',
            'impact' => 'medium'
        ];
    }
    
    if ((float)($recent['avg_traffic'] ?? 0) < 200) {
        $insights[] = [
            'type' => 'info',
            'title' => 'Low Traffic Pattern',
            'description' => 'Customer traffic below optimal levels. Consider promotional activities.',
            'priority' => 3
        ];
        $actions[] = [
            'action' => 'Implement traffic-driving promotions',
            'urgency' => 'medium',
            'impact' => 'medium'
        ];
    }
    
    return [
        'status' => 'success',
        'insights' => $insights,
        'automated_actions' => $actions,
        'summary' => $recent
    ];
}
?>