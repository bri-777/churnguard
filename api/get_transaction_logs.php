<?php
// Clean any output before JSON
ob_start();

session_start();

// Clear output buffer
ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Database connection - Use your Hostinger config
if (!defined('DB_HOST')) {
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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Get all transaction logs for this user
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
    
    $response = [
        'success' => true,
        'transactions' => $transactions,
        'total_count' => intval($totalCount),
        'showing_count' => count($transactions),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Final clean and output
    ob_clean();
    echo json_encode($response);
    exit;
    
} catch (PDOException $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
    exit;
}
?>