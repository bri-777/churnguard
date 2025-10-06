<?php
declare(strict_types=1);

// ==================== ULTRA-ENHANCED SALES API v3.0 ====================
// Production-ready, enterprise-grade backend

// ==================== CONFIGURATION ====================
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/api_errors.log');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '30');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u393812660_churnguard');
define('DB_USER', 'u393812660_churnguard');
define('DB_PASS', '102202Brian_');
define('DB_CHARSET', 'utf8mb4');

// Security & Performance Constants
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60);
define('CACHE_TTL', 300);
define('SESSION_LIFETIME', 7200);
define('MAX_RETRIES', 3);

// ==================== SECURITY HEADERS ====================
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// CORS with validation
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = ['http://localhost', 'http://localhost:3000', 'https://yourdomain.com'];

foreach ($allowedOrigins as $allowed) {
    if (strpos($origin, $allowed) === 0) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        break;
    }
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
header('Access-Control-Max-Age: 3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ==================== RATE LIMITER ====================
class RateLimiter {
    private static string $cacheDir = '/tmp/rate_limit/';
    
    public static function check(string $identifier): bool {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        $file = self::$cacheDir . md5($identifier);
        $now = time();
        
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            
            if ($now - $data['window_start'] >= RATE_LIMIT_WINDOW) {
                $data = ['count' => 1, 'window_start' => $now];
            } else {
                $data['count']++;
                if ($data['count'] > RATE_LIMIT_REQUESTS) {
                    return false;
                }
            }
        } else {
            $data = ['count' => 1, 'window_start' => $now];
        }
        
        file_put_contents($file, json_encode($data), LOCK_EX);
        return true;
    }
}

// ==================== LOGGER ====================
class Logger {
    private static string $logDir = __DIR__ . '/logs/';
    
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
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
        
        $logFile = self::$logDir . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// ==================== CACHE MANAGER ====================
class Cache {
    private static string $cacheDir = '/tmp/api_cache/';
    
    public static function get(string $key) {
        $file = self::$cacheDir . md5($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($file), true);
        
        if (time() > $data['expires']) {
            unlink($file);
            return null;
        }
        
        return $data['value'];
    }
    
    public static function set(string $key, $value, int $ttl = CACHE_TTL): void {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        $file = self::$cacheDir . md5($key);
        $data = [
            'value' => $value,
            'expires' => time() + $ttl
        ];
        
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    public static function delete(string $key): void {
        $file = self::$cacheDir . md5($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    public static function clear(string $pattern = ''): void {
        if (!is_dir(self::$cacheDir)) return;
        
        $files = glob(self::$cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                if (empty($pattern) || strpos(basename($file), md5($pattern)) !== false) {
                    unlink($file);
                }
            }
        }
    }
}

// ==================== DATABASE CLASS ====================
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
                DB_HOST, DB_NAME, DB_CHARSET
            );
            
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_PERSISTENT => false
            ]);
            
            // Test connection
            self::$pdo->query("SELECT 1");
            
            return self::$pdo;
            
        } catch (PDOException $e) {
            Logger::error("Database connection failed", [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            http_response_code(503);
            Response::error('Database service unavailable', 503);
        }
    }
    
    public static function prepare(string $sql): PDOStatement {
        return self::connect()->prepare($sql);
    }
    
    public static function logQuery(string $sql, float $duration): void {
        self::$queryLog[] = [
            'sql' => $sql,
            'duration' => round($duration * 1000, 2),
            'timestamp' => microtime(true)
        ];
    }
    
    public static function getQueryLog(): array {
        return self::$queryLog;
    }
}

// ==================== RESPONSE HANDLER ====================
class Response {
    public static function success(array $data = []): void {
        http_response_code(200);
        echo json_encode(
            array_merge(['status' => 'success', 'timestamp' => time()], $data),
            JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_PRESERVE_ZERO_FRACTION
        );
        exit;
    }
    
    public static function error(string $message, int $code = 400, ?string $errorType = null): void {
        http_response_code($code);
        
        $response = [
            'status' => 'error',
            'message' => $message,
            'timestamp' => time()
        ];
        
        if ($errorType) {
            $response['error'] = $errorType;
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ==================== VALIDATOR ====================
class Validator {
    public static function validateDate(string $date): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }
        
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    public static function validateInt($value, int $min = null, int $max = null): bool {
        $val = filter_var($value, FILTER_VALIDATE_INT);
        if ($val === false) return false;
        if ($min !== null && $val < $min) return false;
        if ($max !== null && $val > $max) return false;
        return true;
    }
    
    public static function validateFloat($value, float $min = null, float $max = null): bool {
        $val = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($val === false) return false;
        if ($min !== null && $val < $min) return false;
        if ($max !== null && $val > $max) return false;
        return true;
    }
    
    public static function validateString(string $value, int $minLen = 1, int $maxLen = 255): bool {
        $len = mb_strlen(trim($value));
        return $len >= $minLen && $len <= $maxLen;
    }
    
    public static function validateEnum(string $value, array $allowed): bool {
        return in_array($value, $allowed, true);
    }
    
    public static function sanitize(string $value, int $maxLen = 255): string {
        $cleaned = trim(htmlspecialchars(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return mb_substr($cleaned, 0, $maxLen, 'UTF-8');
    }
}

// ==================== MATH UTILITIES ====================
class MathUtils {
    public static function safeDivide(float $numerator, float $denominator, int $precision = 2): float {
        if ($denominator == 0 || !is_finite($denominator)) {
            return 0.00;
        }
        $result = $numerator / $denominator;
        return is_finite($result) ? round($result, $precision) : 0.00;
    }
    
    public static function percentageChange(float $current, float $previous, int $precision = 2): float {
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
        $bounded = max(-999.99, min(999.99, $change));
        return round($bounded, $precision);
    }
}


// ==================== SESSION MANAGER ====================
class SessionManager {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
            
            session_name('SALES_SESSION');
            session_start();
            
            self::validateSession();
        }
    }
    
    private static function validateSession(): void {
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['created_at'] = time();
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        // IP validation
        if (isset($_SESSION['ip']) && $_SESSION['ip'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
            self::destroy();
            Response::error('Session validation failed', 401, 'session_invalid');
        }
        
        // Session timeout
        if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at']) > SESSION_LIFETIME) {
            self::destroy();
            Response::error('Session expired', 401, 'session_expired');
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
    }
    
    public static function getUserId(): int {
        if (!isset($_SESSION['user_id'])) {
            Response::error('Authentication required', 401, 'unauthorized');
        }
        
        $userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);
        if ($userId === false || $userId <= 0) {
            self::destroy();
            Response::error('Invalid session data', 401, 'invalid_session');
        }
        
        return $userId;
    }
    
    public static function destroy(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

// ==================== TARGET SERVICE ====================
class TargetService {
    private PDO $pdo;
    
    public function __construct() {
        $this->pdo = Database::connect();
    }
    
    public function calculateProgress(array $target): array {
        $currentValue = $this->getCurrentValue($target);
        $targetValue = round((float)$target['target_value'], 2);
        
        if ($targetValue <= 0) {
            return ['progress' => 0.00, 'status' => 'below'];
        }
        
        $progress = min(($currentValue / $targetValue) * 100, 999.99);
        $progress = round($progress, 2);
        
        $status = match(true) {
            $progress >= 100 => 'achieved',
            $progress >= 80 => 'near',
            default => 'below'
        };
        
        return ['progress' => $progress, 'status' => $status];
    }
    
    private function getCurrentValue(array $target): float {
        $value = match($target['target_type']) {
            'sales' => (float)($target['current_sales'] ?? 0),
            'transactions' => (float)($target['current_receipts'] ?? 0),
            'customers' => (float)($target['current_customers'] ?? 0),
            'avg_transaction' => (float)($target['current_avg_transaction'] ?? 0),
            default => 0.00
        };
        
        return round($value, 2);
    }
    
    public function getAll(int $userId, string $filter = 'all'): array {
        $validFilters = ['all', 'active', 'achieved', 'near', 'below'];
        if (!in_array($filter, $validFilters, true)) {
            throw new InvalidArgumentException('Invalid filter');
        }
        
        // Check cache
        $cacheKey = "targets_{$userId}_{$filter}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $where = "t.user_id = ?";
        if ($filter === 'active') {
            $where .= " AND CURDATE() BETWEEN t.start_date AND t.end_date";
        }
        
        $startTime = microtime(true);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                t.id, t.target_name, t.target_type, t.target_value,
                t.start_date, t.end_date, t.store, t.created_at,
                COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                COALESCE(SUM(cd.customer_traffic), 0) as current_customers,
                COALESCE(AVG(cd.sales_volume / NULLIF(cd.receipt_count, 0)), 0) as current_avg_transaction,
                DATEDIFF(t.end_date, CURDATE()) as days_remaining
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE {$where}
            GROUP BY t.id
            ORDER BY t.created_at DESC
        ");
        
        $stmt->execute([$userId]);
        $targets = $stmt->fetchAll();
        
        Database::logQuery('get_targets', microtime(true) - $startTime);
        
        $processed = [];
        foreach ($targets as $target) {
            $currentValue = $this->getCurrentValue($target);
            $progressData = $this->calculateProgress($target);
            
            if ($filter === 'all' || $progressData['status'] === $filter) {
                $processed[] = [
                    'id' => (int)$target['id'],
                    'target_name' => Validator::sanitize($target['target_name']),
                    'target_type' => $target['target_type'],
                    'target_value' => round((float)$target['target_value'], 2),
                    'current_value' => $currentValue,
                    'progress' => $progressData['progress'],
                    'status' => $progressData['status'],
                    'start_date' => $target['start_date'],
                    'end_date' => $target['end_date'],
                    'days_remaining' => max(0, (int)$target['days_remaining']),
                    'store' => Validator::sanitize($target['store'] ?? ''),
                    'created_at' => $target['created_at']
                ];
            }
        }
        
        // Cache results
        Cache::set($cacheKey, $processed, 180); // 3 minutes
        
        return $processed;
    }
    
    public function create(int $userId, array $data): int {
        $this->validateTargetData($data);
        
        // Check for overlaps
        if ($this->hasOverlap($userId, $data['type'], $data['start_date'], $data['end_date'])) {
            throw new RuntimeException('A target of this type already exists for the selected date range');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO targets 
                (user_id, target_name, target_type, target_value, start_date, end_date, store, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                Validator::sanitize($data['name']),
                $data['type'],
                $data['value'],
                $data['start_date'],
                $data['end_date'],
                Validator::sanitize($data['store'] ?? '')
            ]);
            
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            
            // Clear cache
            Cache::clear('targets_' . $userId);
            
            Logger::info("Target created", ['user_id' => $userId, 'target_id' => $id]);
            
            return $id;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            Logger::error("Create target failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function update(int $userId, int $id, array $data): void {
        $this->validateTargetData($data);
        
        // Verify ownership
        if (!$this->verifyOwnership($userId, $id)) {
            throw new RuntimeException('Target not found');
        }
        
        // Check for overlaps (excluding current)
        if ($this->hasOverlap($userId, $data['type'], $data['start_date'], $data['end_date'], $id)) {
            throw new RuntimeException('A target of this type already exists for the selected date range');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("
                UPDATE targets 
                SET target_name = ?, target_type = ?, target_value = ?, 
                    start_date = ?, end_date = ?, store = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([
                Validator::sanitize($data['name']),
                $data['type'],
                $data['value'],
                $data['start_date'],
                $data['end_date'],
                Validator::sanitize($data['store'] ?? ''),
                $id,
                $userId
            ]);
            
            $this->pdo->commit();
            
            // Clear cache
            Cache::clear('targets_' . $userId);
            
            Logger::info("Target updated", ['user_id' => $userId, 'target_id' => $id]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            Logger::error("Update target failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function delete(int $userId, int $id): void {
        if (!$this->verifyOwnership($userId, $id)) {
            throw new RuntimeException('Target not found');
        }
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            
            $this->pdo->commit();
            
            // Clear cache
            Cache::clear('targets_' . $userId);
            
            Logger::info("Target deleted", ['user_id' => $userId, 'target_id' => $id]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            Logger::error("Delete target failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    private function validateTargetData(array $data): void {
        if (!Validator::validateString($data['name'] ?? '', 3, 100)) {
            throw new InvalidArgumentException('Target name must be 3-100 characters');
        }
        
        if (!Validator::validateEnum($data['type'] ?? '', ['sales', 'customers', 'transactions', 'avg_transaction'])) {
            throw new InvalidArgumentException('Invalid target type');
        }
        
        if (!Validator::validateFloat($data['value'] ?? 0, 0.01, 999999999.99)) {
            throw new InvalidArgumentException('Target value must be between 0.01 and 999,999,999.99');
        }
        
        if (!Validator::validateDate($data['start_date'] ?? '')) {
            throw new InvalidArgumentException('Invalid start date');
        }
        
        if (!Validator::validateDate($data['end_date'] ?? '')) {
            throw new InvalidArgumentException('Invalid end date');
        }
        
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            throw new InvalidArgumentException('End date must be after start date');
        }
    }
    
    private function verifyOwnership(int $userId, int $id): bool {
        $stmt = $this->pdo->prepare("SELECT id FROM targets WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() !== false;
    }
    
    private function hasOverlap(int $userId, string $type, string $startDate, string $endDate, ?int $excludeId = null): bool {
        $sql = "
            SELECT COUNT(*) as count FROM targets 
            WHERE user_id = ? AND target_type = ?
                AND (
                    (start_date BETWEEN ? AND ?)
                    OR (end_date BETWEEN ? AND ?)
                    OR (? BETWEEN start_date AND end_date)
                    OR (? BETWEEN start_date AND end_date)
                )
        ";
        
        $params = [$userId, $type, $startDate, $endDate, $startDate, $endDate, $startDate, $endDate];
        
        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
}


// ==================== DATA SERVICE ====================
class DataService {
    private PDO $pdo;
    
    public function __construct() {
        $this->pdo = Database::connect();
    }
    
    public function getKPISummary(int $userId): array {
        // Check cache
        $cacheKey = "kpi_summary_{$userId}_" . date('Y-m-d');
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $startTime = microtime(true);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                'today' as period,
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
            UNION ALL
            SELECT 
                'yesterday' as period,
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        
        $stmt->execute([$userId, $today, $userId, $yesterday]);
        $results = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
        
        Database::logQuery('kpi_summary', microtime(true) - $startTime);
        
        $todayData = $results['today'][0] ?? ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        $yesterdayData = $results['yesterday'][0] ?? ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        
        $todaySales = round((float)$todayData['sales_volume'], 2);
        $yesterdaySales = round((float)$yesterdayData['sales_volume'], 2);
        $todayCustomers = (int)$todayData['customer_traffic'];
        $yesterdayCustomers = (int)$yesterdayData['customer_traffic'];
        $todayTransactions = (int)$todayData['receipt_count'];
        $yesterdayTransactions = (int)$yesterdayData['receipt_count'];
        
        // Get active target
        $targetService = new TargetService();
        $targets = $targetService->getAll($userId, 'active');
        
        $targetAchievement = 0.00;
        $targetStatus = 'No active target';
        
        if (!empty($targets)) {
            $target = $targets[0];
            $targetAchievement = $target['progress'];
            $targetStatus = $target['target_name'];
        }
        
        $result = [
            'today_sales' => $todaySales,
            'sales_change' => MathUtils::percentageChange($todaySales, $yesterdaySales),
            'today_customers' => $todayCustomers,
            'customers_change' => MathUtils::percentageChange((float)$todayCustomers, (float)$yesterdayCustomers),
            'today_transactions' => $todayTransactions,
            'transactions_change' => MathUtils::percentageChange((float)$todayTransactions, (float)$yesterdayTransactions),
            'target_achievement' => round($targetAchievement, 2),
            'target_status' => $targetStatus,
            'dates' => ['today' => $today, 'yesterday' => $yesterday]
        ];
        
        // Cache for 5 minutes
        Cache::set($cacheKey, $result, 300);
        
        return $result;
    }
    
    public function getComparison(int $userId, string $currentDate, string $compareDate): array {
        if (!Validator::validateDate($currentDate) || !Validator::validateDate($compareDate)) {
            throw new InvalidArgumentException('Invalid date format');
        }
        
        if ($currentDate === $compareDate) {
            throw new InvalidArgumentException('Dates must be different');
        }
        
        // Check cache
        $cacheKey = "comparison_{$userId}_{$currentDate}_{$compareDate}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $startTime = microtime(true);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                CASE WHEN date = ? THEN 'current' ELSE 'compare' END as period,
                COALESCE(SUM(sales_volume), 0) as sales_volume,
                COALESCE(SUM(receipt_count), 0) as receipt_count,
                COALESCE(SUM(customer_traffic), 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date IN (?, ?)
            GROUP BY period
        ");
        
        $stmt->execute([$currentDate, $userId, $currentDate, $compareDate]);
        $results = $stmt->fetchAll(PDO::FETCH_GROUP|PDO::FETCH_ASSOC);
        
        Database::logQuery('comparison', microtime(true) - $startTime);
        
        $currentData = $results['current'][0] ?? ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        $compareData = $results['compare'][0] ?? ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        
        $currentSales = round((float)$currentData['sales_volume'], 2);
        $compareSales = round((float)$compareData['sales_volume'], 2);
        $currentReceipts = (int)$currentData['receipt_count'];
        $compareReceipts = (int)$compareData['receipt_count'];
        $currentCustomers = (int)$currentData['customer_traffic'];
        $compareCustomers = (int)$compareData['customer_traffic'];
        
        $currentAvgTrans = MathUtils::safeDivide($currentSales, (float)$currentReceipts);
        $compareAvgTrans = MathUtils::safeDivide($compareSales, (float)$compareReceipts);
        
        $metrics = [
            [
                'metric' => 'Sales Revenue',
                'current' => $currentSales,
                'compare' => $compareSales,
                'difference' => round($currentSales - $compareSales, 2),
                'percentage' => MathUtils::percentageChange($currentSales, $compareSales),
                'trend' => $currentSales >= $compareSales ? 'up' : 'down'
            ],
            [
                'metric' => 'Transactions',
                'current' => $currentReceipts,
                'compare' => $compareReceipts,
                'difference' => $currentReceipts - $compareReceipts,
                'percentage' => MathUtils::percentageChange((float)$currentReceipts, (float)$compareReceipts),
                'trend' => $currentReceipts >= $compareReceipts ? 'up' : 'down'
            ],
            [
                'metric' => 'Customer Traffic',
                'current' => $currentCustomers,
                'compare' => $compareCustomers,
                'difference' => $currentCustomers - $compareCustomers,
                'percentage' => MathUtils::percentageChange((float)$currentCustomers, (float)$compareCustomers),
                'trend' => $currentCustomers >= $compareCustomers ? 'up' : 'down'
            ],
            [
                'metric' => 'Avg Transaction Value',
                'current' => $currentAvgTrans,
                'compare' => $compareAvgTrans,
                'difference' => round($currentAvgTrans - $compareAvgTrans, 2),
                'percentage' => MathUtils::percentageChange($currentAvgTrans, $compareAvgTrans),
                'trend' => $currentAvgTrans >= $compareAvgTrans ? 'up' : 'down'
            ]
        ];
        
        $result = [
            'comparison' => $metrics,
            'currentDate' => $currentDate,
            'compareDate' => $compareDate
        ];
        
        // Cache for 1 hour
        Cache::set($cacheKey, $result, 3600);
        
        return $result;
    }
    
    public function getTrendData(int $userId, int $days = 30): array {
        if (!Validator::validateInt($days, 1, 365)) {
            throw new InvalidArgumentException('Days must be between 1 and 365');
        }
        
        // Check cache
        $cacheKey = "trend_data_{$userId}_{$days}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        $startTime = microtime(true);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                date,
                ROUND(COALESCE(sales_volume, 0), 2) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data
            WHERE user_id = ? 
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND date <= CURDATE()
            ORDER BY date ASC
        ");
        
        $stmt->execute([$userId, $days]);
        $data = $stmt->fetchAll();
        
        Database::logQuery('trend_data', microtime(true) - $startTime);
        
        // Calculate statistics
        $totalSales = 0;
        $totalReceipts = 0;
        $totalCustomers = 0;
        
        foreach ($data as $item) {
            $totalSales += (float)$item['sales_volume'];
            $totalReceipts += (int)$item['receipt_count'];
            $totalCustomers += (int)$item['customer_traffic'];
        }
        
        $count = count($data);
        $avgDailySales = $count > 0 ? round($totalSales / $count, 2) : 0;
        $avgDailyReceipts = $count > 0 ? round($totalReceipts / $count, 0) : 0;
        $avgDailyCustomers = $count > 0 ? round($totalCustomers / $count, 0) : 0;
        
        $result = [
            'trend_data' => $data,
            'period' => $days,
            'record_count' => $count,
            'statistics' => [
                'total_sales' => round($totalSales, 2),
                'total_receipts' => $totalReceipts,
                'total_customers' => $totalCustomers,
                'avg_daily_sales' => $avgDailySales,
                'avg_daily_receipts' => $avgDailyReceipts,
                'avg_daily_customers' => $avgDailyCustomers
            ]
        ];
        
        // Cache for 10 minutes
        Cache::set($cacheKey, $result, 600);
        
        return $result;
    }
}

// ==================== REQUEST HANDLER ====================
class RequestHandler {
    private int $userId;
    private DataService $dataService;
    private TargetService $targetService;
    
    public function __construct(int $userId) {
        $this->userId = $userId;
        $this->dataService = new DataService();
        $this->targetService = new TargetService();
    }
    
    public function handleRequest(string $action, string $method): void {
        try {
            match($action) {
                'kpi_summary' => $this->handleKPISummary(),
                'compare' => $this->handleComparison(),
                'get_targets' => $this->handleGetTargets(),
                'save_target' => $method === 'POST' ? $this->handleSaveTarget() : throw new Exception('Method not allowed'),
                'update_target' => $method === 'POST' ? $this->handleUpdateTarget() : throw new Exception('Method not allowed'),
                'delete_target' => $this->handleDeleteTarget(),
                'trend_data' => $this->handleTrendData(),
                default => throw new Exception('Invalid action')
            };
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422, 'validation_error');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 409, 'conflict');
        } catch (Exception $e) {
            Logger::error("Request handler error", [
                'action' => $action,
                'error' => $e->getMessage(),
                'user_id' => $this->userId
            ]);
            Response::error('An error occurred', 500, 'internal_error');
        }
    }
    
    private function handleKPISummary(): void {
        $data = $this->dataService->getKPISummary($this->userId);
        Response::success($data);
    }
    
    private function handleComparison(): void {
        $currentDate = $_GET['currentDate'] ?? date('Y-m-d');
        $compareDate = $_GET['compareDate'] ?? date('Y-m-d', strtotime('-1 day'));
        
        $data = $this->dataService->getComparison($this->userId, $currentDate, $compareDate);
        Response::success($data);
    }
    
    private function handleGetTargets(): void {
        $filter = $_GET['filter'] ?? 'all';
        $targets = $this->targetService->getAll($this->userId, $filter);
        Response::success([
            'targets' => $targets,
            'total_count' => count($targets),
            'filter' => $filter
        ]);
    }
    
    private function handleSaveTarget(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON');
        }
        
        $data = [
            'name' => $input['name'] ?? '',
            'type' => $input['type'] ?? '',
            'value' => (float)($input['value'] ?? 0),
            'start_date' => $input['start_date'] ?? '',
            'end_date' => $input['end_date'] ?? '',
            'store' => $input['store'] ?? ''
        ];
        
        $id = $this->targetService->create($this->userId, $data);
        Response::success(['id' => $id, 'message' => 'Target created successfully']);
    }
    
    private function handleUpdateTarget(): void {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON');
        }
        
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid target ID');
        }
        
        $data = [
            'name' => $input['name'] ?? '',
            'type' => $input['type'] ?? '',
            'value' => (float)($input['value'] ?? 0),
            'start_date' => $input['start_date'] ?? '',
            'end_date' => $input['end_date'] ?? '',
            'store' => $input['store'] ?? ''
        ];
        
        $this->targetService->update($this->userId, $id, $data);
        Response::success(['id' => $id, 'message' => 'Target updated successfully']);
    }
    
    private function handleDeleteTarget(): void {
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Invalid target ID');
        }
        
        $this->targetService->delete($this->userId, $id);
        Response::success(['message' => 'Target deleted successfully']);
    }
    
    private function handleTrendData(): void {
        $days = (int)($_GET['days'] ?? 30);
        $data = $this->dataService->getTrendData($this->userId, $days);
        Response::success($data);
    }
}



// Start session
SessionManager::start();

// Get user ID
$userId = SessionManager::getUserId();

// Rate limiting
$rateLimitKey = $userId . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
if (!RateLimiter::check($rateLimitKey)) {
    Logger::warning('Rate limit exceeded', [
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    Response::error('Too many requests. Please try again later.', 429, 'rate_limit');
}

// Get action and method
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Validate action
if (empty($action)) {
    Response::error('Action parameter required', 400, 'missing_action');
}

// Log request
Logger::info("API Request", [
    'user_id' => $userId,
    'action' => $action,
    'method' => $method,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

// Handle request
$startTime = microtime(true);
$handler = new RequestHandler($userId);
$handler->handleRequest($action, $method);

// Log performance (this won't execute if Response::success/error is called)
$duration = microtime(true) - $startTime;
if ($duration > 1.0) {
    Logger::warning('Slow request', [
        'action' => $action,
        'duration' => round($duration * 1000, 2) . 'ms'
    ]);
}

// ==================== SHUTDOWN HANDLER ====================
register_shutdown_function(function() use ($userId, $action) {
    $error = error_get_last();
    
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        Logger::error('Fatal error', [
            'user_id' => $userId,
            'action' => $action,
            'error' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        
        if (!headers_sent()) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'A fatal error occurred',
                'timestamp' => time()
            ]);
        }
    }
    
    // Log query performance
    $queries = Database::getQueryLog();
    if (count($queries) > 5) {
        $totalTime = array_sum(array_column($queries, 'duration'));
        Logger::warning('High query count', [
            'user_id' => $userId,
            'action' => $action,
            'query_count' => count($queries),
            'total_time' => $totalTime . 'ms'
        ]);
    }
});

// ==================== ERROR HANDLERS ====================
set_error_handler(function($errno, $errstr, $errfile, $errline) use ($userId, $action) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $errorTypes = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_PARSE => 'PARSE',
        E_NOTICE => 'NOTICE',
        E_CORE_ERROR => 'CORE_ERROR',
        E_CORE_WARNING => 'CORE_WARNING',
        E_COMPILE_ERROR => 'COMPILE_ERROR',
        E_COMPILE_WARNING => 'COMPILE_WARNING',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
        E_DEPRECATED => 'DEPRECATED',
        E_USER_DEPRECATED => 'USER_DEPRECATED'
    ];
    
    $errorType = $errorTypes[$errno] ?? 'UNKNOWN';
    
    Logger::error("PHP {$errorType}", [
        'user_id' => $userId,
        'action' => $action,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ]);
    
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        Response::error('An error occurred', 500, 'internal_error');
    }
    
    return true;
});

set_exception_handler(function($exception) use ($userId, $action) {
    Logger::error('Uncaught Exception', [
        'user_id' => $userId,
        'action' => $action,
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    if (!headers_sent()) {
        Response::error('An unexpected error occurred', 500, 'uncaught_exception');
    }
});

// ==================== CLEANUP FUNCTION ====================
function cleanupOldFiles(): void {
    // Clean old rate limit files (older than 1 hour)
    $rateLimitDir = '/tmp/rate_limit/';
    if (is_dir($rateLimitDir)) {
        $files = glob($rateLimitDir . '*');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > 3600) {
                unlink($file);
            }
        }
    }
    
    // Clean old cache files (expired)
    $cacheDir = '/tmp/api_cache/';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && time() > ($data['expires'] ?? 0)) {
                    unlink($file);
                }
            }
        }
    }
    
    // Clean old log files (older than 30 days)
    $logDir = __DIR__ . '/logs/';
    if (is_dir($logDir)) {
        $files = glob($logDir . '*.log');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > (30 * 86400)) {
                unlink($file);
            }
        }
    }
}

// Run cleanup occasionally (1% chance per request)
if (rand(1, 100) === 1) {
    cleanupOldFiles();
}

// ==================== PERFORMANCE MONITORING ====================
class PerformanceMonitor {
    private static array $metrics = [];
    
    public static function start(string $operation): void {
        self::$metrics[$operation] = microtime(true);
    }
    
    public static function end(string $operation): float {
        if (!isset(self::$metrics[$operation])) {
            return 0;
        }
        
        $duration = microtime(true) - self::$metrics[$operation];
        unset(self::$metrics[$operation]);
        
        if ($duration > 1.0) {
            Logger::warning("Slow operation: {$operation}", [
                'duration' => round($duration * 1000, 2) . 'ms'
            ]);
        }
        
        return $duration;
    }
}

// ==================== HEALTH CHECK ENDPOINT ====================
if ($action === 'health_check') {
    try {
        // Check database
        $pdo = Database::connect();
        $pdo->query("SELECT 1");
        
        // Check writable directories
        $writableChecks = [
            'logs' => is_writable(__DIR__ . '/logs/'),
            'cache' => is_writable('/tmp/api_cache/'),
            'rate_limit' => is_writable('/tmp/rate_limit/')
        ];
        
        $healthy = !in_array(false, $writableChecks, true);
        
        Response::success([
            'status' => $healthy ? 'healthy' : 'degraded',
            'database' => 'connected',
            'writable' => $writableChecks,
            'timestamp' => date('c'),
            'version' => '3.0'
        ]);
        
    } catch (Exception $e) {
        Response::error('Health check failed', 503, 'unhealthy');
    }
}

// ==================== EXPORT DATA ENDPOINT ====================
if ($action === 'export_data') {
    $type = $_GET['type'] ?? 'trend';
    $format = $_GET['format'] ?? 'csv';
    
    if (!in_array($type, ['trend', 'targets'], true)) {
        Response::error('Invalid export type', 422, 'invalid_type');
    }
    
    if (!in_array($format, ['csv', 'json'], true)) {
        Response::error('Invalid format', 422, 'invalid_format');
    }
    
    try {
        $dataService = new DataService();
        $data = [];
        
        if ($type === 'trend') {
            $days = (int)($_GET['days'] ?? 30);
            $result = $dataService->getTrendData($userId, $days);
            $data = $result['trend_data'];
        } elseif ($type === 'targets') {
            $targetService = new TargetService();
            $data = $targetService->getAll($userId, 'all');
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
        
    } catch (Exception $e) {
        Logger::error('Export failed', ['error' => $e->getMessage()]);
        Response::error('Export failed', 500, 'export_error');
    }
}

// ==================== API DOCUMENTATION ====================
if ($action === 'docs') {
    $docs = [
        'version' => '3.0',
        'endpoints' => [
            'kpi_summary' => [
                'method' => 'GET',
                'description' => 'Get KPI summary for today vs yesterday',
                'cache' => '5 minutes',
                'returns' => ['today_sales', 'sales_change', 'today_customers', 'customers_change', 'today_transactions', 'transactions_change', 'target_achievement', 'target_status']
            ],
            'compare' => [
                'method' => 'GET',
                'description' => 'Compare two dates',
                'parameters' => ['currentDate' => 'YYYY-MM-DD', 'compareDate' => 'YYYY-MM-DD'],
                'cache' => '1 hour'
            ],
            'get_targets' => [
                'method' => 'GET',
                'description' => 'Get all targets',
                'parameters' => ['filter' => 'all|active|achieved|near|below'],
                'cache' => '3 minutes'
            ],
            'save_target' => [
                'method' => 'POST',
                'description' => 'Create new target',
                'body' => ['name', 'type', 'value', 'start_date', 'end_date', 'store']
            ],
            'update_target' => [
                'method' => 'POST',
                'description' => 'Update existing target',
                'body' => ['id', 'name', 'type', 'value', 'start_date', 'end_date', 'store']
            ],
            'delete_target' => [
                'method' => 'GET',
                'description' => 'Delete target',
                'parameters' => ['id' => 'integer']
            ],
            'trend_data' => [
                'method' => 'GET',
                'description' => 'Get trend data',
                'parameters' => ['days' => '1-365'],
                'cache' => '10 minutes'
            ],
            'export_data' => [
                'method' => 'GET',
                'description' => 'Export data',
                'parameters' => ['type' => 'trend|targets', 'format' => 'csv|json']
            ],
            'health_check' => [
                'method' => 'GET',
                'description' => 'Check API health'
            ]
        ],
        'rate_limit' => RATE_LIMIT_REQUESTS . ' requests per ' . RATE_LIMIT_WINDOW . ' seconds',
        'caching' => 'Intelligent caching per endpoint',
        'features' => [
            'Rate limiting',
            'Query optimization',
            'Comprehensive logging',
            'Error handling',
            'Performance monitoring',
            'Data validation',
            'Cache management'
        ]
    ];
    
    Response::success($docs);
}