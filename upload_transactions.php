<?php
// Clean output buffer
while (ob_get_level()) {
    ob_end_clean();
}

session_start();
header('Content-Type: application/json');

// Database credentials - Hostinger
define('DB_HOST', 'localhost');
define('DB_NAME', 'u393812660_churnguard');
define('DB_USER', 'u393812660_churnguard');
define('DB_PASS', '102202Brian_');

try {
    // Check session
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in. Session: ' . session_id()]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['transactions'])) {
        echo json_encode(['success' => false, 'message' => 'No transactions data']);
        exit;
    }
    
    $transactions = $data['transactions'];
    
    // Insert each transaction
    $sql = "INSERT INTO transaction_logs 
            (user_id, shop_name, receipt_count, customer_name, quantity_of_drinks, 
             type_of_drink, date_visited, day, time_of_day, total_amount, payment_method) 
            VALUES 
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $count = 0;
    
    foreach ($transactions as $t) {
        $stmt->execute([
            $user_id,
            $t['shop_name'] ?? '',
            $t['receipt_count'] ?? 0,
            $t['customer_name'] ?? '',
            $t['quantity_of_drinks'] ?? 0,
            $t['type_of_drink'] ?? '',
            $t['date_visited'] ?? date('Y-m-d'),
            $t['day'] ?? '',
            $t['time_of_day'] ?? '',
            $t['total_amount'] ?? 0,
            $t['payment_method'] ?? ''
        ]);
        $count++;
    }
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'message' => "Saved $count transactions"
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