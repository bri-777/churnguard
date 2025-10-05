<?php
declare(strict_types=1);

// ==================== CONFIGURATION ====================
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database credentials
$db_host = 'localhost';
$db_name = 'u393812660_churnguard';
$db_user = 'u393812660_churnguard';
$db_pass = '102202Brian_';

// ==================== AUTHENTICATION ====================
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'error' => 'unauthorized',
        'message' => 'User not authenticated. Please login.'
    ]);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// ==================== DATABASE CONNECTION ====================
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'db_connection',
        'message' => 'Database connection failed'
    ]);
    exit;
}

// ==================== HELPER FUNCTIONS ====================
function j_ok(array $d = []) {
    echo json_encode(array_merge(['status' => 'success'], $d));
    exit;
}

function j_err(string $m, int $c = 400) {
    http_response_code($c);
    echo json_encode([
        'status' => 'error',
        'message' => $m
    ]);
    exit;
}

function safeDivide(float $numerator, float $denominator): float {
    return $denominator > 0 ? round($numerator / $denominator, 2) : 0;
}

function percentageChange(float $current, float $previous): float {
    return $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : 0;
}

function isValidDate(string $date): bool {
    return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
}

function getTargetCurrentValue(array $target): float {
    switch ($target['target_type']) {
        case 'sales':
            return (float)($target['current_sales'] ?? 0);
        case 'transactions':
            return (float)($target['current_receipts'] ?? 0);
        case 'customers':
            return (float)($target['current_customers'] ?? 0);
        case 'avg_transaction':
            return (float)($target['current_avg_transaction'] ?? 0);
        default:
            return 0;
    }
}

function calculateTargetProgress(float $current, float $targetValue): array {
    $progress = safeDivide($current * 100, $targetValue);
    $progress = min($progress, 999.9);
    
    if ($progress >= 100) {
        $status = 'achieved';
    } elseif ($progress >= 80) {
        $status = 'near';
    } else {
        $status = 'below';
    }
    
    return [
        'progress' => round($progress, 2),
        'status' => $status
    ];
}

// ==================== GET ACTION ====================
$action = $_GET['action'] ?? '';

// ==================== KPI SUMMARY ====================
if ($action === 'kpi_summary') {
    try {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Today's data
        $stmtToday = $pdo->prepare("
            SELECT 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtToday->execute([$uid, $today]);
        $todayData = $stmtToday->fetch(PDO::FETCH_ASSOC);
        
        // Yesterday's data
        $stmtYesterday = $pdo->prepare("
            SELECT 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtYesterday->execute([$uid, $yesterday]);
        $yesterdayData = $stmtYesterday->fetch(PDO::FETCH_ASSOC);
        
        if (!$todayData) {
            $todayData = ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        }
        if (!$yesterdayData) {
            $yesterdayData = ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        }
        
        $todaySales = (float)$todayData['sales_volume'];
        $yesterdaySales = (float)$yesterdayData['sales_volume'];
        $salesChange = percentageChange($todaySales, $yesterdaySales);
        
        $todayCustomers = (int)$todayData['customer_traffic'];
        $yesterdayCustomers = (int)$yesterdayData['customer_traffic'];
        $customersChange = percentageChange((float)$todayCustomers, (float)$yesterdayCustomers);
        
        $todayTransactions = (int)$todayData['receipt_count'];
        $yesterdayTransactions = (int)$yesterdayData['receipt_count'];
        $transactionsChange = percentageChange((float)$todayTransactions, (float)$yesterdayTransactions);
        
        // Get active target
        $stmtTarget = $pdo->prepare("
            SELECT t.*, 
                   COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                   COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                   COALESCE(SUM(cd.customer_traffic), 0) as current_customers
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE t.user_id = ? 
                AND t.end_date >= CURDATE()
                AND t.start_date <= CURDATE()
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 1
        ");
        $stmtTarget->execute([$uid]);
        $targetData = $stmtTarget->fetch(PDO::FETCH_ASSOC);
        
        $targetAchievement = 0;
        $targetStatus = 'No active target';
        
        if ($targetData) {
            $current = getTargetCurrentValue($targetData);
            $targetValue = (float)$targetData['target_value'];
            $progressData = calculateTargetProgress($current, $targetValue);
            $targetAchievement = $progressData['progress'];
            $targetStatus = $targetData['target_name'];
        }
        
        j_ok([
            'today_sales' => $todaySales,
            'sales_change' => $salesChange,
            'today_customers' => $todayCustomers,
            'customers_change' => $customersChange,
            'today_transactions' => $todayTransactions,
            'transactions_change' => $transactionsChange,
            'target_achievement' => $targetAchievement,
            'target_status' => $targetStatus
        ]);
        
    } catch (Throwable $e) {
        error_log("KPI Error: " . $e->getMessage());
        j_err('Failed to load KPI summary', 500);
    }
}

// ==================== COMPARISON ====================
if ($action === 'compare') {
    try {
        $currentDate = $_GET['currentDate'] ?? date('Y-m-d');
        $compareDate = $_GET['compareDate'] ?? date('Y-m-d', strtotime('-1 day'));
        
        if (!isValidDate($currentDate) || !isValidDate($compareDate)) {
            j_err('Invalid date format', 422);
        }
        
        $stmtCurrent = $pdo->prepare("
            SELECT 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCurrent->execute([$uid, $currentDate]);
        $currentData = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
        
        $stmtCompare = $pdo->prepare("
            SELECT 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCompare->execute([$uid, $compareDate]);
        $compareData = $stmtCompare->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentData) {
            $currentData = ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        }
        if (!$compareData) {
            $compareData = ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        }
        
        $currentSales = (float)$currentData['sales_volume'];
        $compareSales = (float)$compareData['sales_volume'];
        $currentReceipts = (int)$currentData['receipt_count'];
        $compareReceipts = (int)$compareData['receipt_count'];
        $currentCustomers = (int)$currentData['customer_traffic'];
        $compareCustomers = (int)$compareData['customer_traffic'];
        
        $currentAvgTrans = safeDivide($currentSales, $currentReceipts);
        $compareAvgTrans = safeDivide($compareSales, $compareReceipts);
        
        $metrics = [
            [
                'metric' => 'Sales Revenue',
                'current' => $currentSales,
                'compare' => $compareSales,
                'difference' => $currentSales - $compareSales,
                'percentage' => percentageChange($currentSales, $compareSales),
                'trend' => $currentSales >= $compareSales ? 'up' : 'down'
            ],
            [
                'metric' => 'Transactions',
                'current' => $currentReceipts,
                'compare' => $compareReceipts,
                'difference' => $currentReceipts - $compareReceipts,
                'percentage' => percentageChange((float)$currentReceipts, (float)$compareReceipts),
                'trend' => $currentReceipts >= $compareReceipts ? 'up' : 'down'
            ],
            [
                'metric' => 'Customer Traffic',
                'current' => $currentCustomers,
                'compare' => $compareCustomers,
                'difference' => $currentCustomers - $compareCustomers,
                'percentage' => percentageChange((float)$currentCustomers, (float)$compareCustomers),
                'trend' => $currentCustomers >= $compareCustomers ? 'up' : 'down'
            ],
            [
                'metric' => 'Avg Transaction Value',
                'current' => $currentAvgTrans,
                'compare' => $compareAvgTrans,
                'difference' => $currentAvgTrans - $compareAvgTrans,
                'percentage' => percentageChange($currentAvgTrans, $compareAvgTrans),
                'trend' => $currentAvgTrans >= $compareAvgTrans ? 'up' : 'down'
            ]
        ];
        
        j_ok([
            'comparison' => $metrics,
            'currentDate' => $currentDate,
            'compareDate' => $compareDate
        ]);
        
    } catch (Throwable $e) {
        error_log("Comparison Error: " . $e->getMessage());
        j_err('Comparison failed', 500);
    }
}

// ==================== GET TARGETS ====================
if ($action === 'get_targets') {
    try {
        $filter = $_GET['filter'] ?? 'all';
        $validFilters = ['all', 'active', 'achieved', 'near', 'below'];
        
        if (!in_array($filter, $validFilters, true)) {
            j_err('Invalid filter', 422);
        }
        
        $query = "
            SELECT t.*, 
                   COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                   COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                   COALESCE(SUM(cd.customer_traffic), 0) as current_customers,
                   COALESCE(AVG(CASE 
                       WHEN cd.receipt_count > 0 
                       THEN cd.sales_volume / cd.receipt_count 
                       ELSE 0 
                   END), 0) as current_avg_transaction
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE t.user_id = ?
        ";
        
        if ($filter === 'active') {
            $query .= " AND t.end_date >= CURDATE() AND t.start_date <= CURDATE()";
        }
        
        $query .= " GROUP BY t.id ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$uid]);
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($targets as &$target) {
            $current = getTargetCurrentValue($target);
            $targetValue = (float)$target['target_value'];
            $progressData = calculateTargetProgress($current, $targetValue);
            
            $target['current_value'] = $current;
            $target['progress'] = $progressData['progress'];
            $target['status'] = $progressData['status'];
            
            unset($target['current_sales'], $target['current_receipts'], 
                  $target['current_customers'], $target['current_avg_transaction']);
        }
        
        if (in_array($filter, ['achieved', 'near', 'below'], true)) {
            $targets = array_filter($targets, fn($t) => $t['status'] === $filter);
            $targets = array_values($targets);
        }
        
        j_ok(['targets' => $targets]);
        
    } catch (Throwable $e) {
        error_log("Get Targets Error: " . $e->getMessage());
        j_err('Failed to load targets', 500);
    }
}

// ==================== SAVE TARGET ====================
if ($action === 'save_target') {
    try {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true) ?: $_POST;
        
        $name = trim($data['name'] ?? '');
        $type = trim($data['type'] ?? '');
        $value = (float)($data['value'] ?? 0);
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $store = trim($data['store'] ?? '');
        
        if (empty($name)) j_err('Target name is required', 422);
        if (strlen($name) > 100) j_err('Target name too long', 422);
        if (!in_array($type, ['sales', 'customers', 'transactions', 'avg_transaction'])) j_err('Invalid target type', 422);
        if ($value <= 0) j_err('Target value must be greater than 0', 422);
        if ($value > 999999999) j_err('Target value too large', 422);
        if (!isValidDate($startDate) || !isValidDate($endDate)) j_err('Invalid date format', 422);
        if (strtotime($endDate) < strtotime($startDate)) j_err('End date must be after start date', 422);
        
        $stmt = $pdo->prepare("
            INSERT INTO targets 
            (user_id, target_name, target_type, target_value, start_date, end_date, store, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$uid, $name, $type, $value, $startDate, $endDate, $store]);
        
        j_ok([
            'saved' => true,
            'id' => (int)$pdo->lastInsertId(),
            'message' => 'Target created successfully'
        ]);
        
    } catch (Throwable $e) {
        error_log("Save Target Error: " . $e->getMessage());
        j_err('Failed to save target', 500);
    }
}

// ==================== DELETE TARGET ====================
if ($action === 'delete_target') {
    try {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) j_err('Invalid target ID', 422);
        
        $stmtCheck = $pdo->prepare("SELECT id FROM targets WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$id, $uid]);
        
        if (!$stmtCheck->fetch()) j_err('Target not found', 404);
        
        $stmt = $pdo->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $uid]);
        
        j_ok([
            'deleted' => true,
            'message' => 'Target deleted successfully'
        ]);
        
    } catch (Throwable $e) {
        error_log("Delete Target Error: " . $e->getMessage());
        j_err('Failed to delete target', 500);
    }
}

// ==================== TREND DATA ====================
if ($action === 'trend_data') {
    try {
        $days = (int)($_GET['days'] ?? 30);
        $days = max(7, min(90, $days));
        
        $stmt = $pdo->prepare("
            SELECT 
                date, 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data
            WHERE user_id = ? 
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND date <= CURDATE()
            ORDER BY date ASC
        ");
        $stmt->execute([$uid, $days]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        j_ok(['trend_data' => $data]);
        
    } catch (Throwable $e) {
        error_log("Trend Data Error: " . $e->getMessage());
        j_err('Failed to load trend data', 500);
    }
}

// ==================== INVALID ACTION ====================
j_err('Unknown action', 400);