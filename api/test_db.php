<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$host = 'localhost';
$name = 'u393812660_churnguard';
$user = 'u393812660_churnguard';
$pass = '102202Brian_';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user,
        $pass
    );
    echo "✅ Database connection successful!<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM churn_data");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ churn_data table has {$result['count']} records<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM targets");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ targets table has {$result['count']} records<br>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ users table has {$result['count']} records<br>";
    
} catch (PDOException $e) {
    echo "❌ Connection failed: " . $e->getMessage();
}
?>