<?php
declare(strict_types=1);

// ==================== CONFIGURATION ====================
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/errors.log');

// ==================== CORS & HEADERS ====================
// Handle CORS first, before any other output
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowedOrigins = [
        'http://localhost',
        'http://127.0.0.1',
        'http://localhost:3000',
        'http://127.0.0.1:3000'
    ];
    
    // Check if origin matches allowed patterns
    $isAllowed = false;
    foreach ($allowedOrigins as $allowed) {
        if (strpos($origin, $allowed) === 0) {
            $isAllowed = true;
            break;
        }
    }
    
    if ($isAllowed) {
        header("Access-Control-Allow-Origin: $origin");
    }
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// ==================== DATABASE CONFIGURATION ====================
define('DB_HOST', 'localhost');
define('DB_NAME', 'u393812660_churnguard');
define('DB_USER', 'u393812660_churnguard');
define('DB_PASS', '102202Brian_');

// ==================== SESSION MANAGEMENT ====================
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== LOGGING FUNCTION ====================
function logDebug(string $message, array $context = []): void {
    $logMessage = date('[Y-m-d H:i:s] ') . $message;
    if (!empty($context)) {
        $logMessage .= ' | Context: ' . json_encode($context);
    }
    error_log($logMessage);
}

// ==================== AUTHENTICATION CHECK ====================
logDebug("=== API Request Started ===", [
    'action' => $_GET['action'] ?? 'none',
    'method' => $_SERVER['REQUEST_METHOD'],
    'session_id' => session_id(),
    'has_user_id' => isset($_SESSION['user_id'])
]);

if (!isset($_SESSION['user_id'])) {
    logDebug("Authentication failed - no user_id in session");
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'error' => 'unauthorized',
        'message' => 'Authentication required. Please log in.'
    ]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
logDebug("Authenticated user ID: $userId");

// ==================== DATABASE CONNECTION ====================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]
    );
    logDebug("Database connected successfully");
} catch (PDOException $e) {
    logDebug("Database connection failed", ['error' => $e->getMessage()]);
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'error' => 'service_unavailable',
        'message' => 'Database connection failed. Please try again later.'
    ]);
    exit;
}

// ==================== HELPER FUNCTIONS ====================

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit;
}

function jsonSuccess(array $data = []): void {
    logDebug("Success response", $data);
    jsonResponse(array_merge(['status' => 'success'], $data));
}

function jsonError(string $message, int $code = 400, string $error = 'error'): void {
    logDebug("Error response: $message (code: $code)");
    jsonResponse([
        'status' => 'error',
        'error' => $error,
        'message' => $message
    ], $code);
}

function safeDivide(float $numerator, float $denominator): float {
    return $denominator > 0 ? round($numerator / $denominator, 2) : 0.00;
}

function percentageChange(float $current, float $previous): float {
    if ($previous == 0) {
        return $current > 0 ? 100.00 : 0.00;
    }
    $change = (($current - $previous) / $previous) * 100;
    return round(max(-999.9, min(999.9, $change)), 2);
}

function validateDate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeString(?string $input, int $maxLength = 255): string {
    if ($input === null || $input === '') return '';
    $cleaned = trim(strip_tags($input));
    return mb_substr($cleaned, 0, $maxLength, 'UTF-8');
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
            return 0.00;
    }
}

function calculateTargetProgress(float $current, float $target): array {
    $progress = min(safeDivide($current * 100, $target), 999.9);
    
    $status = 'below';
    if ($progress >= 100) {
        $status = 'achieved';
    } elseif ($progress >= 80) {
        $status = 'near';
    }
    
    return [
        'progress' => round($progress, 2),
        'status' => $status
    ];
}

// ==================== ROUTE HANDLING ====================
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

logDebug("Processing action: $action with method: $method");

// ==================== KPI SUMMARY ====================
if ($action === 'kpi_summary') {
    try {
        logDebug("Loading KPI summary for user: $userId");
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Get today's data
        $stmtToday = $pdo->prepare("
            SELECT 
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtToday->execute([$userId, $today]);
        $todayData = $stmtToday->fetch();
        
        // Get yesterday's data
        $stmtYesterday = $pdo->prepare("
            SELECT 
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtYesterday->execute([$userId, $yesterday]);
        $yesterdayData = $stmtYesterday->fetch();
        
        // Calculate metrics
        $todaySales = (float)($todayData['sales_volume'] ?? 0);
        $yesterdaySales = (float)($yesterdayData['sales_volume'] ?? 0);
        $salesChange = percentageChange($todaySales, $yesterdaySales);
        
        $todayCustomers = (int)($todayData['customer_traffic'] ?? 0);
        $yesterdayCustomers = (int)($yesterdayData['customer_traffic'] ?? 0);
        $customersChange = percentageChange((float)$todayCustomers, (float)$yesterdayCustomers);
        
        $todayTransactions = (int)($todayData['receipt_count'] ?? 0);
        $yesterdayTransactions = (int)($yesterdayData['receipt_count'] ?? 0);
        $transactionsChange = percentageChange((float)$todayTransactions, (float)$yesterdayTransactions);
        
        // Get active target
        $stmtTarget = $pdo->prepare("
            SELECT 
                t.*,
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
                AND t.end_date >= CURDATE()
                AND t.start_date <= CURDATE()
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 1
        ");
        $stmtTarget->execute([$userId]);
        $targetData = $stmtTarget->fetch();
        
        $targetAchievement = 0;
        $targetStatus = 'No active target';
        
        if ($targetData) {
            $currentValue = getTargetCurrentValue($targetData);
            $targetValue = (float)$targetData['target_value'];
            $progressData = calculateTargetProgress($currentValue, $targetValue);
            $targetAchievement = $progressData['progress'];
            $targetStatus = $targetData['target_name'];
        }
        
        jsonSuccess([
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
        logDebug("KPI Summary Error", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        jsonError('Failed to load KPI summary', 500);
    }
}

// ==================== TREND DATA ====================
elseif ($action === 'trend_data') {
    try {
        $days = filter_var($_GET['days'] ?? 30, FILTER_VALIDATE_INT);
        $days = max(7, min(90, $days ?: 30));
        
        logDebug("Loading trend data", ['days' => $days, 'user_id' => $userId]);
        
        $stmt = $pdo->prepare("
            SELECT 
                date,
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data
            WHERE user_id = ? 
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND date <= CURDATE()
            GROUP BY date
            ORDER BY date ASC
        ");
        
        $stmt->execute([$userId, $days]);
        $trendData = $stmt->fetchAll();
        
        logDebug("Trend data loaded", ['record_count' => count($trendData)]);
        
        jsonSuccess(['trend_data' => $trendData]);
        
    } catch (Throwable $e) {
        logDebug("Trend Data Error", ['error' => $e->getMessage()]);
        jsonError('Failed to load trend data', 500);
    }
}

// ==================== COMPARISON ====================
elseif ($action === 'compare') {
    try {
        $currentDate = $_GET['currentDate'] ?? date('Y-m-d');
        $compareDate = $_GET['compareDate'] ?? date('Y-m-d', strtotime('-1 day'));
        
        if (!validateDate($currentDate) || !validateDate($compareDate)) {
            jsonError('Invalid date format. Use YYYY-MM-DD', 422, 'invalid_date');
        }
        
        logDebug("Comparing dates", ['current' => $currentDate, 'compare' => $compareDate]);
        
        // Current period data
        $stmtCurrent = $pdo->prepare("
            SELECT 
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCurrent->execute([$userId, $currentDate]);
        $currentData = $stmtCurrent->fetch();
        
        // Comparison period data
        $stmtCompare = $pdo->prepare("
            SELECT 
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCompare->execute([$userId, $compareDate]);
        $compareData = $stmtCompare->fetch();
        
        // Calculate metrics
        $currentSales = (float)($currentData['sales_volume'] ?? 0);
        $compareSales = (float)($compareData['sales_volume'] ?? 0);
        $currentReceipts = (int)($currentData['receipt_count'] ?? 0);
        $compareReceipts = (int)($compareData['receipt_count'] ?? 0);
        $currentCustomers = (int)($currentData['customer_traffic'] ?? 0);
        $compareCustomers = (int)($compareData['customer_traffic'] ?? 0);
        
        $currentAvgTrans = safeDivide($currentSales, (float)$currentReceipts);
        $compareAvgTrans = safeDivide($compareSales, (float)$compareReceipts);
        
        $metrics = [
            [
                'metric' => 'Sales Revenue',
                'current' => $currentSales,
                'compare' => $compareSales,
                'difference' => round($currentSales - $compareSales, 2),
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
                'difference' => round($currentAvgTrans - $compareAvgTrans, 2),
                'percentage' => percentageChange($currentAvgTrans, $compareAvgTrans),
                'trend' => $currentAvgTrans >= $compareAvgTrans ? 'up' : 'down'
            ]
        ];
        
        jsonSuccess([
            'comparison' => $metrics,
            'currentDate' => $currentDate,
            'compareDate' => $compareDate
        ]);
        
    } catch (Throwable $e) {
        logDebug("Comparison Error", ['error' => $e->getMessage()]);
        jsonError('Comparison failed', 500);
    }
}

// ==================== GET TARGETS ====================
elseif ($action === 'get_targets') {
    try {
        $filter = $_GET['filter'] ?? 'all';
        $validFilters = ['all', 'active', 'achieved', 'near', 'below'];
        
        if (!in_array($filter, $validFilters, true)) {
            jsonError('Invalid filter parameter', 422, 'invalid_filter');
        }
        
        logDebug("Getting targets", ['filter' => $filter]);
        
        $query = "
            SELECT 
                t.id,
                t.target_name,
                t.target_type,
                t.target_value,
                t.start_date,
                t.end_date,
                t.store,
                t.created_at,
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
        $stmt->execute([$userId]);
        $targets = $stmt->fetchAll();
        
        // Process targets
        $processedTargets = [];
        foreach ($targets as $target) {
            $currentValue = getTargetCurrentValue($target);
            $targetValue = (float)$target['target_value'];
            $progressData = calculateTargetProgress($currentValue, $targetValue);
            
            $processedTargets[] = [
                'id' => (int)$target['id'],
                'target_name' => $target['target_name'],
                'target_type' => $target['target_type'],
                'target_value' => $targetValue,
                'start_date' => $target['start_date'],
                'end_date' => $target['end_date'],
                'store' => $target['store'] ?? '',
                'current_value' => $currentValue,
                'progress' => $progressData['progress'],
                'status' => $progressData['status']
            ];
        }
        
        // Apply status filter
        if (in_array($filter, ['achieved', 'near', 'below'], true)) {
            $processedTargets = array_values(array_filter(
                $processedTargets, 
                fn($t) => $t['status'] === $filter
            ));
        }
        
        logDebug("Targets loaded", ['count' => count($processedTargets)]);
        
        jsonSuccess(['targets' => $processedTargets]);
        
    } catch (Throwable $e) {
        logDebug("Get Targets Error", ['error' => $e->getMessage()]);
        jsonError('Failed to load targets', 500);
    }
}

// ==================== SAVE TARGET ====================
elseif ($action === 'save_target' && $method === 'POST') {
    try {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonError('Invalid JSON data', 422, 'invalid_json');
        }
        
        // Validate inputs
        $name = sanitizeString($data['name'] ?? '', 100);
        $type = trim($data['type'] ?? '');
        $value = filter_var($data['value'] ?? 0, FILTER_VALIDATE_FLOAT);
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $store = sanitizeString($data['store'] ?? '', 100);
        
        if (empty($name)) {
            jsonError('Target name is required', 422, 'missing_name');
        }
        
        $validTypes = ['sales', 'customers', 'transactions', 'avg_transaction'];
        if (!in_array($type, $validTypes, true)) {
            jsonError('Invalid target type', 422, 'invalid_type');
        }
        
        if ($value === false || $value <= 0 || $value > 999999999) {
            jsonError('Invalid target value. Must be between 0.01 and 999,999,999', 422, 'invalid_value');
        }
        
        if (!validateDate($startDate) || !validateDate($endDate)) {
            jsonError('Invalid date format. Use YYYY-MM-DD', 422, 'invalid_date');
        }
        
        if (strtotime($endDate) < strtotime($startDate)) {
            jsonError('End date must be after start date', 422, 'invalid_date_range');
        }
        
        // Insert target
        $stmt = $pdo->prepare("
            INSERT INTO targets 
            (user_id, target_name, target_type, target_value, start_date, end_date, store, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$userId, $name, $type, $value, $startDate, $endDate, $store]);
        
        $newId = (int)$pdo->lastInsertId();
        logDebug("Target created", ['id' => $newId]);
        
        jsonSuccess([
            'id' => $newId,
            'message' => 'Target created successfully'
        ]);
        
    } catch (Throwable $e) {
        logDebug("Save Target Error", ['error' => $e->getMessage()]);
        jsonError('Failed to save target', 500);
    }
}

// ==================== UPDATE TARGET ====================
elseif ($action === 'update_target' && $method === 'POST') {
    try {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonError('Invalid JSON data', 422, 'invalid_json');
        }
        
        $id = filter_var($data['id'] ?? 0, FILTER_VALIDATE_INT);
        $name = sanitizeString($data['name'] ?? '', 100);
        $type = trim($data['type'] ?? '');
        $value = filter_var($data['value'] ?? 0, FILTER_VALIDATE_FLOAT);
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $store = sanitizeString($data['store'] ?? '', 100);
        
        if ($id === false || $id <= 0) {
            jsonError('Invalid target ID', 422, 'invalid_id');
        }
        
        // Check ownership
        $checkStmt = $pdo->prepare("SELECT id FROM targets WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$id, $userId]);
        
        if (!$checkStmt->fetch()) {
            jsonError('Target not found or access denied', 404, 'not_found');
        }
        
        // Validate (same as save)
        if (empty($name)) {
            jsonError('Target name is required', 422, 'missing_name');
        }
        
        $validTypes = ['sales', 'customers', 'transactions', 'avg_transaction'];
        if (!in_array($type, $validTypes, true)) {
            jsonError('Invalid target type', 422, 'invalid_type');
        }
        
        if ($value === false || $value <= 0 || $value > 999999999) {
            jsonError('Invalid target value', 422, 'invalid_value');
        }
        
        if (!validateDate($startDate) || !validateDate($endDate)) {
            jsonError('Invalid date format', 422, 'invalid_date');
        }
        
        if (strtotime($endDate) < strtotime($startDate)) {
            jsonError('End date must be after start date', 422, 'invalid_date_range');
        }
        
        // Update target
        $stmt = $pdo->prepare("
            UPDATE targets 
            SET target_name = ?, 
                target_type = ?, 
                target_value = ?, 
                start_date = ?, 
                end_date = ?, 
                store = ?,
                updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([$name, $type, $value, $startDate, $endDate, $store, $id, $userId]);
        
        logDebug("Target updated", ['id' => $id]);
        
        jsonSuccess([
            'id' => $id,
            'message' => 'Target updated successfully'
        ]);
        
    } catch (Throwable $e) {
        logDebug("Update Target Error", ['error' => $e->getMessage()]);
        jsonError('Failed to update target', 500);
    }
}

// ==================== DELETE TARGET ====================
elseif ($action === 'delete_target') {
    try {
        $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
        
        if ($id === false || $id <= 0) {
            jsonError('Invalid target ID', 422, 'invalid_id');
        }
        
        // Check ownership
        $checkStmt = $pdo->prepare("SELECT id FROM targets WHERE id = ? AND user_id = ?");
        $checkStmt->execute([$id, $userId]);
        
        if (!$checkStmt->fetch()) {
            jsonError('Target not found or access denied', 404, 'not_found');
        }
        
        // Delete target
        $deleteStmt = $pdo->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
        $deleteStmt->execute([$id, $userId]);
        
        logDebug("Target deleted", ['id' => $id]);
        
        jsonSuccess(['message' => 'Target deleted successfully']);
        
    } catch (Throwable $e) {
        logDebug("Delete Target Error", ['error' => $e->getMessage()]);
        jsonError('Failed to delete target', 500);
    }
}

// ==================== INVALID ACTION ====================
else {
    logDebug("Invalid action", ['action' => $action]);
    jsonError('Invalid action parameter', 400, 'invalid_action');
}