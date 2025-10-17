<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection
require_once 'config.php'; // Adjust to your database config file

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get JSON data from request
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['transactions']) || !is_array($data['transactions'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data format']);
    exit;
}

$transactions = $data['transactions'];

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
    foreach ($transactions as $transaction) {
        $stmt->execute([
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
        ]);
        $insertedCount++;
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Transactions saved successfully',
        'count' => $insertedCount
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false, 
        'message' => 'Error saving transactions: ' . $e->getMessage()
    ]);
}
?>