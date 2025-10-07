<?php
// ==================== API DIAGNOSTIC TEST ====================
header('Content-Type: application/json');

// Start session
session_start();

// Test results
$results = [
    'status' => 'success',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: PHP Version
$results['tests']['php_version'] = [
    'status' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'PASS' : 'FAIL',
    'value' => PHP_VERSION,
    'message' => version_compare(PHP_VERSION, '7.4.0', '>=') ? 'PHP version OK' : 'PHP version too old'
];

// Test 2: Session
$results['tests']['session'] = [
    'status' => session_status() === PHP_SESSION_ACTIVE ? 'PASS' : 'FAIL',
    'session_id' => session_id(),
    'user_id_exists' => isset($_SESSION['user_id']) ? 'YES' : 'NO',
    'user_id' => $_SESSION['user_id'] ?? 'NOT SET'
];

// Test 3: Database Connection
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u393812660_churnguard;charset=utf8mb4",
        "u393812660_churnguard",
        "102202Brian_",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $results['tests']['database'] = [
        'status' => 'PASS',
        'message' => 'Database connection successful'
    ];
    
    // Test if tables exist
    $stmt = $pdo->query("SHOW TABLES LIKE 'churn_data'");
    $results['tests']['churn_data_table'] = [
        'status' => $stmt->rowCount() > 0 ? 'PASS' : 'FAIL',
        'message' => $stmt->rowCount() > 0 ? 'churn_data table exists' : 'churn_data table NOT FOUND'
    ];
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'targets'");
    $results['tests']['targets_table'] = [
        'status' => $stmt->rowCount() > 0 ? 'PASS' : 'FAIL',
        'message' => $stmt->rowCount() > 0 ? 'targets table exists' : 'targets table NOT FOUND'
    ];
    
} catch (PDOException $e) {
    $results['tests']['database'] = [
        'status' => 'FAIL',
        'message' => 'Database connection failed: ' . $e->getMessage()
    ];
}

// Test 4: File Permissions
$results['tests']['file_access'] = [
    'status' => file_exists('sales_comparison.php') ? 'PASS' : 'FAIL',
    'message' => file_exists('sales_comparison.php') ? 'sales_comparison.php found' : 'sales_comparison.php NOT FOUND',
    'readable' => is_readable('sales_comparison.php') ? 'YES' : 'NO'
];

// Test 5: PDO Extension
$results['tests']['pdo_extension'] = [
    'status' => extension_loaded('pdo') && extension_loaded('pdo_mysql') ? 'PASS' : 'FAIL',
    'message' => extension_loaded('pdo') && extension_loaded('pdo_mysql') ? 'PDO MySQL available' : 'PDO MySQL NOT AVAILABLE'
];

// Test 6: JSON Extension
$results['tests']['json_extension'] = [
    'status' => extension_loaded('json') ? 'PASS' : 'FAIL',
    'message' => extension_loaded('json') ? 'JSON extension available' : 'JSON extension NOT AVAILABLE'
];

// Test 7: Current Directory
$results['tests']['directory'] = [
    'current_dir' => getcwd(),
    'script_path' => __FILE__,
    'files_in_dir' => array_slice(scandir('.'), 0, 20)
];

// Summary
$failedTests = 0;
foreach ($results['tests'] as $test) {
    if (isset($test['status']) && $test['status'] === 'FAIL') {
        $failedTests++;
    }
}

$results['summary'] = [
    'total_tests' => count($results['tests']),
    'failed' => $failedTests,
    'overall_status' => $failedTests === 0 ? 'ALL TESTS PASSED' : "$failedTests TEST(S) FAILED"
];

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>