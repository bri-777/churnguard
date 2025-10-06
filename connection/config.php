<?php
// /connection/config.php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

// Error handling - log to file, never display
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/db_errors.log');

// Create logs directory if needed
$log_dir = __DIR__ . '/../logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}

// Increase limits for prediction calculations
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '180');

// ---- DB CONFIG BASED ON ENVIRONMENT ----
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    // Local (XAMPP or dev machine)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'churnguard');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    // Production (live server)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u393812660_churnguard');
    define('DB_USER', 'u393812660_churnguard');
    define('DB_PASS', '102202Brian_');
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    // CRITICAL: Set MySQL timezone and SQL mode for consistency
    $pdo->exec("SET time_zone = '+08:00'");
    $pdo->exec("SET sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    $pdo->exec("SET SESSION sql_mode = ''");
    
    // Log successful connection
    error_log(date('Y-m-d H:i:s') . " - DB Connected: " . DB_NAME . " on " . $_SERVER['HTTP_HOST']);
    
} catch (Throwable $e) {
    // Log the full error details
    error_log(date('Y-m-d H:i:s') . " - DB Connection Failed: " . $e->getMessage() . " | Host: " . DB_HOST . " | DB: " . DB_NAME);
    
    // Return JSON so the frontend sees a proper error (not HTML)
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'DB connection failed',
        'message' => 'Database unavailable. Please contact support.',
        'details' => ($_SERVER['HTTP_HOST'] === 'localhost') ? $e->getMessage() : 'Check server logs'
    ]);
    exit;
}

// Helper function for safe date handling
function get_server_date() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT CURDATE() as current_date");
        $result = $stmt->fetch();
        return $result['current_date'];
    } catch (Exception $e) {
        return date('Y-m-d');
    }
}

// Helper function for debugging (only on localhost)
function debug_log($message, $data = null) {
    if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
        $log = date('Y-m-d H:i:s') . " - " . $message;
        if ($data !== null) {
            $log .= " | Data: " . json_encode($data);
        }
        error_log($log . "\n", 3, __DIR__ . '/../logs/debug.log');
    }
}
?>