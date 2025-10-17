<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection - Hostinger
define('DB_HOST', 'localhost');
define('DB_NAME', 'u393812660_churnguard');
define('DB_USER', 'u393812660_churnguard');
define('DB_PASS', '102202Brian_');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all transaction logs for this user (excluding created_at and updated_at)
    $sql = "SELECT 
                id,
                shop_name,
                receipt_count,
                customer_name,
                quantity_of_drinks,
                type_of_drink,
                date_visited,
                day,
                time_of_day,
                total_amount,
                payment_method
            FROM transaction_logs 
            WHERE user_id = ?
            ORDER BY date_visited DESC, id DESC
            LIMIT 1000";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM transaction_logs WHERE user_id = ?";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([$user_id]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'total_count' => $totalCount,
        'showing_count' => count($transactions),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>