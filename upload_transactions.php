<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create log file for debugging
$logFile = __DIR__ . '/upload_log.txt';
$logMsg = date('Y-m-d H:i:s') . " - Upload request received\n";
file_put_contents($logFile, $logMsg, FILE_APPEND);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $error = 'User not logged in. Session: ' . session_id();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $error\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

$user_id = $_SESSION['user_id'];
file_put_contents($logFile, date('Y-m-d H:i:s') . " - User ID: $user_id\n", FILE_APPEND);

// Database connection - Hostinger config
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connected\n", FILE_APPEND);
} catch (PDOException $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $error\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

// Get JSON data
$rawData = file_get_contents('php://input');
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Raw data length: " . strlen($rawData) . "\n", FILE_APPEND);

if (empty($rawData)) {
    $error = 'No data received';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $error\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

$data = json_decode($rawData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $error = 'JSON decode error: ' . json_last_error_msg();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $error\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

if (!isset($data['transactions']) || !is_array($data['transactions'])) {
    $error = 'Invalid data format - transactions array missing';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $error\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

$transactions = $data['transactions'];
$transCount = count($transactions);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transactions to save: $transCount\n", FILE_APPEND);

if ($transCount === 0) {
    $error = 'No transactions to save';
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $error\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

// Verify table exists
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'transaction_logs'");
    if ($checkTable->rowCount() === 0) {
        $error = 'Table transaction_logs does not exist!';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $error\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => $error]);
        exit;
    }
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Table exists\n", FILE_APPEND);
} catch (Exception $e) {
    $error = 'Table check error: ' . $e->getMessage();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $error\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => $error]);
    exit;
}

// Insert transactions
try {
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO transaction_logs 
            (user_id, shop_name, receipt_count, customer_name, quantity_of_drinks, 
             type_of_drink, date_visited, day, time_of_day, total_amount, payment_method) 
            VALUES 
            (:user_id, :shop_name, :receipt_count, :customer_name, :quantity_of_drinks, 
             :type_of_drink, :date_visited, :day, :time_of_day, :total_amount, :payment_method)";
    
    $stmt = $pdo->prepare($sql);
    
    $insertedCount = 0;
    foreach ($transactions as $index => $transaction) {
        $params = [
            ':user_id' => $user_id,
            ':shop_name' => $transaction['shop_name'] ?? '',
            ':receipt_count' => $transaction['receipt_count'] ?? 0,
            ':customer_name' => $transaction['customer_name'] ?? '',
            ':quantity_of_drinks' => $transaction['quantity_of_drinks'] ?? 0,
            ':type_of_drink' => $transaction['type_of_drink'] ?? '',
            ':date_visited' => $transaction['date_visited'] ?? date('Y-m-d'),
            ':day' => $transaction['day'] ?? '',
            ':time_of_day' => $transaction['time_of_day'] ?? '',
            ':total_amount' => $transaction['total_amount'] ?? 0,
            ':payment_method' => $transaction['payment_method'] ?? ''
        ];
        
        $stmt->execute($params);
        $insertedCount++;
    }
    
    $pdo->commit();
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - SUCCESS: $insertedCount transactions saved\n", FILE_APPEND);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Transactions saved successfully',
        'count' => $insertedCount
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $error = 'Insert error: ' . $e->getMessage();
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $error\n", FILE_APPEND);
    
    echo json_encode([
        'success' => false, 
        'message' => $error
    ]);
}
?>