<?php
// api/performance_tracker.php
declare(strict_types=1);

// Database configuration
$db_host = 'localhost';
$db_name = 'u393812660_churnguard';
$db_user = 'u393812660_churnguard';
$db_pass = '102202Brian_';

// Application settings
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Start session and check authentication
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

try {
    // Database connection
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Get action from request
$action = $_GET['action'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'];

// Route handling
switch ($action) {
    case 'dashboard':
        getDashboardData($pdo, $user_id);
        break;
        
    case 'comparison':
        getYearComparison($pdo, $user_id);
        break;
        
    case 'monthly_trend':
        getMonthlyTrend($pdo, $user_id);
        break;
        
    case 'targets':
        if ($method === 'GET') {
            getTargets($pdo, $user_id);
        } elseif ($method === 'POST') {
            saveTarget($pdo, $user_id);
        }
        break;
        
    case 'save_monthly':
        saveMonthlyData($pdo, $user_id);
        break;
        
    case 'performance_summary':
        getPerformanceSummary($pdo, $user_id);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

// Function to get dashboard data
function getDashboardData($pdo, $user_id) {
    try {
        $current_year = date('Y');
        $previous_year = $current_year - 1;
        
        // Get current year data
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(total_sales), 0) as total_sales,
                COALESCE(SUM(total_customers), 0) as total_customers,
                COALESCE(SUM(new_customers), 0) as new_customers,
                COALESCE(SUM(returning_customers), 0) as returning_customers,
                COALESCE(AVG(average_transaction_value), 0) as avg_transaction,
                COALESCE(SUM(total_transactions), 0) as total_transactions,
                COUNT(DISTINCT month) as months_recorded
            FROM yearly_performance 
            WHERE user_id = ? AND year = ?
        ");
        
        $stmt->execute([$user_id, $current_year]);
        $current = $stmt->fetch();
        
        // Get previous year data
        $stmt->execute([$user_id, $previous_year]);
        $previous = $stmt->fetch();
        
        // Calculate growth metrics
        $sales_growth = $previous['total_sales'] > 0 ? 
            (($current['total_sales'] - $previous['total_sales']) / $previous['total_sales']) * 100 : 0;
        
        $customer_growth = $previous['total_customers'] > 0 ? 
            (($current['total_customers'] - $previous['total_customers']) / $previous['total_customers']) * 100 : 0;
        
        // Get monthly breakdown
        $stmt = $pdo->prepare("
            SELECT 
                month,
                year,
                total_sales,
                total_customers,
                total_transactions,
                new_customers,
                average_transaction_value
            FROM yearly_performance
            WHERE user_id = ? AND year IN (?, ?)
            ORDER BY year DESC, month ASC
        ");
        
        $stmt->execute([$user_id, $current_year, $previous_year]);
        $monthly = $stmt->fetchAll();
        
        // Get targets and calculate progress
        $stmt = $pdo->prepare("
            SELECT target_type, target_value
            FROM performance_targets
            WHERE user_id = ? AND year = ?
        ");
        
        $stmt->execute([$user_id, $current_year]);
        $targets = $stmt->fetchAll();
        
        $targets_map = [];
        foreach ($targets as $target) {
            $targets_map[$target['target_type']] = $target['target_value'];
        }
        
        // Calculate progress
        $sales_progress = isset($targets_map['sales']) && $targets_map['sales'] > 0 ?
            min(100, ($current['total_sales'] / $targets_map['sales']) * 100) : 0;
        
        $customer_progress = isset($targets_map['customers']) && $targets_map['customers'] > 0 ?
            min(100, ($current['total_customers'] / $targets_map['customers']) * 100) : 0;
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'current_year' => $current,
                'previous_year' => $previous,
                'growth' => [
                    'sales' => round($sales_growth, 2),
                    'customers' => round($customer_growth, 2)
                ],
                'monthly' => $monthly,
                'targets' => $targets_map,
                'progress' => [
                    'sales' => round($sales_progress, 2),
                    'customers' => round($customer_progress, 2)
                ]
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to load dashboard data']);
    }
}

// Function to get year-over-year comparison
function getYearComparison($pdo, $user_id) {
    try {
        $current_year = date('Y');
        $previous_year = $current_year - 1;
        
        $stmt = $pdo->prepare("
            SELECT 
                year,
                month,
                total_sales,
                total_customers,
                new_customers,
                returning_customers,
                average_transaction_value,
                total_transactions
            FROM yearly_performance
            WHERE user_id = ? AND year IN (?, ?)
            ORDER BY year, month
        ");
        
        $stmt->execute([$user_id, $current_year, $previous_year]);
        $data = $stmt->fetchAll();
        
        echo json_encode([
            'status' => 'success',
            'data' => $data
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to load comparison data']);
    }
}

// Function to save target
function saveTarget($pdo, $user_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $year = $input['year'] ?? date('Y');
        $type = $input['type'] ?? '';
        $value = (float)($input['value'] ?? 0);
        $period = $input['period'] ?? 'yearly';
        
        if (!in_array($type, ['sales', 'customers', 'growth_rate'])) {
            throw new Exception('Invalid target type');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO performance_targets 
                (user_id, year, target_type, target_value, target_period)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                target_value = VALUES(target_value),
                updated_at = NOW()
        ");
        
        $stmt->execute([$user_id, $year, $type, $value, $period]);
        
        echo json_encode(['status' => 'success', 'message' => 'Target saved successfully']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save target']);
    }
}

// Function to save monthly data
function saveMonthlyData($pdo, $user_id) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $year = $input['year'] ?? date('Y');
        $month = $input['month'] ?? date('n');
        $sales = (float)($input['sales'] ?? 0);
        $customers = (int)($input['customers'] ?? 0);
        $new_customers = (int)($input['new_customers'] ?? 0);
        $transactions = (int)($input['transactions'] ?? 0);
        
        $avg_value = $transactions > 0 ? $sales / $transactions : 0;
        $returning = max(0, $customers - $new_customers);
        
        $stmt = $pdo->prepare("
            INSERT INTO yearly_performance 
                (user_id, year, month, total_sales, total_customers, new_customers, 
                 returning_customers, average_transaction_value, total_transactions)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                total_sales = VALUES(total_sales),
                total_customers = VALUES(total_customers),
                new_customers = VALUES(new_customers),
                returning_customers = VALUES(returning_customers),
                average_transaction_value = VALUES(average_transaction_value),
                total_transactions = VALUES(total_transactions),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $user_id, $year, $month, $sales, $customers, 
            $new_customers, $returning, $avg_value, $transactions
        ]);
        
        echo json_encode(['status' => 'success', 'message' => 'Data saved successfully']);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save monthly data']);
    }
}

// Function to get performance summary
function getPerformanceSummary($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                year,
                COUNT(DISTINCT month) as months_recorded,
                SUM(total_sales) as annual_sales,
                SUM(total_customers) as annual_customers,
                AVG(average_transaction_value) as avg_transaction_value
            FROM yearly_performance
            WHERE user_id = ?
            GROUP BY year
            ORDER BY year DESC
            LIMIT 5
        ");
        
        $stmt->execute([$user_id]);
        $data = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to load summary']);
    }
}

// Function to get targets
function getTargets($pdo, $user_id) {
    try {
        $year = $_GET['year'] ?? date('Y');
        
        $stmt = $pdo->prepare("
            SELECT target_type, target_value, target_period
            FROM performance_targets
            WHERE user_id = ? AND year = ?
        ");
        
        $stmt->execute([$user_id, $year]);
        $targets = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $targets]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to load targets']);
    }
}

// Function to get monthly trend
function getMonthlyTrend($pdo, $user_id) {
    try {
        $year = $_GET['year'] ?? date('Y');
        
        $stmt = $pdo->prepare("
            SELECT 
                month,
                total_sales,
                total_customers,
                total_transactions
            FROM yearly_performance
            WHERE user_id = ? AND year = ?
            ORDER BY month
        ");
        
        $stmt->execute([$user_id, $year]);
        $data = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $data]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to load monthly trend']);
    }
}
?>