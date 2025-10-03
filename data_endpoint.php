<?php
// data_endpoint.php — ChurnGuard data API (Manila TZ, prediction shown; metrics from churn_data)

// --- DB credentials ---
$db_host = 'localhost';
$db_name = 'churnguard';
$db_user = 'root';
$db_pass = '';

// --- App / headers ---
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // --- AUTHENTICATION CHECK - GET CURRENT USER ID ---
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','error'=>'unauthorized','message'=>'User not authenticated']);
        exit;
    }
    
    $current_user_id = (int)$_SESSION['user_id'];
    
    // --- Validate view ---
    $view = $_GET['view'] ?? '14days';
    $valid_views = ['today','yesterday','7days','14days','30days'];
    if (!in_array($view, $valid_views, true)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','error'=>'bad_param','message'=>'Invalid view']);
        exit;
    }

    // --- DB ---
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // --- FIX: Get the last available data date instead of using current date ---
    $stmt = $pdo->prepare("
        SELECT MAX(date) AS last_date 
        FROM churn_data 
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $current_user_id]);
    $result = $stmt->fetch();
    $last_data_date = $result['last_date'] ?? date('Y-m-d');
    
    // Use the last data date as "today" for calculations
    $today = $last_data_date;
    $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));

    // --- Dates based on last available data ---
    switch ($view) {
        case 'today':
            $start_date = $today;
            $end_date   = $today;
            $compare_start = $yesterday;
            $compare_end   = $yesterday;
            $days_count = 1;
            break;
        case 'yesterday':
            $start_date = $yesterday;
            $end_date   = $yesterday;
            $compare_start = date('Y-m-d', strtotime($today . ' -2 day'));
            $compare_end   = date('Y-m-d', strtotime($today . ' -2 day'));
            $days_count = 1;
            break;
        case '7days':
            $start_date = date('Y-m-d', strtotime($today . ' -6 day'));
            $end_date   = $today;
            $compare_start = date('Y-m-d', strtotime($today . ' -13 day'));
            $compare_end   = date('Y-m-d', strtotime($today . ' -7 day'));
            $days_count = 7;
            break;
        case '14days':
            $start_date = date('Y-m-d', strtotime($today . ' -13 day'));
            $end_date   = $today;
            $compare_start = date('Y-m-d', strtotime($today . ' -27 day'));
            $compare_end   = date('Y-m-d', strtotime($today . ' -14 day'));
            $days_count = 14;
            break;
        case '30days':
        default:
            $start_date = date('Y-m-d', strtotime($today . ' -29 day'));
            $end_date   = $today;
            $compare_start = date('Y-m-d', strtotime($today . ' -59 day'));
            $compare_end   = date('Y-m-d', strtotime($today . ' -30 day'));
            $days_count = 30;
            break;
    }

    // --- Latest prediction for the "Current Risk Level" ---
    $stmt = $pdo->prepare("
        SELECT risk_level, risk_percentage, COALESCE(description,'') AS description
        FROM churn_predictions
        WHERE user_id = :user_id AND for_date = :d
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $current_user_id, ':d' => $today]);
    $latest_risk = $stmt->fetch();

    if (!$latest_risk) {
        $stmt = $pdo->prepare("
            SELECT risk_level, risk_percentage, COALESCE(description,'') AS description
            FROM churn_predictions
            WHERE user_id = :user_id
            ORDER BY for_date DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $current_user_id]);
        $latest_risk = $stmt->fetch();
    }
    if (!$latest_risk) {
        $latest_risk = [
            'risk_level' => 'Low',
            'risk_percentage' => 0,
            'description' => 'No prediction data available'
        ];
    }

    // --- Current window aggregate - using churn_predictions for risk levels ---
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT cd.date) AS days_with_data,
            COUNT(DISTINCT CASE WHEN cp.risk_level='High'   THEN cd.date END) AS high_risk_days,
            COUNT(DISTINCT CASE WHEN cp.risk_level='Medium' THEN cd.date END) AS medium_risk_days,
            COUNT(DISTINCT CASE WHEN cp.risk_level='Low'    THEN cd.date END) AS low_risk_days,
            COALESCE(AVG(cd.sales_volume),0) AS avg_sales,
            COALESCE(SUM(cd.sales_volume),0) AS total_sales,
            COALESCE(AVG(cd.receipt_count),0) AS avg_receipts,
            COALESCE(AVG(cd.customer_traffic),0) AS avg_traffic,
            COALESCE(MAX(CASE WHEN cd.date = :today THEN cd.sales_volume END),0) AS today_sales,
            COALESCE(MAX(CASE WHEN cd.date = :today THEN cd.customer_traffic END),0) AS today_traffic,
            COALESCE(MAX(CASE WHEN cd.date = :yday THEN cd.sales_volume END),0) AS yesterday_sales,
            COALESCE(MAX(CASE WHEN cd.date = :yday THEN cd.customer_traffic END),0) AS yesterday_traffic
        FROM churn_data cd
        LEFT JOIN churn_predictions cp ON cp.user_id = cd.user_id AND cp.for_date = cd.date
        WHERE cd.user_id = :user_id AND cd.date BETWEEN :s AND :e
    ");
    $stmt->execute([
        ':user_id' => $current_user_id,
        ':s'=>$start_date, ':e'=>$end_date, ':today'=>$today, ':yday'=>$yesterday
    ]);
    $current = $stmt->fetch();

    // --- Compare window ---
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT cd.date) AS days_with_data,
            COALESCE(SUM(cd.sales_volume),0) AS total_sales,
            COALESCE(AVG(cd.sales_volume),0) AS avg_sales,
            COALESCE(AVG(cd.receipt_count),0) AS avg_receipts,
            COUNT(DISTINCT CASE WHEN cp.risk_level='High' THEN cd.date END) AS high_risk_days
        FROM churn_data cd
        LEFT JOIN churn_predictions cp ON cp.user_id = cd.user_id AND cp.for_date = cd.date
        WHERE cd.user_id = :user_id AND cd.date BETWEEN :s AND :e
    ");
    $stmt->execute([':user_id' => $current_user_id, ':s'=>$compare_start, ':e'=>$compare_end]);
    $compare = $stmt->fetch();

    // --- Rolling baselines (7d/30d) ---
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(sales_volume),0) AS avg_sales_7,
               COALESCE(AVG(customer_traffic),0) AS avg_traf_7
        FROM churn_data
        WHERE user_id = :user_id AND date BETWEEN DATE_SUB(:t, INTERVAL 6 DAY) AND :t
    ");
    $stmt->execute([':user_id' => $current_user_id, ':t'=>$today]);
    $b7 = $stmt->fetch();

    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(sales_volume),0) AS avg_sales_30,
               COALESCE(AVG(customer_traffic),0) AS avg_traf_30
        FROM churn_data
        WHERE user_id = :user_id AND date BETWEEN DATE_SUB(:t, INTERVAL 29 DAY) AND :t
    ");
    $stmt->execute([':user_id' => $current_user_id, ':t'=>$today]);
    $b30 = $stmt->fetch();

    // === Executive Summary metrics ===
    $days_with_data     = (int)($current['days_with_data'] ?? 0);
    $high_days_current  = (int)($current['high_risk_days'] ?? 0);
    $high_days_compare  = (int)($compare['high_risk_days'] ?? 0);

    // At-risk customers = high-risk DAYS × 150
    $at_risk_count = $high_days_current * 150;

    // WoW change based on days
    $at_risk_change = ($high_days_compare > 0)
        ? (($at_risk_count - ($high_days_compare * 150)) / ($high_days_compare * 150) * 100.0)
        : 0.0;

    // Revenue at risk = avg daily revenue × high-risk days
    $avg_rev_for_window = ($days_with_data > 0)
        ? ((float)$current['total_sales'] / $days_with_data)
        : 0.0;
    $revenue_at_risk = $avg_rev_for_window * $high_days_current;

    // Churn / retention from churn_data days
    $churn_rate = ($days_with_data > 0)
        ? ($high_days_current / $days_with_data * 100.0)
        : 0.0;
    $churn_rate = min(100.0, max(0.0, $churn_rate));
    $retention_rate = 100.0 - $churn_rate;

    // Retention change vs compare window
    $days_with_data_cmp = (int)($compare['days_with_data'] ?? 0);
    $compare_churn = ($days_with_data_cmp > 0)
        ? ($high_days_compare / $days_with_data_cmp * 100.0)
        : 0.0;
    $compare_churn = min(100.0, max(0.0, $compare_churn));
    $compare_retention = 100.0 - $compare_churn;
    $retention_change = $retention_rate - $compare_retention;

    // Clamp / round
    $at_risk_count     = (int)$at_risk_count;
    $at_risk_change    = round((float)$at_risk_change, 1);
    $revenue_at_risk   = round((float)$revenue_at_risk, 2);
    $churn_rate        = round((float)$churn_rate, 1);
    $retention_rate    = round((float)$retention_rate, 1);
    $retention_change  = round((float)$retention_change, 1);

    // Yesterday risk
    $stmt = $pdo->prepare("
        SELECT risk_percentage
        FROM churn_predictions
        WHERE user_id = :user_id AND for_date = :d
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([':user_id' => $current_user_id, ':d'=>$yesterday]);
    $yr = $stmt->fetch();
    $yesterday_risk = $yr ? round((float)$yr['risk_percentage'], 1) : 0.0;

    // === Trends with gap filling - using churn_predictions ---
    $stmt = $pdo->prepare("
        SELECT 
            d.date AS date,
            COALESCE(cp.risk_percentage, 0)  AS risk_percentage,
            COALESCE(cd.sales_volume, 0)     AS sales_volume,
            COALESCE(cd.receipt_count, 0)    AS receipt_count,
            COALESCE(cd.customer_traffic, 0) AS customer_traffic,
            CASE WHEN cd.date IS NULL THEN 1 ELSE 0 END AS is_gap
        FROM (
            SELECT DATE_SUB(:end_date, INTERVAL n DAY) AS date
            FROM (
                SELECT 0 n UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 
                UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
                UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14
                UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
                UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
                UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29
                UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32 UNION ALL SELECT 33 UNION ALL SELECT 34
                UNION ALL SELECT 35 UNION ALL SELECT 36 UNION ALL SELECT 37 UNION ALL SELECT 38 UNION ALL SELECT 39
                UNION ALL SELECT 40 UNION ALL SELECT 41 UNION ALL SELECT 42 UNION ALL SELECT 43 UNION ALL SELECT 44
                UNION ALL SELECT 45 UNION ALL SELECT 46 UNION ALL SELECT 47 UNION ALL SELECT 48 UNION ALL SELECT 49
                UNION ALL SELECT 50 UNION ALL SELECT 51 UNION ALL SELECT 52 UNION ALL SELECT 53 UNION ALL SELECT 54
                UNION ALL SELECT 55 UNION ALL SELECT 56 UNION ALL SELECT 57 UNION ALL SELECT 58 UNION ALL SELECT 59
            ) numbers
            WHERE n < :days
        ) d
        LEFT JOIN churn_data cd 
               ON cd.user_id = :user_id AND cd.date = d.date
        LEFT JOIN churn_predictions cp
               ON cp.user_id = :user_id AND cp.for_date = d.date
        ORDER BY d.date ASC
    ");
    $stmt->execute([':user_id' => $current_user_id, ':end_date'=>$end_date, ':days'=>$days_count]);
    $trends = $stmt->fetchAll();

    // === Period comparison payload ===
    $period_comparison = [
        'today' => [
            'revenue'    => (float)$current['today_sales'],
            'customers'  => (int)$current['today_traffic'],
            'risk_score' => round((float)$latest_risk['risk_percentage'], 1)
        ],
        'yesterday' => [
            'revenue'    => (float)$current['yesterday_sales'],
            'customers'  => (int)$current['yesterday_traffic'],
            'risk_score' => $yesterday_risk
        ],
        'avg_7day' => [
            'revenue'    => round((float)$b7['avg_sales_7'], 2),
            'customers'  => (int)round($b7['avg_traf_7'] ?? 0),
            'risk_score' => $churn_rate
        ],
        'avg_30day' => [
            'revenue'    => round((float)$b30['avg_sales_30'], 2),
            'customers'  => (int)round($b30['avg_traf_30'] ?? 0),
            'risk_score' => $churn_rate
        ]
    ];

    // === Segments (using predictions for accurate risk levels) ===
    $stmt = $pdo->prepare("
        SELECT 
            cp.risk_level,
            COUNT(DISTINCT cd.date) AS days,
            COALESCE(AVG(cp.risk_percentage),0) AS avg_risk_score,
            COALESCE(SUM(cd.sales_volume),0) AS total_revenue
        FROM churn_data cd
        LEFT JOIN churn_predictions cp ON cp.user_id = cd.user_id AND cp.for_date = cd.date
        WHERE cd.user_id = :user_id AND cd.date BETWEEN :s AND :e AND cp.risk_level IS NOT NULL
        GROUP BY cp.risk_level
    ");
    $stmt->execute([':user_id' => $current_user_id, ':s'=>$start_date, ':e'=>$end_date]);
    $segments_raw = $stmt->fetchAll();

    $segments = [
        'High'   => ['count'=>0,'revenue'=>0.0,'score'=>0.0],
        'Medium' => ['count'=>0,'revenue'=>0.0,'score'=>0.0],
        'Low'    => ['count'=>0,'revenue'=>0.0,'score'=>0.0],
    ];
    foreach ($segments_raw as $seg) {
        $lvl = $seg['risk_level'];
        if (isset($segments[$lvl])) {
            $segments[$lvl]['count']   = (int)$seg['days'] * 150;
            $segments[$lvl]['revenue'] = (float)$seg['total_revenue'];
            $segments[$lvl]['score']   = (float)$seg['avg_risk_score'];
        }
    }

    // === Response ===
    $response = [
        'status'        => 'success',
        'timestamp'     => date('Y-m-d H:i:s'),
        'last_updated'  => date('M d, Y h:i A'),
        'view'          => $view,
        'user_id'       => $current_user_id,
        'last_data_date' => $today, // Added for debugging
        'data_availability' => [
            'has_data'        => ((int)$current['days_with_data']) > 0,
            'days_with_data'  => (int)$current['days_with_data'],
            'total_days'      => (int)$days_count,
            'coverage_percent'=> $days_count > 0 ? round(((int)$current['days_with_data']) / $days_count * 100, 1) : 0
        ],
        'executive_summary' => [
            'risk_level'       => $latest_risk['risk_level'],
            'risk_percentage'  => round((float)$latest_risk['risk_percentage'], 1),
            'risk_description' => $latest_risk['description'],
            'at_risk_customers'=> $at_risk_count,
            'at_risk_change'   => $at_risk_change,
            'revenue_at_risk'  => $revenue_at_risk,
            'revenue_change'   => ($compare['total_sales'] > 0)
                ? round(((float)$current['total_sales'] - (float)$compare['total_sales']) / (float)$compare['total_sales'] * 100, 1)
                : 0.0,
            'retention_rate'   => $retention_rate,
            'retention_change' => $retention_change
        ],
        'retention_metrics' => [
            'current_retention' => $retention_rate,
            'churn_rate'        => $churn_rate,
            'wow_change'        => $at_risk_change,
            'high_risk_count'   => (int)$segments['High']['count'],
            'medium_risk_count' => (int)$segments['Medium']['count']
        ],
        'behavior_metrics' => [
            'avg_frequency'    => round((float)$current['avg_receipts'], 0),
            'avg_value'        => ($current['avg_receipts'] > 0)
                ? round(((float)$current['avg_sales'] / (float)$current['avg_receipts']), 2)
                : 0.0,
            'loyalty_rate'     => round($retention_rate * 0.85, 1),
            'engagement_score' => round(min(100, ((float)$current['avg_traffic']) / 10.0), 0)
        ],
        'revenue_impact' => [
            'potential_loss' => $revenue_at_risk,
            'revenue_saved'  => round($revenue_at_risk * 0.3, 2),
            'lifetime_value' => round(((float)$current['avg_sales']) * 12.0, 2)
        ],
        'segments'          => $segments,
        'trends'            => $trends,
        'period_comparison' => $period_comparison
    ];

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('ChurnGuard API Error: '.$e->getMessage());
    echo json_encode(['status'=>'error','error'=>'server_error','message'=>'Internal error: ' . $e->getMessage()]);
}