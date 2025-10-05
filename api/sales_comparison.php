<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

$action = $_GET['action'] ?? '';

// Helper function for safe division
function safeDivide(float $numerator, float $denominator): float {
    return $denominator > 0 ? round($numerator / $denominator, 2) : 0;
}

// Helper function for percentage change
function percentageChange(float $current, float $previous): float {
    return $previous > 0 ? round((($current - $previous) / $previous) * 100, 2) : 0;
}

// Get comparison data
if ($action === 'compare') {
    try {
        $currentDate = $_GET['currentDate'] ?? date('Y-m-d');
        $compareDate = $_GET['compareDate'] ?? date('Y-m-d', strtotime('-1 day'));
        
        // Validate dates
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $currentDate) || 
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $compareDate)) {
            j_err('Invalid date format', 422);
        }
        
        // Get current period data
        $stmtCurrent = $pdo->prepare("
            SELECT 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCurrent->execute([$uid, $currentDate]);
        $currentData = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
        
        // Get compare period data
        $stmtCompare = $pdo->prepare("
            SELECT 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCompare->execute([$uid, $compareDate]);
        $compareData = $stmtCompare->fetch(PDO::FETCH_ASSOC);
        
        // Use zeros if no data found
        if (!$currentData) {
            $currentData = ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        }
        if (!$compareData) {
            $compareData = ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        }
        
        // Calculate metrics
        $currentSales = (float)$currentData['sales_volume'];
        $compareSales = (float)$compareData['sales_volume'];
        $currentReceipts = (int)$currentData['receipt_count'];
        $compareReceipts = (int)$compareData['receipt_count'];
        $currentCustomers = (int)$currentData['customer_traffic'];
        $compareCustomers = (int)$compareData['customer_traffic'];
        
        $currentAvgTrans = safeDivide($currentSales, $currentReceipts);
        $compareAvgTrans = safeDivide($compareSales, $compareReceipts);
        
        $metrics = [
            [
                'metric' => 'Sales Revenue',
                'current' => $currentSales,
                'compare' => $compareSales,
                'difference' => $currentSales - $compareSales,
                'percentage' => percentageChange($currentSales, $compareSales),
                'trend' => $currentSales >= $compareSales ? 'up' : 'down'
            ],
            [
                'metric' => 'Transactions',
                'current' => $currentReceipts,
                'compare' => $compareReceipts,
                'difference' => $currentReceipts - $compareReceipts,
                'percentage' => percentageChange((float)$currentReceipts, (float)$compareReceipts),
                'trend' => $currentReceipts >= $compareReceipts ? 'up' : 'down'
            ],
            [
                'metric' => 'Customer Traffic',
                'current' => $currentCustomers,
                'compare' => $compareCustomers,
                'difference' => $currentCustomers - $compareCustomers,
                'percentage' => percentageChange((float)$currentCustomers, (float)$compareCustomers),
                'trend' => $currentCustomers >= $compareCustomers ? 'up' : 'down'
            ],
            [
                'metric' => 'Avg Transaction Value',
                'current' => $currentAvgTrans,
                'compare' => $compareAvgTrans,
                'difference' => $currentAvgTrans - $compareAvgTrans,
                'percentage' => percentageChange($currentAvgTrans, $compareAvgTrans),
                'trend' => $currentAvgTrans >= $compareAvgTrans ? 'up' : 'down'
            ]
        ];
        
        j_ok([
            'comparison' => $metrics,
            'currentDate' => $currentDate,
            'compareDate' => $compareDate
        ]);
        
    } catch (Throwable $e) {
        error_log("Comparison error: " . $e->getMessage());
        j_err('Comparison failed', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Get targets
if ($action === 'get_targets') {
    try {
        $filter = $_GET['filter'] ?? 'all';
        $validFilters = ['all', 'active', 'achieved', 'near', 'below'];
        
        if (!in_array($filter, $validFilters)) {
            j_err('Invalid filter', 422);
        }
        
        $query = "
            SELECT t.*, 
                   COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                   COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                   COALESCE(SUM(cd.customer_traffic), 0) as current_customers,
                   COALESCE(AVG(CASE 
                       WHEN cd.receipt_count > 0 
                       THEN cd.sales_volume / cd.receipt_count 
                       ELSE 0 
                   END), 0) as current_avg_transaction
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE t.user_id = ?
        ";
        
        $params = [$uid];
        
        if ($filter === 'active') {
            $query .= " AND t.end_date >= CURDATE() AND t.start_date <= CURDATE()";
        }
        
        $query .= " GROUP BY t.id ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate progress for each target
        foreach ($targets as &$target) {
            $current = 0;
            
            switch ($target['target_type']) {
                case 'sales':
                    $current = (float)$target['current_sales'];
                    break;
                case 'transactions':
                    $current = (int)$target['current_receipts'];
                    break;
                case 'customers':
                    $current = (int)$target['current_customers'];
                    break;
                case 'avg_transaction':
                    $current = (float)$target['current_avg_transaction'];
                    break;
                default:
                    $current = 0;
            }
            
            $targetValue = (float)$target['target_value'];
            $progress = safeDivide($current * 100, $targetValue);
            
            // Cap progress at 999.9% for display purposes
            $progress = min($progress, 999.9);
            
            // Determine status
            if ($progress >= 100) {
                $status = 'achieved';
            } elseif ($progress >= 80) {
                $status = 'near';
            } else {
                $status = 'below';
            }
            
            $target['current_value'] = $current;
            $target['progress'] = $progress;
            $target['status'] = $status;
            
            // Clean up extra fields
            unset($target['current_sales'], $target['current_receipts'], 
                  $target['current_customers'], $target['current_avg_transaction']);
        }
        
        // Apply status filter if needed
        if (in_array($filter, ['achieved', 'near', 'below'])) {
            $targets = array_filter($targets, function($t) use ($filter) {
                return $t['status'] === $filter;
            });
            $targets = array_values($targets); // Re-index array
        }
        
        j_ok(['targets' => $targets]);
        
    } catch (Throwable $e) {
        error_log("Get targets error: " . $e->getMessage());
        j_err('Failed to load targets', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Save target
if ($action === 'save_target') {
    try {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true) ?: $_POST;
        
        $name = trim($data['name'] ?? '');
        $type = trim($data['type'] ?? '');
        $value = (float)($data['value'] ?? 0);
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $store = trim($data['store'] ?? '');
        
        // Validation
        if (empty($name)) {
            j_err('Target name is required', 422);
        }
        
        if (strlen($name) > 100) {
            j_err('Target name too long (max 100 characters)', 422);
        }
        
        $validTypes = ['sales', 'customers', 'transactions', 'avg_transaction'];
        if (!in_array($type, $validTypes)) {
            j_err('Invalid target type', 422);
        }
        
        if ($value <= 0) {
            j_err('Target value must be greater than 0', 422);
        }
        
        if ($value > 999999999) {
            j_err('Target value too large', 422);
        }
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || 
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            j_err('Invalid date format', 422);
        }
        
        if (strtotime($endDate) < strtotime($startDate)) {
            j_err('End date must be after start date', 422);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO targets 
            (user_id, target_name, target_type, target_value, start_date, end_date, store, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$uid, $name, $type, $value, $startDate, $endDate, $store]);
        
        j_ok([
            'saved' => true, 
            'id' => (int)$pdo->lastInsertId(),
            'message' => 'Target created successfully'
        ]);
        
    } catch (Throwable $e) {
        error_log("Save target error: " . $e->getMessage());
        j_err('Failed to save target', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Delete target
if ($action === 'delete_target') {
    try {
        $id = (int)($_GET['id'] ?? 0);
        
        if ($id <= 0) {
            j_err('Invalid target ID', 422);
        }
        
        // Check if target exists and belongs to user
        $stmtCheck = $pdo->prepare("SELECT id FROM targets WHERE id = ? AND user_id = ?");
        $stmtCheck->execute([$id, $uid]);
        
        if (!$stmtCheck->fetch()) {
            j_err('Target not found', 404);
        }
        
        $stmt = $pdo->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $uid]);
        
        j_ok([
            'deleted' => true,
            'message' => 'Target deleted successfully'
        ]);
        
    } catch (Throwable $e) {
        error_log("Delete target error: " . $e->getMessage());
        j_err('Failed to delete target', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Get KPI summary
if ($action === 'kpi_summary') {
    try {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Today's data
        $stmtToday = $pdo->prepare("
            SELECT 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtToday->execute([$uid, $today]);
        $todayData = $stmtToday->fetch(PDO::FETCH_ASSOC);
        
        // Yesterday's data
        $stmtYesterday = $pdo->prepare("
            SELECT 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtYesterday->execute([$uid, $yesterday]);
        $yesterdayData = $stmtYesterday->fetch(PDO::FETCH_ASSOC);
        
        // Use zeros if no data
        if (!$todayData) {
            $todayData = ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        }
        if (!$yesterdayData) {
            $yesterdayData = ['sales_volume' => 0, 'receipt_count' => 0, 'customer_traffic' => 0];
        }
        
        // Calculate changes
        $todaySales = (float)$todayData['sales_volume'];
        $yesterdaySales = (float)$yesterdayData['sales_volume'];
        $salesChange = percentageChange($todaySales, $yesterdaySales);
        
        $todayCustomers = (int)$todayData['customer_traffic'];
        $yesterdayCustomers = (int)$yesterdayData['customer_traffic'];
        $customersChange = percentageChange((float)$todayCustomers, (float)$yesterdayCustomers);
        
        $todayTransactions = (int)$todayData['receipt_count'];
        $yesterdayTransactions = (int)$yesterdayData['receipt_count'];
        $transactionsChange = percentageChange((float)$todayTransactions, (float)$yesterdayTransactions);
        
        // Get active target achievement
        $stmtTarget = $pdo->prepare("
            SELECT t.*, 
                   COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                   COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                   COALESCE(SUM(cd.customer_traffic), 0) as current_customers
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE t.user_id = ? 
                AND t.end_date >= CURDATE()
                AND t.start_date <= CURDATE()
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 1
        ");
        $stmtTarget->execute([$uid]);
        $targetData = $stmtTarget->fetch(PDO::FETCH_ASSOC);
        
        $targetAchievement = 0;
        $targetStatus = 'No active target';
        
        if ($targetData) {
            $current = 0;
            
            switch ($targetData['target_type']) {
                case 'sales':
                    $current = (float)$targetData['current_sales'];
                    break;
                case 'transactions':
                    $current = (int)$targetData['current_receipts'];
                    break;
                case 'customers':
                    $current = (int)$targetData['current_customers'];
                    break;
            }
            
            $targetValue = (float)$targetData['target_value'];
            $targetAchievement = safeDivide($current * 100, $targetValue);
            $targetStatus = $targetData['target_name'];
        }
        
        j_ok([
            'today_sales' => $todaySales,
            'sales_change' => $salesChange,
            'today_customers' => $todayCustomers,
            'customers_change' => $customersChange,
            'today_transactions' => $todayTransactions,
            'transactions_change' => $transactionsChange,
            'target_achievement' => min($targetAchievement, 999.9),
            'target_status' => $targetStatus
        ]);
        
    } catch (Throwable $e) {
        error_log("KPI summary error: " . $e->getMessage());
        j_err('Failed to load KPI summary', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Get trend data
if ($action === 'trend_data') {
    try {
        $days = (int)($_GET['days'] ?? 30);
        $days = max(7, min(90, $days));
        
        $stmt = $pdo->prepare("
            SELECT 
                date, 
                COALESCE(sales_volume, 0) as sales_volume,
                COALESCE(receipt_count, 0) as receipt_count,
                COALESCE(customer_traffic, 0) as customer_traffic
            FROM churn_data
            WHERE user_id = ? 
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                AND date <= CURDATE()
            ORDER BY date ASC
        ");
        $stmt->execute([$uid, $days]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        j_ok(['trend_data' => $data]);
        
    } catch (Throwable $e) {
        error_log("Trend data error: " . $e->getMessage());
        j_err('Failed to load trend data', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

j_err('Unknown action', 400);