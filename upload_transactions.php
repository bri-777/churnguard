<?php
ob_start();
session_start();

// Clear any previous output
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Disable error output to prevent breaking JSON
ini_set('display_errors', 0);
error_reporting(0);

try {
    // Check session
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Database connection
    if (!defined('DB_HOST')) {
        require_once 'config.php';
    }
    
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get JSON input
    $rawData = file_get_contents('php://input');
    
    if (empty($rawData)) {
        throw new Exception('No data received');
    }
    
    $data = json_decode($rawData, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    if (!isset($data['transactions']) || !is_array($data['transactions'])) {
        throw new Exception('Transactions array not found');
    }
    
    $transactions = $data['transactions'];
    
    if (count($transactions) === 0) {
        throw new Exception('No transactions to save');
    }
    
    // Insert into database
    $pdo->beginTransaction();
    
    $sql = "INSERT INTO transaction_logs 
            (user_id, shop_name, receipt_count, customer_name, quantity_of_drinks, 
             type_of_drink, date_visited, day, time_of_day, total_amount, payment_method) 
            VALUES 
            (:user_id, :shop_name, :receipt_count, :customer_name, :quantity_of_drinks, 
             :type_of_drink, :date_visited, :day, :time_of_day, :total_amount, :payment_method)";
    
    $stmt = $pdo->prepare($sql);
    
    $insertedCount = 0;
    foreach ($transactions as $transaction) {
        $stmt->execute([
            ':user_id' => $user_id,
            ':shop_name' => $transaction['shop_name'] ?? '',
            ':receipt_count' => intval($transaction['receipt_count'] ?? 0),
            ':customer_name' => $transaction['customer_name'] ?? '',
            ':quantity_of_drinks' => intval($transaction['quantity_of_drinks'] ?? 0),
            ':type_of_drink' => $transaction['type_of_drink'] ?? '',
            ':date_visited' => $transaction['date_visited'] ?? date('Y-m-d'),
            ':day' => $transaction['day'] ?? '',
            ':time_of_day' => $transaction['time_of_day'] ?? '',
            ':total_amount' => floatval($transaction['total_amount'] ?? 0),
            ':payment_method' => $transaction['payment_method'] ?? ''
        ]);
        $insertedCount++;
    }
    
    $pdo->commit();
    
    $response = [
        'success' => true,
        'message' => 'Transactions saved successfully',
        'count' => $insertedCount
    ];
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

// Clear any output buffer and send only JSON
ob_clean();
echo json_encode($response);
exit;
?>