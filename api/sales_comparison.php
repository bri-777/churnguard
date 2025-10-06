<?php
declare(strict_types=1);

// ==================== ULTRA-ENHANCED BACKEND API v3.0 ====================

// ==================== CONFIGURATION ====================
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/error.log');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '30');

// Enhanced Security Headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// CORS Configuration
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'https://yourdomain.com',
    'http://localhost',
    'http://localhost:3000'
];

if (in_array($origin, $allowedOrigins) || (strpos($origin, 'localhost') !== false && $_SERVER['SERVER_NAME'] === 'localhost')) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token, Authorization');
header('Access-Control-Max-Age: 3600');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ==================== DATABASE CONFIG ====================
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
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        PDO::MYSQL_ATTR_FOUND_ROWS => true
    ]
];

// ==================== VALIDATION CONSTANTS ====================
const VALIDATION = [
    'MIN_NAME_LENGTH' => 3,
    'MAX_NAME_LENGTH' => 100,
    'MIN_VALUE' => 0.01,
    'MAX_VALUE' => 999999999.99,
    'DATE_PATTERN' => '/^\d{4}-\d{2}-\d{2}$/',
    'MAX_DAYS_BACK' => 365,
    'VALID_TARGET_TYPES' => ['sales', 'customers', 'transactions', 'avg_transaction'],
    'VALID_FILTERS' => ['all', 'active', 'achieved', 'near', 'below']
];

// ==================== RATE LIMITING ====================
class RateLimiter {
    private const RATE_LIMIT = 100; // requests per minute
    private const CACHE_DIR = '/tmp/rate_limit_cache/';
    
    public static function check(string $identifier): bool {
        $cacheDir = self::CACHE_DIR;
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheFile = $cacheDir . md5($identifier);
        $now = time();
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            
            // Reset if window passed
            if ($now - $data['window_start'] >= 60) {
                $data = ['count' => 1, 'window_start' => $now];
            } else {
                $data['count']++;
                
                if ($data['count'] > self::RATE_LIMIT) {
                    return false;
                }
            }
        } else {
            $data = ['count' => 1, 'window_start' => $now];
        }
        
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }
    
    public static function cleanup(): void {
        $cacheDir = self::CACHE_DIR;
        if (!is_dir($cacheDir)) return;
        
        $files = glob($cacheDir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }
}

// ==================== CSRF PROTECTION ====================
class CSRFProtection {
    private const TOKEN_LENGTH = 32;
    private const TOKEN_LIFETIME = 3600; // 1 hour
    
    public static function generateToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    public static function validateToken(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // Check token age
        if (time() - $_SESSION['csrf_token_time'] > self::TOKEN_LIFETIME) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// ==================== ENHANCED SESSION MANAGEMENT ====================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '7200');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    
    session_name('SECURE_SESSION');
    session_start();
    
    // Session fixation protection
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['created'] = time();
        $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    // Validate session
    if (isset($_SESSION['ip']) && $_SESSION['ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        session_destroy();
        http_response_code(401);
        jsonError('Session validation failed', 401, 'session_invalid');
    }
    
    // Session timeout check
    if (isset($_SESSION['created']) && (time() - $_SESSION['created']) > 7200) {
        session_destroy();
        http_response_code(401);
        jsonError('Session expired', 401, 'session_expired');
    }
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    jsonResponse([
        'status' => 'error',
        'error' => 'unauthorized',
        'message' => 'Authentication required',
        'redirect' => '/login.php'
    ], 401);
}

$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
if ($userId === false || $userId <= 0) {
    session_destroy();
    http_response_code(401);
    jsonError('Invalid session data', 401, 'invalid_session');
}

// ==================== DATABASE CONNECTION ====================
class Database {
    private static ?PDO $pdo = null;
    private static array $queryLog = [];
    
    public static function connect(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=%s",
                DB_CONFIG['host'],
                DB_CONFIG['name'],
                DB_CONFIG['charset']
            );
            
            self::$pdo = new PDO($dsn, DB_CONFIG['user'], DB_CONFIG['pass'], DB_CONFIG['options']);
            
            // Connection verification
            self::$pdo->query("SELECT 1");
            
            return self::$pdo;
            
        } catch (PDOException $e) {
            Logger::error("Database connection failed: " . $e->getMessage());
            http_response_code(503);
            jsonError('Database service unavailable', 503, 'db_unavailable');
        }
    }
    
    public static function prepare(string $sql): PDOStatement {
        return self::connect()->prepare($sql);
    }
    
    public static function logQuery(string $sql, float $duration): void {
        self::$queryLog[] = [
            'sql' => $sql,
            'duration' => $duration,
            'timestamp' => microtime(true)
        ];
    }
    
    public static function getQueryLog(): array {
        return self::$queryLog;
    }
}

$pdo = Database::connect();

// ==================== LOGGER ====================
class Logger {
    private const LOG_DIR = __DIR__ . '/logs/';
    
    public static function error(string $message, array $context = []): void {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning(string $message, array $context = []): void {
        self::log('WARNING', $message, $context);
    }
    
    public static function info(string $message, array $context = []): void {
        self::log('INFO', $message, $context);
    }
    
    private static function log(string $level, string $message, array $context): void {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
        }
        
        $logFile = self::LOG_DIR . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
        
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// ==================== HELPER FUNCTIONS ====================

function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function jsonSuccess(array $data = []): void {
    jsonResponse(array_merge([
        'status' => 'success',
        'timestamp' => time(),
        'server_time' => date('c')
    ], $data));
}

function jsonError(string $message, int $code = 400, ?string $error = null, array $details = []): void {
    Logger::error($message, array_merge(['code' => $code, 'error' => $error], $details));
    
    $response = [
        'status' => 'error',
        'message' => $message,
        'timestamp' => time()
    ];
    
    if ($error) {
        $response['error'] = $error;
    }
    
    if (!empty($details) && defined('DEBUG_MODE') && DEBUG_MODE) {
        $response['details'] = $details;
    }
    
    jsonResponse($response, $code);
}

// Enhanced division with precision
function safeDivide(float $numerator, float $denominator, int $precision = 2): float {
    if ($denominator == 0 || !is_finite($denominator)) {
        return 0.00;
    }
    
    $result = $numerator / $denominator;
    
    if (!is_finite($result)) {
        return 0.00;
    }
    
    return round($result, $precision);
}

// Enhanced percentage calculation
function percentageChange(float $current, float $previous, int $precision = 2): float {
    if (!is_finite($current) || !is_finite($previous)) {
        return 0.00;
    }
    
    if ($previous == 0) {
        return $current > 0 ? 100.00 : 0.00;
    }
    
    if ($current == $previous) {
        return 0.00;
    }
    
    $change = (($current - $previous) / abs($previous)) * 100;
    
    // Cap at reasonable bounds
    $change = max(-999.99, min(999.99, $change));
    
    return round($change, $precision);
}

// Enhanced date validation
function validateDate(string $date): bool {
    if (!preg_match(VALIDATION['DATE_PATTERN'], $date)) {
        return false;
    }
    
    try {
        $d = DateTime::createFromFormat('Y-m-d', $date, new DateTimeZone('Asia/Manila'));
        return $d && $d->format('Y-m-d') === $date;
    } catch (Exception $e) {
        return false;
    }
}

// Enhanced string sanitization
function sanitizeString(?string $input, int $maxLength = 255): string {
    if ($input === null || $input === '') {
        return '';
    }
    
    // Remove control characters
    $cleaned = preg_replace('/[\x00-\x1F\x7F]/u', '', $input);
    
    // HTML entities encoding
    $cleaned = htmlspecialchars(strip_tags($cleaned), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Trim whitespace
    $cleaned = trim($cleaned);
    
    // Limit length
    return mb_substr($cleaned, 0, $maxLength, 'UTF-8');
}

// Enhanced numeric filter with bounds
function filterNumeric($value, string $type = 'float', ?float $min = null, ?float $max = null) {
    $filtered = $type === 'int' 
        ? filter_var($value, FILTER_VALIDATE_INT) 
        : filter_var($value, FILTER_VALIDATE_FLOAT);
    
    if ($filtered === false) {
        return false;
    }
    
    if ($min !== null && $filtered < $min) {
        return false;
    }
    
    if ($max !== null && $filtered > $max) {
        return false;
    }
    
    return $filtered;
}

// Get target current value
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

// Calculate target progress
function calculateTargetProgress(float $current, float $target): array {
    if ($target <= 0 || !is_finite($target)) {
        return ['progress' => 0.00, 'status' => 'below'];
    }
    
    $progress = ($current / $target) * 100;
    $progress = min(999.99, max(0, $progress));
    $progress = round($progress, 2);
    
    $status = match(true) {
        $progress >= 100 => 'achieved',
        $progress >= 80 => 'near',
        default => 'below'
    };
    
    return ['progress' => $progress, 'status' => $status];
}

// Input validation
function validateInput(array $rules, array $data): array {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? null;
        
        // Required check
        if (isset($rule['required']) && $rule['required'] && empty($value)) {
            $errors[] = ucfirst($field) . " is required";
            continue;
        }
        
        if (empty($value)) continue;
        
        // Type validation
        if (isset($rule['type'])) {
            switch ($rule['type']) {
                case 'string':
                    if (!is_string($value)) {
                        $errors[] = ucfirst($field) . " must be a string";
                    }
                    break;
                case 'int':
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $errors[] = ucfirst($field) . " must be an integer";
                    }
                    break;
                case 'float':
                    if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
                        $errors[] = ucfirst($field) . " must be a number";
                    }
                    break;
                case 'date':
                    if (!validateDate($value)) {
                        $errors[] = ucfirst($field) . " must be a valid date (YYYY-MM-DD)";
                    }
                    break;
            }
        }
        
        // Min/Max validation
        if (isset($rule['min']) && is_numeric($value) && $value < $rule['min']) {
            $errors[] = ucfirst($field) . " must be at least " . $rule['min'];
        }
        
        if (isset($rule['max']) && is_numeric($value) && $value > $rule['max']) {
            $errors[] = ucfirst($field) . " must not exceed " . $rule['max'];
        }
        
        // Min/Max length for strings
        if (isset($rule['minLength']) && is_string($value) && mb_strlen($value) < $rule['minLength']) {
            $errors[] = ucfirst($field) . " must be at least " . $rule['minLength'] . " characters";
        }
        
        if (isset($rule['maxLength']) && is_string($value) && mb_strlen($value) > $rule['maxLength']) {
            $errors[] = ucfirst($field) . " must not exceed " . $rule['maxLength'] . " characters";
        }
        
        // Pattern validation
        if (isset($rule['pattern']) && is_string($value) && !preg_match($rule['pattern'], $value)) {
            $errors[] = ucfirst($field) . " format is invalid";
        }
        
        // In array validation
        if (isset($rule['in']) && !in_array($value, $rule['in'], true)) {
            $errors[] = ucfirst($field) . " must be one of: " . implode(', ', $rule['in']);
        }
    }
    
    return $errors;
}

// ==================== REQUEST HANDLING ====================
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Rate limiting
$rateLimitKey = ($userId ?? 'anonymous') . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!RateLimiter::check($rateLimitKey)) {
    jsonError('Too many requests. Please try again later.', 429, 'rate_limit_exceeded');
}

// CSRF validation for POST requests
if ($method === 'POST') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!CSRFProtection::validateToken($csrfToken)) {
        Logger::warning('CSRF validation failed', ['user_id' => $userId, 'action' => $action]);
        jsonError('Invalid security token. Please refresh the page.', 403, 'csrf_invalid');
    }
}

// ==================== API ENDPOINTS ====================

// ==================== KPI SUMMARY ====================
if ($action === 'kpi_summary') {
    try {
        $startTime = microtime(true);
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Optimized query with UNION
        $stmt = Database::prepare("
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
        
        // Get active target
        $stmtTarget = Database::prepare("
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
        
        $duration = microtime(true) - $startTime;
        Database::logQuery('kpi_summary', $duration);
        
        jsonSuccess([
            'today_sales' => $todaySales,
            'sales_change' => $salesChange,
            'today_customers' => $todayCustomers,
            'customers_change' => $customersChange,
            'today_transactions' => $todayTransactions,
            'transactions_change' => $transactionsChange,
            'target_achievement' => $targetAchievement,
            'target_status' => $targetStatus,
            'dates' => [
                'today' => $today, 
                'yesterday' => $yesterday
            ],
            'performance' => [
                'query_time' => round($duration * 1000, 2) . 'ms'
            ]
        ]);
        
    } catch (Throwable $e) {
        Logger::error("KPI Error: " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        jsonError('Failed to load KPI summary', 500, 'kpi_error');
    }
}

// ==================== DATA COMPARISON ====================
elseif ($action === 'compare') {
    try {
        $startTime = microtime(true);
        
        $currentDate = $_GET['currentDate'] ?? date('Y-m-d');
        $compareDate = $_GET['compareDate'] ?? date('Y-m-d', strtotime('-1 day'));
        
        // Validation
        if (!validateDate($currentDate) || !validateDate($compareDate)) {
            jsonError('Invalid date format. Use YYYY-MM-DD', 422, 'invalid_date');
        }
        
        if ($currentDate === $compareDate) {
            jsonError('Please select different dates to compare', 422, 'same_dates');
        }
        
        // Check date range
        $dateDiff = abs(strtotime($currentDate) - strtotime($compareDate));
        $daysDiff = floor($dateDiff / 86400);
        
        if ($daysDiff > 365) {
            jsonError('Date range cannot exceed 365 days', 422, 'date_range_exceeded');
        }
        
        // Optimized query
        $stmt = Database::prepare("
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
        
        // Precise calculations
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
                'trend' => $currentSales >= $compareSales ? 'up' : 'down',
                'is_positive' => $currentSales >= $compareSales
            ],
            [
                'metric' => 'Transactions',
                'current' => $currentReceipts,
                'compare' => $compareReceipts,
                'difference' => $currentReceipts - $compareReceipts,
                'percentage' => percentageChange((float)$currentReceipts, (float)$compareReceipts),
                'trend' => $currentReceipts >= $compareReceipts ? 'up' : 'down',
                'is_positive' => $currentReceipts >= $compareReceipts
            ],
            [
                'metric' => 'Customer Traffic',
                'current' => $currentCustomers,
                'compare' => $compareCustomers,
                'difference' => $currentCustomers - $compareCustomers,
                'percentage' => percentageChange((float)$currentCustomers, (float)$compareCustomers),
                'trend' => $currentCustomers >= $compareCustomers ? 'up' : 'down',
                'is_positive' => $currentCustomers >= $compareCustomers
            ],
            [
                'metric' => 'Avg Transaction Value',
                'current' => $currentAvgTrans,
                'compare' => $compareAvgTrans,
                'difference' => round($currentAvgTrans - $compareAvgTrans, 2),
                'percentage' => percentageChange($currentAvgTrans, $compareAvgTrans),
                'trend' => $currentAvgTrans >= $compareAvgTrans ? 'up' : 'down',
                'is_positive' => $currentAvgTrans >= $compareAvgTrans
            ]
        ];
        
        $duration = microtime(true) - $startTime;
        Database::logQuery('compare', $duration);
        
        jsonSuccess([
            'comparison' => $metrics,
            'currentDate' => $currentDate,
            'compareDate' => $compareDate,
            'days_difference' => $daysDiff,
            'performance' => [
                'query_time' => round($duration * 1000, 2) . 'ms'
            ]
        ]);
        
    } catch (Throwable $e) {
        Logger::error("Comparison Error: " . $e->getMessage(), [
            'user_id' => $userId,
            'trace' => $e->getTraceAsString()
        ]);
        jsonError('Failed to load comparison data', 500, 'compare_error');
    }
}

// ==================== GET TARGETS ====================
elseif ($action === 'get_targets') {
    try {
        $startTime = microtime(true);
        
        $filter = $_GET['filter'] ?? 'all';
        
        if (!in_array($filter, VALIDATION['VALID_FILTERS'], true)) {
            jsonError('Invalid filter parameter', 422, 'invalid_filter', [
                'valid_filters' => VALIDATION['VALID_FILTERS']
            ]);
        }
        
        $whereClause = "t.user_id = :user_id";
        if ($filter === 'active') {
            $whereClause .= " AND CURDATE() BETWEEN t.start_date AND t.end_date";
        }
        
        $stmt = Database::prepare("
            SELECT 
                t.id, t.target_name, t.target_type, t.target_value,
                t.start_date, t.end_date, t.store, t.created_at, t.updated_at,
                COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                COALESCE(SUM(cd.customer_traffic), 0) as current_customers,
                COALESCE(AVG(NULLIF(cd.sales_volume / NULLIF(cd.receipt_count, 0), 0)), 0) as current_avg_transaction,
                DATEDIFF(t.end_date, CURDATE()) as days_remaining
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE {$whereClause}
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
        
        $stmt->execute([':user_id' => $userId]);
        $targets = $stmt->fetchAll();
        
        // Process targets
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
                'days_remaining' => max(0, (int)$target['days_remaining']),
                'store' => sanitizeString($target['store'] ?? ''),
                'created_at' => $target['created_at'],
                'updated_at' => $target['updated_at']
            ];
            
            // Apply status filter
            if ($filter === 'all' || $processedTarget['status'] === $filter) {
                $processedTargets[] = $processedTarget;
            }
        }
        
        $duration = microtime(true) - $startTime;
        Database::logQuery('get_targets', $duration);
        
        jsonSuccess([
            'targets' => $processedTargets,
            'total_count' => count($processedTargets),
            'filter' => $filter,
            'performance' => [
                'query_time' => round($duration * 1000, 2) . 'ms'
            ]
        ]);
        
    } catch (Throwable $e) {
        Logger::error("Get Targets Error: " . $e->getMessage(), [
            'user_id' => $userId,
            'filter' => $filter ?? 'unknown'
        ]);
        jsonError('Failed to load targets', 500, 'targets_error');
    }
}

// ==================== SAVE TARGET ====================
elseif ($action === 'save_target' && $method === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $rawInput = file_get_contents('php://input');
        if (empty($rawInput)) {
            jsonError('Empty request body', 422, 'empty_body');
        }
        
        $data = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonError('Invalid JSON: ' . json_last_error_msg(), 422, 'invalid_json');
        }
        
        // Validation rules
        $rules = [
            'name' => [
                'required' => true,
                'type' => 'string',
                'minLength' => VALIDATION['MIN_NAME_LENGTH'],
                'maxLength' => VALIDATION['MAX_NAME_LENGTH'],
                'pattern' => '/^[a-zA-Z0-9\s\-_]+$/'
            ],
            'type' => [
                'required' => true,
                'in' => VALIDATION['VALID_TARGET_TYPES']
            ],
            'value' => [
                'required' => true,
                'type' => 'float',
                'min' => VALIDATION['MIN_VALUE'],
                'max' => VALIDATION['MAX_VALUE']
            ],
            'start_date' => [
                'required' => true,
                'type' => 'date'
            ],
            'end_date' => [
                'required' => true,
                'type' => 'date'
            ],
            'store' => [
                'type' => 'string',
                'maxLength' => 100
            ]
        ];
        
        $errors = validateInput($rules, $data);
        
        if (!empty($errors)) {
            jsonError($errors[0], 422, 'validation_error', ['all_errors' => $errors]);
        }
        
        // Additional validation
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            jsonError('End date must be after or equal to start date', 422, 'invalid_date_range');
        }
        
        $name = sanitizeString($data['name'], 100);
        $type = $data['type'];
        $value = round((float)$data['value'], 2);
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];
        $store = sanitizeString($data['store'] ?? '', 100);
        
        // Check for overlapping targets
        $checkStmt = Database::prepare("
            SELECT COUNT(*) as count FROM targets 
            WHERE user_id = :user_id 
                AND target_type = :type
                AND (
                    (start_date BETWEEN :start AND :end)
                    OR (end_date BETWEEN :start AND :end)
                    OR (:start BETWEEN start_date AND end_date)
                    OR (:end BETWEEN start_date AND end_date)
                )
        ");
        
        $checkStmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':start' => $startDate,
            ':end' => $endDate
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $pdo->rollBack();
            jsonError(
                'A target of this type already exists for the selected date range', 
                409, 
                'target_overlap'
            );
        }
        
        // Insert target
        $stmt = Database::prepare("
            INSERT INTO targets 
            (user_id, target_name, target_type, target_value, start_date, end_date, store, created_at, updated_at)
            VALUES (:user_id, :name, :type, :value, :start_date, :end_date, :store, NOW(), NOW())
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
        
        Logger::info("Target created", [
            'user_id' => $userId,
            'target_id' => $newId,
            'type' => $type
        ]);
        
        jsonSuccess([
            'id' => $newId,
            'message' => 'Target created successfully'
        ]);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Logger::error("Save Target Error: " . $e->getMessage(), [
            'user_id' => $userId,
            'trace' => $e->getTraceAsString()
        ]);
        jsonError('Failed to save target', 500, 'save_error');
    }
}

// ==================== UPDATE TARGET ====================
elseif ($action === 'update_target' && $method === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            jsonError('Invalid JSON: ' . json_last_error_msg(), 422, 'invalid_json');
        }
        
        $id = filterNumeric($data['id'] ?? 0, 'int', 1);
        
        if ($id === false) {
            jsonError('Invalid target ID', 422, 'invalid_id');
        }
        
        // Verify ownership
        $checkStmt = Database::prepare("SELECT id FROM targets WHERE id = :id AND user_id = :user_id");
        $checkStmt->execute([':id' => $id, ':user_id' => $userId]);
        
        if (!$checkStmt->fetch()) {
            $pdo->rollBack();
            jsonError('Target not found or access denied', 404, 'not_found');
        }
        
        // Validation
        $rules = [
            'name' => [
                'required' => true,
                'type' => 'string',
                'minLength' => VALIDATION['MIN_NAME_LENGTH'],
                'maxLength' => VALIDATION['MAX_NAME_LENGTH'],
                'pattern' => '/^[a-zA-Z0-9\s\-_]+$/'
            ],
            'type' => [
                'required' => true,
                'in' => VALIDATION['VALID_TARGET_TYPES']
            ],
            'value' => [
                'required' => true,
                'type' => 'float',
                'min' => VALIDATION['MIN_VALUE'],
                'max' => VALIDATION['MAX_VALUE']
            ],
            'start_date' => [
                'required' => true,
                'type' => 'date'
            ],
            'end_date' => [
                'required' => true,
                'type' => 'date'
            ]
        ];
        
        $errors = validateInput($rules, $data);
        
        if (!empty($errors)) {
            $pdo->rollBack();
            jsonError($errors[0], 422, 'validation_error', ['all_errors' => $errors]);
        }
        
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            $pdo->rollBack();
            jsonError('End date must be after or equal to start date', 422, 'invalid_date_range');
        }
        
        $name = sanitizeString($data['name'], 100);
        $type = $data['type'];
        $value = round((float)$data['value'], 2);
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];
        $store = sanitizeString($data['store'] ?? '', 100);
        
        // Check for overlapping targets (excluding current)
        $checkStmt = Database::prepare("
            SELECT COUNT(*) as count FROM targets 
            WHERE user_id = :user_id 
                AND id != :id
                AND target_type = :type
                AND (
                    (start_date BETWEEN :start AND :end)
                    OR (end_date BETWEEN :start AND :end)
                    OR (:start BETWEEN start_date AND end_date)
                    OR (:end BETWEEN start_date AND end_date)
                )
        ");
        
        $checkStmt->execute([
            ':user_id' => $userId,
            ':id' => $id,
            ':type' => $type,
            ':start' => $startDate,
            ':end' => $endDate
        ]);
        
        if ($checkStmt->fetchColumn() > 0) {
            $pdo->rollBack();
            jsonError(
                'A target of this type already exists for the selected date range', 
                409, 
                'target_overlap'
            );
        }
        
        // Update target
        $stmt = Database::prepare("
            UPDATE targets 
            SET target_name = :name, 
                target_type = :type, 
                target_value = :value, 
                start_date = :start_date, 
                end_date = :end_date, 
                store = :store, 
                updated_at = NOW()
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
        
        Logger::info("Target updated", [
            'user_id' => $userId,
            'target_id' => $id,
            'type' => $type
        ]);
        
        jsonSuccess([
            'id' => $id,
            'message' => 'Target updated successfully'
        ]);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Logger::error("Update Target Error: " . $e->getMessage(), [
            'user_id' => $userId,
            'target_id' => $id ?? 'unknown'
        ]);
        jsonError('Failed to update target', 500, 'update_error');
    }
}

// ==================== DELETE TARGET ====================
elseif ($action === 'delete_target') {
    try {
        $id = filterNumeric($_GET['id'] ?? 0, 'int', 1);
        
        if ($id === false) {
            jsonError('Invalid target ID', 422, 'invalid_id');
        }
        
        $pdo->beginTransaction();
        
        // Soft delete approach - verify ownership first
        $checkStmt = Database::prepare("
            SELECT target_name FROM targets 
            WHERE id = :id AND user_id = :user_id
        ");
        $checkStmt->execute([':id' => $id, ':user_id' => $userId]);
        $target = $checkStmt->fetch();
        
        if (!$target) {
            $pdo->rollBack();
            jsonError('Target not found or access denied', 404, 'not_found');
        }
        
        // Delete target
        $stmt = Database::prepare("DELETE FROM targets WHERE id = :id AND user_id = :user_id");
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
        
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            jsonError('Failed to delete target', 500, 'delete_failed');
        }
        
        $pdo->commit();
        
        Logger::info("Target deleted", [
            'user_id' => $userId,
            'target_id' => $id,
            'target_name' => $target['target_name']
        ]);
        
        jsonSuccess([
            'message' => 'Target deleted successfully',
            'deleted_id' => $id
        ]);
        
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        Logger::error("Delete Target Error: " . $e->getMessage(), [
            'user_id' => $userId,
            'target_id' => $id ?? 'unknown'
        ]);
        jsonError('Failed to delete target', 500, 'delete_error');
    }
}

// ==================== TREND DATA ====================
elseif ($action === 'trend_data') {
    try {
        $startTime = microtime(true);
        
        $days = filterNumeric($_GET['days'] ?? 30, 'int', 1, VALIDATION['MAX_DAYS_BACK']);
        
        if ($days === false) {
            jsonError('Invalid days parameter', 422, 'invalid_days', [
                'valid_range' => '1-' . VALIDATION['MAX_DAYS_BACK']
            ]);
        }
        
        $stmt = Database::prepare("
            SELECT 
                date,
                ROUND(COALESCE(sales_volume, 0), 2) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic,
                ROUND(COALESCE(
                    NULLIF(sales_volume / NULLIF(receipt_count, 0), 0), 0
                ), 2) as avg_transaction_value,
                store_name
            FROM churn_data
            WHERE user_id = :user_id
                AND date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                AND date <= CURDATE()
            ORDER BY date ASC
        ");
        
        $stmt->execute([':user_id' => $userId, ':days' => $days]);
        $trendData = $stmt->fetchAll();
        
        // Calculate statistics
        $totalSales = 0;
        $totalReceipts = 0;
        $totalCustomers = 0;
        
        foreach ($trendData as $item) {
            $totalSales += (float)$item['sales_volume'];
            $totalReceipts += (int)$item['receipt_count'];
            $totalCustomers += (int)$item['customer_traffic'];
        }
        
        $avgDailySales = count($trendData) > 0 ? round($totalSales / count($trendData), 2) : 0;
        $avgDailyReceipts = count($trendData) > 0 ? round($totalReceipts / count($trendData), 0) : 0;
        $avgDailyCustomers = count($trendData) > 0 ? round($totalCustomers / count($trendData), 0) : 0;
        
        $duration = microtime(true) - $startTime;
        Database::logQuery('trend_data', $duration);
        
        jsonSuccess([
            'trend_data' => $trendData,
            'period' => $days,
            'record_count' => count($trendData),
            'statistics' => [
                'total_sales' => round($totalSales, 2),
                'total_receipts' => $totalReceipts,
                'total_customers' => $totalCustomers,
                'avg_daily_sales' => $avgDailySales,
                'avg_daily_receipts' => $avgDailyReceipts,
                'avg_daily_customers' => $avgDailyCustomers
            ],
            'performance' => [
                'query_time' => round($duration * 1000, 2) . 'ms'
            ]
        ]);
        
    } catch (Throwable $e) {
        Logger::error("Trend Data Error: " . $e->getMessage(), [
            'user_id' => $userId,
            'days' => $days ?? 'unknown'
        ]);
        jsonError('Failed to load trend data', 500, 'trend_error');
    }
}

// ==================== GET CSRF TOKEN ====================
elseif ($action === 'get_csrf_token') {
    try {
        $token = CSRFProtection::generateToken();
        jsonSuccess([
            'token' => $token,
            'expires_in' => 3600
        ]);
    } catch (Throwable $e) {
        Logger::error("CSRF Token Error: " . $e->getMessage());
        jsonError('Failed to generate token', 500, 'token_error');
    }
}

// ==================== EXPORT DATA ====================
elseif ($action === 'export_data') {
    try {
        $type = $_GET['type'] ?? 'trend';
        $format = $_GET['format'] ?? 'csv';
        
        if (!in_array($type, ['trend', 'targets', 'comparison'], true)) {
            jsonError('Invalid export type', 422, 'invalid_type');
        }
        
        if (!in_array($format, ['csv', 'json'], true)) {
            jsonError('Invalid export format', 422, 'invalid_format');
        }
        
        // Get data based on type
        $data = [];
        switch ($type) {
            case 'trend':
                $days = filterNumeric($_GET['days'] ?? 30, 'int', 1, 365) ?: 30;
                $stmt = Database::prepare("
                    SELECT date, sales_volume, receipt_count, customer_traffic
                    FROM churn_data
                    WHERE user_id = :user_id
                        AND date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                    ORDER BY date ASC
                ");
                $stmt->execute([':user_id' => $userId, ':days' => $days]);
                $data = $stmt->fetchAll();
                break;
                
            case 'targets':
                $stmt = Database::prepare("
                    SELECT target_name, target_type, target_value, 
                           start_date, end_date, store
                    FROM targets
                    WHERE user_id = :user_id
                    ORDER BY created_at DESC
                ");
                $stmt->execute([':user_id' => $userId]);
                $data = $stmt->fetchAll();
                break;
        }
        
        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Y-m-d') . '.json"');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Y-m-d') . '.csv"');
            
            if (!empty($data)) {
                $output = fopen('php://output', 'w');
                fputcsv($output, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
            }
        }
        exit;
        
    } catch (Throwable $e) {
        Logger::error("Export Error: " . $e->getMessage());
        jsonError('Failed to export data', 500, 'export_error');
    }
}

// ==================== INVALID ACTION ====================
else {
    Logger::warning('Invalid action attempted', [
        'action' => $action,
        'method' => $method,
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    jsonError('Invalid action parameter', 400, 'invalid_action', [
        'valid_actions' => [
            'kpi_summary',
            'compare',
            'get_targets',
            'save_target',
            'update_target',
            'delete_target',
            'trend_data',
            'get_csrf_token',
            'export_data'
        ]
    ]);
}

// ==================== CLEANUP ====================
register_shutdown_function(function() {
    // Log performance metrics
    $queries = Database::getQueryLog();
    if (count($queries) > 5) {
        Logger::warning('High query count', [
            'query_count' => count($queries),
            'total_time' => array_sum(array_column($queries, 'duration'))
        ]);
    }
    
    // Cleanup rate limiter cache
    if (rand(1, 100) === 1) { // 1% chance
        RateLimiter::cleanup();
    }
});

// ==================== ERROR HANDLER ====================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    Logger::error("PHP Error: $errstr", [
        'file' => $errfile,
        'line' => $errline,
        'errno' => $errno
    ]);
    
    if (error_reporting() & $errno) {
        jsonError('Internal server error', 500, 'internal_error');
    }
    
    return true;
});

// ==================== EXCEPTION HANDLER ====================
set_exception_handler(function($exception) {
    Logger::error("Uncaught Exception: " . $exception->getMessage(), [
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    jsonError('An unexpected error occurred', 500, 'uncaught_exception');
});