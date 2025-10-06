<?php
declare(strict_types=1);

// ==================== ENHANCED CONFIGURATION ====================
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/errors.log');

// Enhanced Security Headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// CORS with better security
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['https://yourdomain.com', 'http://localhost'];

if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Database Configuration
const DB_CONFIG = [
    'host' => 'localhost',
    'name' => 'u393812660_churnguard',
    'user' => 'u393812660_churnguard',
    'pass' => '102202Brian_',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_TIMEOUT => 10,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
    ]
];

// ==================== ENHANCED SESSION MANAGEMENT ====================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    session_start();
}

// Enhanced authentication with better error handling
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'error' => 'unauthorized',
        'message' => 'Authentication required. Please log in.',
        'redirect' => '/login.php'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
if ($userId === false || $userId <= 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'error' => 'invalid_session',
        'message' => 'Invalid session data'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== ENHANCED DATABASE CONNECTION ====================
try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        DB_CONFIG['host'],
        DB_CONFIG['name'],
        DB_CONFIG['charset']
    );
    
    $pdo = new PDO($dsn, DB_CONFIG['user'], DB_CONFIG['pass'], DB_CONFIG['options']);
    
    // Verify connection
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(503);
    echo json_encode([
        'status' => 'error',
        'error' => 'database_unavailable',
        'message' => 'Database service temporarily unavailable'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== ENHANCED HELPER FUNCTIONS ====================
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR);
    exit;
}

function jsonSuccess(array $data = []): void {
    jsonResponse(array_merge(['status' => 'success', 'timestamp' => time()], $data));
}

function jsonError(string $message, int $code = 400, ?string $error = null): void {
    $response = [
        'status' => 'error',
        'message' => $message,
        'timestamp' => time()
    ];
    if ($error) {
        $response['error'] = $error;
    }
    jsonResponse($response, $code);
}

// Enhanced division with precision
function safeDivide(float $numerator, float $denominator, int $precision = 2): float {
    if ($denominator == 0) {
        return 0.00;
    }
    $result = $numerator / $denominator;
    return round($result, $precision);
}

// Enhanced percentage calculation with edge cases
function percentageChange(float $current, float $previous, int $precision = 2): float {
    if ($previous == 0) {
        return $current > 0 ? 100.00 : 0.00;
    }
    if ($current == $previous) {
        return 0.00;
    }
    $change = (($current - $previous) / abs($previous)) * 100;
    return round($change, $precision);
}

// Enhanced date validation with timezone awareness
function validateDate(string $date): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Asia/Manila'));
    return $d && $d->format('Y-m-d') === $date;
}

// Enhanced string sanitization
function sanitizeString(?string $input, int $maxLength = 255): string {
    if ($input === null || $input === '') {
        return '';
    }
    $cleaned = trim(htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8'));
    return mb_substr($cleaned, 0, $maxLength, 'UTF-8');
}

// Enhanced target value calculation
function getTargetCurrentValue(array $target): float {
    $value = match($target['target_type']) {
        'sales' => (float)($target['current_sales'] ?? 0),
        'transactions' => (float)($target['current_receipts'] ?? 0),
        'customers' => (float)($target['current_customers'] ?? 0),
        'avg_transaction' => (float)($target['current_avg_transaction'] ?? 0),
        default => 0.00
    };
    return round($value, 2);
}

// Enhanced progress calculation
function calculateTargetProgress(float $current, float $target): array {
    if ($target <= 0) {
        return ['progress' => 0.00, 'status' => 'below'];
    }
    
    $progress = min(($current / $target) * 100, 999.99);
    $progress = round($progress, 2);
    
    $status = match(true) {
        $progress >= 100 => 'achieved',
        $progress >= 80 => 'near',
        default => 'below'
    };
    
    return ['progress' => $progress, 'status' => $status];
}

// Enhanced numeric filter
function filterNumeric($value, string $type = 'float'): float|int|false {
    if ($type === 'int') {
        return filter_var($value, FILTER_VALIDATE_INT);
    }
    return filter_var($value, FILTER_VALIDATE_FLOAT);
}

// ==================== REQUEST HANDLING ====================
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Rate limiting check (basic)
$requestKey = $userId . '_' . $action;
$cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($requestKey);
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 1) {
    jsonError('Too many requests. Please wait.', 429, 'rate_limit');
}
touch($cacheFile);

// ==================== KPI SUMMARY (ENHANCED) ====================
if ($action === 'kpi_summary') {
    try {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Optimized query with single execution
        $stmt = $pdo->prepare("
            SELECT 
                'today' as period,
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = :user_id AND date = :today
            UNION ALL
            SELECT 
                'yesterday' as period,
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = :user_id AND date = :yesterday
        ");
        
        $stmt->execute([':user_id' => $userId, ':today' => $today, ':yesterday' => $yesterday]);
        $results = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
        
        $todayData = $results['today'][0] ?? ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        $yesterdayData = $results['yesterday'][0] ?? ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        
        // Precise calculations
        $todaySales = round((float)$todayData['sales_volume'], 2);
        $yesterdaySales = round((float)$yesterdayData['sales_volume'], 2);
        $salesChange = percentageChange($todaySales, $yesterdaySales);
        
        $todayCustomers = (int)$todayData['customer_traffic'];
        $yesterdayCustomers = (int)$yesterdayData['customer_traffic'];
        $customersChange = percentageChange((float)$todayCustomers, (float)$yesterdayCustomers);
        
        $todayTransactions = (int)$todayData['receipt_count'];
        $yesterdayTransactions = (int)$yesterdayData['receipt_count'];
        $transactionsChange = percentageChange((float)$todayTransactions, (float)$yesterdayTransactions);
        
        // Get active target with enhanced query
        $stmtTarget = $pdo->prepare("
            SELECT 
                t.id, t.target_name, t.target_type, t.target_value,
                t.start_date, t.end_date,
                COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                COALESCE(SUM(cd.customer_traffic), 0) as current_customers,
                COALESCE(AVG(NULLIF(cd.sales_volume / NULLIF(cd.receipt_count, 0), 0)), 0) as current_avg_transaction
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE t.user_id = :user_id
                AND CURDATE() BETWEEN t.start_date AND t.end_date
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 1
        ");
        
        $stmtTarget->execute([':user_id' => $userId]);
        $targetData = $stmtTarget->fetch();
        
        $targetAchievement = 0.00;
        $targetStatus = 'No active target';
        
        if ($targetData) {
            $currentValue = getTargetCurrentValue($targetData);
            $targetValue = round((float)$targetData['target_value'], 2);
            $progressData = calculateTargetProgress($currentValue, $targetValue);
            $targetAchievement = $progressData['progress'];
            $targetStatus = sanitizeString($targetData['target_name']);
        }
        
        jsonSuccess([
            'today_sales' => $todaySales,
            'sales_change' => $salesChange,
            'today_customers' => $todayCustomers,
            'customers_change' => $customersChange,
            'today_transactions' => $todayTransactions,
            'transactions_change' => $transactionsChange,
            'target_achievement' => $targetAchievement,
            'target_status' => $targetStatus,
            'dates' => ['today' => $today, 'yesterday' => $yesterday]
        ]);
        
    } catch (Throwable $e) {
        error_log("KPI Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        jsonError('Failed to load KPI summary', 500, 'kpi_error');
    }
}

// ==================== DATA COMPARISON (ENHANCED) ====================
elseif ($action === 'compare') {
    try {
        $currentDate = $_GET['currentDate'] ?? date('Y-m-d');
        $compareDate = $_GET['compareDate'] ?? date('Y-m-d', strtotime('-1 day'));
        
        if (!validateDate($currentDate) || !validateDate($compareDate)) {
            jsonError('Invalid date format. Use YYYY-MM-DD', 422, 'invalid_date');
        }
        
        // Optimized single query for both periods
        $stmt = $pdo->prepare("
            SELECT 
                CASE WHEN date = :current THEN 'current' ELSE 'compare' END as period,
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = :user_id AND date IN (:current, :compare)
            GROUP BY period
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':current' => $currentDate,
            ':compare' => $compareDate
        ]);
        
        $results = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
        
        $currentData = $results['current'][0] ?? ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        $compareData = $results['compare'][0] ?? ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        
        // Precise metric calculations
        $currentSales = round((float)$currentData['sales_volume'], 2);
        $compareSales = round((float)$compareData['sales_volume'], 2);
        $currentReceipts = (int)$currentData['receipt_count'];
        $compareReceipts = (int)$compareData['receipt_count'];
        $currentCustomers = (int)$currentData['customer_traffic'];
        $compareCustomers = (int)$compareData['customer_traffic'];
        
        $currentAvgTrans = safeDivide($currentSales, (float)$currentReceipts, 2);
        $compareAvgTrans = safeDivide($compareSales, (float)$compareReceipts, 2);
        
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
        jsonError('Comparison failed', 500, 'compare_error');
    }
}

// ==================== GET TARGETS (ENHANCED) ====================
elseif ($action === 'get_targets') {
    try {
        $filter = $_GET['filter'] ?? 'all';
        $validFilters = ['all', 'active', 'achieved', 'near', 'below'];
        
        if (!in_array($filter, $validFilters, true)) {
            jsonError('Invalid filter parameter', 422, 'invalid_filter');
        }
        
        $whereClause = "t.user_id = :user_id";
        if ($filter === 'active') {
            $whereClause .= " AND CURDATE() BETWEEN t.start_date AND t.end_date";
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                t.id, t.target_name, t.target_type, t.target_value,
                t.start_date, t.end_date, t.store, t.created_at,
                COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                COALESCE(SUM(cd.customer_traffic), 0) as current_customers,
                COALESCE(AVG(NULLIF(cd.sales_volume / NULLIF(cd.receipt_count, 0), 0)), 0) as current_avg_transaction
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE {$whereClause}
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $targets = $stmt->fetchAll();
        
        // Process targets with precision
        $processedTargets = [];
        foreach ($targets as $target) {
            $currentValue = getTargetCurrentValue($target);
            $targetValue = round((float)$target['target_value'], 2);
            $progressData = calculateTargetProgress($currentValue, $targetValue);
            
            $processedTarget = [
                'id' => (int)$target['id'],
                'target_name' => sanitizeString($target['target_name']),
                'target_type' => $target['target_type'],
                'target_value' => $targetValue,
                'current_value' => $currentValue,
                'progress' => $progressData['progress'],
                'status' => $progressData['status'],
                'start_date' => $target['start_date'],
                'end_date' => $target['end_date'],
                'store' => sanitizeString($target['store'] ?? '')
            ];
            
            // Apply status filter
            if ($filter === 'all' || $processedTarget['status'] === $filter) {
                $processedTargets[] = $processedTarget;
            }
        }
        
        jsonSuccess(['targets' => $processedTargets]);
        
    } catch (Throwable $e) {
        error_log("Get Targets Error: " . $e->getMessage());
        jsonError('Failed to load targets', 500, 'targets_error');
    }
}

// ==================== SAVE TARGET (ENHANCED) ====================
elseif ($action === 'save_target' && $method === 'POST') {
    try {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonError('Invalid JSON data: ' . json_last_error_msg(), 422, 'invalid_json');
        }
        
        $name = sanitizeString($data['name'] ?? '', 100);
        $type = trim($data['type'] ?? '');
        $value = filterNumeric($data['value'] ?? 0, 'float');
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $store = sanitizeString($data['store'] ?? '', 100);
        
        // Comprehensive validation
        if (empty($name)) jsonError('Target name is required', 422, 'missing_name');
        if (strlen($name) < 3) jsonError('Target name must be at least 3 characters', 422, 'name_too_short');
        
        $validTypes = ['sales', 'customers', 'transactions', 'avg_transaction'];
        if (!in_array($type, $validTypes, true)) {
            jsonError('Invalid target type. Must be: ' . implode(', ', $validTypes), 422, 'invalid_type');
        }
        
        if ($value === false || $value <= 0 || $value > 999999999.99) {
            jsonError('Target value must be between 0.01 and 999,999,999.99', 422, 'invalid_value');
        }
        
        if (!validateDate($startDate)) jsonError('Invalid start date format', 422, 'invalid_start_date');
        if (!validateDate($endDate)) jsonError('Invalid end date format', 422, 'invalid_end_date');
        
        if (strtotime($endDate) < strtotime($startDate)) {
            jsonError('End date must be after or equal to start date', 422, 'invalid_date_range');
        }
        
        // Check for overlapping targets of same type
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) as count FROM targets 
            WHERE user_id = :user_id 
                AND target_type = :type
                AND (
                    (start_date BETWEEN :start AND :end)
                    OR (end_date BETWEEN :start AND :end)
                    OR (:start BETWEEN start_date AND end_date)
                )
        ");
        
        $checkStmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':start' => $startDate,
            ':end' => $endDate
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            jsonError('A target of this type already exists for the selected date range', 409, 'target_overlap');
        }
        
        // Insert with transaction
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO targets 
            (user_id, target_name, target_type, target_value, start_date, end_date, store, created_at)
            VALUES (:user_id, :name, :type, :value, :start_date, :end_date, :store, NOW())
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':name' => $name,
            ':type' => $type,
            ':value' => $value,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':store' => $store
        ]);
        
        $newId = (int)$pdo->lastInsertId();
        $pdo->commit();
        
        jsonSuccess([
            'id' => $newId,
            'message' => 'Target created successfully'
        ]);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Save Target Error: " . $e->getMessage());
        jsonError('Failed to save target', 500, 'save_error');
    }
}

// ==================== UPDATE TARGET (ENHANCED) ====================
elseif ($action === 'update_target' && $method === 'POST') {
    try {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        $id = filterNumeric($data['id'] ?? 0, 'int');
        $name = sanitizeString($data['name'] ?? '', 100);
        $type = trim($data['type'] ?? '');
        $value = filterNumeric($data['value'] ?? 0, 'float');
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $store = sanitizeString($data['store'] ?? '', 100);
        
        if ($id === false || $id <= 0) jsonError('Invalid target ID', 422, 'invalid_id');
        
        // Verify ownership
        $checkStmt = $pdo->prepare("SELECT id FROM targets WHERE id = :id AND user_id = :user_id");
        $checkStmt->execute([':id' => $id, ':user_id' => $userId]);
        
        if (!$checkStmt->fetch()) {
            jsonError('Target not found or access denied', 404, 'not_found');
        }
        
        // Same validation as save
        if (empty($name)) jsonError('Target name is required', 422, 'missing_name');
        
        $validTypes = ['sales', 'customers', 'transactions', 'avg_transaction'];
        if (!in_array($type, $validTypes, true)) jsonError('Invalid target type', 422, 'invalid_type');
        
        if ($value === false || $value <= 0) jsonError('Invalid target value', 422, 'invalid_value');
        if (!validateDate($startDate) || !validateDate($endDate)) jsonError('Invalid date format', 422, 'invalid_date');
        if (strtotime($endDate) < strtotime($startDate)) jsonError('Invalid date range', 422, 'invalid_range');
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            UPDATE targets 
            SET target_name = :name, target_type = :type, target_value = :value, 
                start_date = :start_date, end_date = :end_date, store = :store, updated_at = NOW()
            WHERE id = :id AND user_id = :user_id
        ");
        
        $stmt->execute([
            ':name' => $name,
            ':type' => $type,
            ':value' => $value,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':store' => $store,
            ':id' => $id,
            ':user_id' => $userId
        ]);
        
        $pdo->commit();
        
        jsonSuccess(['id' => $id, 'message' => 'Target updated successfully']);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Update Target Error: " . $e->getMessage());
        jsonError('Failed to update target', 500, 'update_error');
    }
}

// ==================== DELETE TARGET (ENHANCED) ====================
elseif ($action === 'delete_target') {
    try {
        $id = filterNumeric($_GET['id'] ?? 0, 'int');
        
        if ($id === false || $id <= 0) jsonError('Invalid target ID', 422, 'invalid_id');
        
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM targets WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            jsonError('Target not found or access denied', 404, 'not_found');
        }
        
        $pdo->commit();
        
        jsonSuccess(['message' => 'Target deleted successfully']);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Delete Target Error: " . $e->getMessage());
        jsonError('Failed to delete target', 500, 'delete_error');
    }
}

// ==================== TREND DATA (ENHANCED) ====================
elseif ($action === 'trend_data') {
    try {
        $days = filterNumeric($_GET['days'] ?? 30, 'int');
        $days = max(7, min(365, $days ?: 30)); // Extended to 1 year max
        
        $stmt = $pdo->prepare("
            SELECT 
                date,
                ROUND(COALESCE(sales_volume, 0), 2) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic,
                ROUND(COALESCE(
                    NULLIF(sales_volume / NULLIF(receipt_count, 0), 0), 0
                ), 2) as avg_transaction_value
            FROM churn_data
            WHERE user_id = :user_id
                AND date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                AND date <= CURDATE()
            ORDER BY date ASC
        ");
        
        $stmt->execute([':user_id' => $userId, ':days' => $days]);
        $trendData = $stmt->fetchAll();
        
        jsonSuccess([
            'trend_data' => $trendData,
            'period' => $days,
            'record_count' => count($trendData)
        ]);
        
    } catch (Throwable $e) {
        error_log("Trend Data Error: " . $e->getMessage());
        jsonError('Failed to load trend data', 500, 'trend_error');
    }
}

// ==================== INVALID ACTION ====================
else {
    jsonError('Invalid action parameter', 400, 'invalid_action');
}