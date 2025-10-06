<?php
declare(strict_types=1);

// ==================== CONFIGURATION ====================
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/errors.log');

// Security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS Configuration
$allowedOrigins = ['https://yourdomain.com']; // Update with your domain
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *'); // Change in production
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== DATABASE CONFIGURATION ====================
// IMPORTANT: Move these to a config file outside web root in production
// For Hostinger: Use environment variables or separate config.php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'u393812660_churnguard');
define('DB_USER', getenv('DB_USER') ?: 'u393812660_churnguard');
define('DB_PASS', getenv('DB_PASS') ?: '102202Brian_');

// ==================== SESSION CONFIGURATION ====================
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1'); // Ensure HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'error' => 'unauthorized',
        'message' => 'Authentication required'
    ]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

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
            PDO::ATTR_PERSISTENT => false // Change to true for connection pooling
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'error' => 'service_unavailable',
        'message' => 'Service temporarily unavailable'
    ]);
    exit;
}

// ==================== HELPER FUNCTIONS ====================
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
    exit;
}

function jsonSuccess(array $data = []): void {
    jsonResponse(array_merge(['status' => 'success'], $data));
}

function jsonError(string $message, int $code = 400, string $error = null): void {
    $response = [
        'status' => 'error',
        'message' => $message
    ];
    if ($error) {
        $response['error'] = $error;
    }
    jsonResponse($response, $code);
}

function safeDivide(float $numerator, float $denominator): float {
    return $denominator > 0 ? round($numerator / $denominator, 2) : 0.00;
}

function percentageChange(float $current, float $previous): float {
    if ($previous == 0) {
        return $current > 0 ? 100.00 : 0.00;
    }
    return round((($current - $previous) / $previous) * 100, 2);
}

function validateDate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeString(?string $input, int $maxLength = 255): string {
    if ($input === null) return '';
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

// ==================== REQUEST HANDLING ====================
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ==================== ROUTES ====================

// KPI Summary
if ($action === 'kpi_summary') {
    try {
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
        $todayData = $stmtToday->fetch() ?: [
            'sales_volume' => 0,
            'receipt_count' => 0,
            'customer_traffic' => 0
        ];
        
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
        $yesterdayData = $stmtYesterday->fetch() ?: [
            'sales_volume' => 0,
            'receipt_count' => 0,
            'customer_traffic' => 0
        ];
        
        // Calculate changes
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
        error_log("KPI Summary Error: " . $e->getMessage());
        jsonError('Failed to load KPI summary', 500);
    }
}

// Data Comparison
elseif ($action === 'compare') {
    try {
        $currentDate = $_GET['currentDate'] ?? date('Y-m-d');
        $compareDate = $_GET['compareDate'] ?? date('Y-m-d', strtotime('-1 day'));
        
        if (!validateDate($currentDate) || !validateDate($compareDate)) {
            jsonError('Invalid date format. Use YYYY-MM-DD', 422, 'invalid_date');
        }
        
        // Get current period data
        $stmtCurrent = $pdo->prepare("
            SELECT 
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCurrent->execute([$userId, $currentDate]);
        $currentData = $stmtCurrent->fetch() ?: [
            'sales_volume' => 0,
            'receipt_count' => 0,
            'customer_traffic' => 0
        ];
        
        // Get comparison period data
        $stmtCompare = $pdo->prepare("
            SELECT 
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCompare->execute([$userId, $compareDate]);
        $compareData = $stmtCompare->fetch() ?: [
            'sales_volume' => 0,
            'receipt_count' => 0,
            'customer_traffic' => 0
        ];
        
        // Calculate metrics
        $currentSales = (float)$currentData['sales_volume'];
        $compareSales = (float)$compareData['sales_volume'];
        $currentReceipts = (int)$currentData['receipt_count'];
        $compareReceipts = (int)$compareData['receipt_count'];
        $currentCustomers = (int)$currentData['customer_traffic'];
        $compareCustomers = (int)$compareData['customer_traffic'];
        
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
        error_log("Comparison Error: " . $e->getMessage());
        jsonError('Comparison failed', 500);
    }
}

// Get Targets
elseif ($action === 'get_targets') {
    try {
        $filter = $_GET['filter'] ?? 'all';
        $validFilters = ['all', 'active', 'achieved', 'near', 'below'];
        
        if (!in_array($filter, $validFilters, true)) {
            jsonError('Invalid filter parameter', 422, 'invalid_filter');
        }
        
        $query = "
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
        ";
        
        if ($filter === 'active') {
            $query .= " AND t.end_date >= CURDATE() AND t.start_date <= CURDATE()";
        }
        
        $query .= " GROUP BY t.id ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$userId]);
        $targets = $stmt->fetchAll();
        
        // Process targets
        foreach ($targets as &$target) {
            $currentValue = getTargetCurrentValue($target);
            $targetValue = (float)$target['target_value'];
            $progressData = calculateTargetProgress($currentValue, $targetValue);
            
            $target['current_value'] = $currentValue;
            $target['progress'] = $progressData['progress'];
            $target['status'] = $progressData['status'];
            
            // Remove temporary fields
            unset(
                $target['current_sales'],
                $target['current_receipts'],
                $target['current_customers'],
                $target['current_avg_transaction']
            );
        }
        
        // Apply status filter if needed
        if (in_array($filter, ['achieved', 'near', 'below'], true)) {
            $targets = array_values(array_filter($targets, fn($t) => $t['status'] === $filter));
        }
        
        jsonSuccess(['targets' => $targets]);
        
    } catch (Throwable $e) {
        error_log("Get Targets Error: " . $e->getMessage());
        jsonError('Failed to load targets', 500);
    }
}

// Save Target
elseif ($action === 'save_target' && $method === 'POST') {
    try {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonError('Invalid JSON data', 422, 'invalid_json');
        }
        
        // Validate and sanitize inputs
        $name = sanitizeString($data['name'] ?? '', 100);
        $type = trim($data['type'] ?? '');
        $value = filter_var($data['value'] ?? 0, FILTER_VALIDATE_FLOAT);
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $store = sanitizeString($data['store'] ?? '', 100);
        
        // Validation
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
        
        // Insert target
        $stmt = $pdo->prepare("
            INSERT INTO targets 
            (user_id, target_name, target_type, target_value, start_date, end_date, store, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $name,
            $type,
            $value,
            $startDate,
            $endDate,
            $store
        ]);
        
        jsonSuccess([
            'id' => (int)$pdo->lastInsertId(),
            'message' => 'Target created successfully'
        ]);
        
    } catch (Throwable $e) {
        error_log("Save Target Error: " . $e->getMessage());
        jsonError('Failed to save target', 500);
    }
}

// Update Target
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
        
        // Validation (same as save)
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
        
        $stmt->execute([
            $name,
            $type,
            $value,
            $startDate,
            $endDate,
            $store,
            $id,
            $userId
        ]);
        
        jsonSuccess([
            'id' => $id,
            'message' => 'Target updated successfully'
        ]);
        
    } catch (Throwable $e) {
        error_log("Update Target Error: " . $e->getMessage());
        jsonError('Failed to update target', 500);
    }
}

// Delete Target
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
        
        jsonSuccess(['message' => 'Target deleted successfully']);
        
    } catch (Throwable $e) {
        error_log("Delete Target Error: " . $e->getMessage());
        jsonError('Failed to delete target', 500);
    }
}

// Trend Data
elseif ($action === 'trend_data') {
    try {
        $days = filter_var($_GET['days'] ?? 30, FILTER_VALIDATE_INT);
        $days = max(7, min(90, $days ?: 30));
        
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
        
        $stmt->execute([$userId, $days]);
        $trendData = $stmt->fetchAll();
        
        jsonSuccess(['trend_data' => $trendData]);
        
    } catch (Throwable $e) {
        error_log("Trend Data Error: " . $e->getMessage());
        jsonError('Failed to load trend data', 500);
    }
}

// Invalid action
else {
    jsonError('Invalid action parameter', 400, 'invalid_action');
}