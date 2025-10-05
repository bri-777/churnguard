<?php
// api/performance_comparison.php - Performance Analytics API with authentication

// --- DB credentials ---
$db_host = 'localhost';
$db_name = 'u393812660_churnguard';
$db_user = 'u393812660_churnguard';
$db_pass = '102202Brian_';

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
    
    // --- Database connection ---
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Helper functions
    function json_success($data = []) {
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }
    
    function json_error($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }
    
    $action = $_GET['action'] ?? 'compare';
    
    // ========== COMPARE CURRENT VS PREVIOUS YEAR ==========
    if ($action === 'compare') {
        $currentYear = (int)date('Y');
        $previousYear = $currentYear - 1;
        
        // Fetch current year data
        $qCurrent = $pdo->prepare("
            SELECT 
                SUM(total_sales) as total_sales,
                SUM(total_customers) as total_customers,
                SUM(new_customers) as new_customers,
                SUM(total_transactions) as total_transactions,
                AVG(average_transaction_value) as avg_transaction_value
            FROM yearly_performance
            WHERE user_id = ? AND year = ?
        ");
        $qCurrent->execute([$current_user_id, $currentYear]);
        $current = $qCurrent->fetch();
        
        // Fetch previous year data
        $qPrevious = $pdo->prepare("
            SELECT 
                SUM(total_sales) as total_sales,
                SUM(total_customers) as total_customers,
                SUM(new_customers) as new_customers,
                SUM(total_transactions) as total_transactions,
                AVG(average_transaction_value) as avg_transaction_value
            FROM yearly_performance
            WHERE user_id = ? AND year = ?
        ");
        $qPrevious->execute([$current_user_id, $previousYear]);
        $previous = $qPrevious->fetch();
        
        // Calculate growth rates
        $salesGrowth = 0;
        $customerGrowth = 0;
        $transactionGrowth = 0;
        
        if ($previous && $previous['total_sales'] > 0) {
            $salesGrowth = (($current['total_sales'] - $previous['total_sales']) / $previous['total_sales']) * 100;
        }
        
        if ($previous && $previous['total_customers'] > 0) {
            $customerGrowth = (($current['total_customers'] - $previous['total_customers']) / $previous['total_customers']) * 100;
        }
        
        if ($previous && $previous['total_transactions'] > 0) {
            $transactionGrowth = (($current['total_transactions'] - $previous['total_transactions']) / $previous['total_transactions']) * 100;
        }
        
        // Calculate competitiveness score (0-100)
        // Weighted formula: 40% sales growth + 60% customer growth, normalized to 0-100
        $competitivenessScore = max(0, min(100, 50 + ($salesGrowth * 0.4) + ($customerGrowth * 0.6)));
        
        // Fetch monthly breakdown for charts
        $qMonthly = $pdo->prepare("
            SELECT year, month, total_sales, total_customers, total_transactions
            FROM yearly_performance
            WHERE user_id = ? AND year IN (?, ?)
            ORDER BY year, month
        ");
        $qMonthly->execute([$current_user_id, $currentYear, $previousYear]);
        $monthlyData = $qMonthly->fetchAll();
        
        // Fetch current targets to calculate progress
        $qTargets = $pdo->prepare("
            SELECT * FROM performance_targets
            WHERE user_id = ? AND year = ?
        ");
        $qTargets->execute([$current_user_id, $currentYear]);
        $targets = $qTargets->fetchAll();
        
        // Calculate target progress
        $targetProgress = [];
        foreach ($targets as $target) {
            $currentValue = 0;
            $targetValue = (float)$target['target_value'];
            
            switch ($target['target_type']) {
                case 'sales':
                    $currentValue = (float)($current['total_sales'] ?? 0);
                    break;
                case 'customers':
                    $currentValue = (float)($current['total_customers'] ?? 0);
                    break;
                case 'transactions':
                    $currentValue = (float)($current['total_transactions'] ?? 0);
                    break;
                case 'growth_rate':
                    $currentValue = $salesGrowth;
                    break;
            }
            
            $progress = $targetValue > 0 ? min(100, ($currentValue / $targetValue) * 100) : 0;
            
            $targetProgress[] = [
                'id' => $target['id'],
                'type' => $target['target_type'],
                'period' => $target['target_period'],
                'target_value' => $targetValue,
                'current_value' => $currentValue,
                'progress_percentage' => round($progress, 2)
            ];
        }
        
        json_success([
            'current_year' => $currentYear,
            'previous_year' => $previousYear,
            'current' => [
                'total_sales' => (float)($current['total_sales'] ?? 0),
                'total_customers' => (int)($current['total_customers'] ?? 0),
                'new_customers' => (int)($current['new_customers'] ?? 0),
                'total_transactions' => (int)($current['total_transactions'] ?? 0),
                'avg_transaction_value' => (float)($current['avg_transaction_value'] ?? 0)
            ],
            'previous' => [
                'total_sales' => (float)($previous['total_sales'] ?? 0),
                'total_customers' => (int)($previous['total_customers'] ?? 0),
                'new_customers' => (int)($previous['new_customers'] ?? 0),
                'total_transactions' => (int)($previous['total_transactions'] ?? 0),
                'avg_transaction_value' => (float)($previous['avg_transaction_value'] ?? 0)
            ],
            'growth' => [
                'sales' => round($salesGrowth, 2),
                'customers' => round($customerGrowth, 2),
                'transactions' => round($transactionGrowth, 2)
            ],
            'competitiveness_score' => round($competitivenessScore, 2),
            'monthly_data' => $monthlyData,
            'target_progress' => $targetProgress
        ]);
    }
    
    // ========== GET TARGETS ==========
    if ($action === 'get_targets') {
        $year = (int)($_GET['year'] ?? date('Y'));
        
        $q = $pdo->prepare("
            SELECT * FROM performance_targets
            WHERE user_id = ? AND year = ?
            ORDER BY target_type, target_period
        ");
        $q->execute([$current_user_id, $year]);
        $targets = $q->fetchAll();
        
        json_success(['targets' => $targets, 'year' => $year]);
    }
    
    // ========== SET/UPDATE TARGET ==========
    if ($action === 'set_target') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        
        $year = (int)($data['year'] ?? date('Y'));
        $targetType = trim($data['target_type'] ?? '');
        $targetValue = (float)($data['target_value'] ?? 0);
        $targetPeriod = trim($data['target_period'] ?? 'yearly');
        
        if (!in_array($targetType, ['sales', 'customers', 'growth_rate', 'transactions'])) {
            json_error('Invalid target type', 422);
        }
        
        if (!in_array($targetPeriod, ['monthly', 'quarterly', 'yearly'])) {
            json_error('Invalid target period', 422);
        }
        
        if ($targetValue <= 0) {
            json_error('Target value must be greater than 0', 422);
        }
        
        $sql = "
            INSERT INTO performance_targets 
                (user_id, year, target_type, target_value, target_period, created_at, updated_at)
            VALUES 
                (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                target_value = VALUES(target_value),
                updated_at = NOW()
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$current_user_id, $year, $targetType, $targetValue, $targetPeriod]);
        
        json_success([
            'saved' => true, 
            'year' => $year, 
            'target_type' => $targetType,
            'target_value' => $targetValue
        ]);
    }
    
    // ========== DELETE TARGET ==========
    if ($action === 'delete_target') {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            json_error('Invalid target ID', 422);
        }
        
        $stmt = $pdo->prepare("DELETE FROM performance_targets WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $current_user_id]);
        
        json_success(['deleted' => true, 'id' => $id]);
    }
    
    // ========== GET COMPETITIVENESS METRICS ==========
    if ($action === 'competitiveness') {
        $year = (int)($_GET['year'] ?? date('Y'));
        $month = (int)($_GET['month'] ?? date('n'));
        
        $q = $pdo->prepare("
            SELECT * FROM competitiveness_metrics
            WHERE user_id = ? AND year = ? AND month = ?
            LIMIT 1
        ");
        $q->execute([$current_user_id, $year, $month]);
        $metrics = $q->fetch();
        
        if (!$metrics) {
            json_success(['has_data' => false, 'message' => 'No competitiveness data for this period']);
        } else {
            json_success(['has_data' => true, 'metrics' => $metrics]);
        }
    }
    
    json_error('Unknown action', 400);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'database_error',
        'message' => 'Database connection failed'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'server_error',
        'message' => $e->getMessage()
    ]);
    exit;
}