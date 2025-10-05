<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

$action = $_GET['action'] ?? '';

// Get comparison data
if ($action === 'compare') {
    try {
        $currentDate = $_GET['currentDate'] ?? date('Y-m-d');
        $compareDate = $_GET['compareDate'] ?? date('Y-m-d', strtotime('-1 day'));
        
        // Get current period data
        $stmtCurrent = $pdo->prepare("
            SELECT * FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCurrent->execute([$uid, $currentDate]);
        $currentData = $stmtCurrent->fetch(PDO::FETCH_ASSOC);
        
        // Get compare period data
        $stmtCompare = $pdo->prepare("
            SELECT * FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtCompare->execute([$uid, $compareDate]);
        $compareData = $stmtCompare->fetch(PDO::FETCH_ASSOC);
        
        // Calculate comparisons
        $metrics = [
            'sales' => [
                'name' => 'Sales Revenue',
                'current' => (float)($currentData['sales_volume'] ?? 0),
                'compare' => (float)($compareData['sales_volume'] ?? 0)
            ],
            'receipts' => [
                'name' => 'Transactions',
                'current' => (int)($currentData['receipt_count'] ?? 0),
                'compare' => (int)($compareData['receipt_count'] ?? 0)
            ],
            'customers' => [
                'name' => 'Customer Traffic',
                'current' => (int)($currentData['customer_traffic'] ?? 0),
                'compare' => (int)($compareData['customer_traffic'] ?? 0)
            ],
            'avg_transaction' => [
                'name' => 'Avg Transaction Value',
                'current' => ($currentData['receipt_count'] ?? 0) > 0 
                    ? ($currentData['sales_volume'] ?? 0) / $currentData['receipt_count'] 
                    : 0,
                'compare' => ($compareData['receipt_count'] ?? 0) > 0 
                    ? ($compareData['sales_volume'] ?? 0) / $compareData['receipt_count'] 
                    : 0
            ]
        ];
        
        // Calculate differences and percentages
        $comparison = [];
        foreach ($metrics as $key => $metric) {
            $diff = $metric['current'] - $metric['compare'];
            $pctChange = $metric['compare'] > 0 
                ? (($diff / $metric['compare']) * 100) 
                : 0;
            
            $comparison[] = [
                'metric' => $metric['name'],
                'current' => $metric['current'],
                'compare' => $metric['compare'],
                'difference' => $diff,
                'percentage' => round($pctChange, 2),
                'trend' => $diff >= 0 ? 'up' : 'down'
            ];
        }
        
        j_ok([
            'comparison' => $comparison,
            'currentDate' => $currentDate,
            'compareDate' => $compareDate
        ]);
        
    } catch (Throwable $e) {
        j_err('Comparison failed', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Get targets
if ($action === 'get_targets') {
    try {
        $filter = $_GET['filter'] ?? 'all';
        
        $query = "
            SELECT t.*, 
                   COALESCE(SUM(cd.sales_volume), 0) as current_sales,
                   COALESCE(SUM(cd.receipt_count), 0) as current_receipts,
                   COALESCE(SUM(cd.customer_traffic), 0) as current_customers
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE t.user_id = ?
        ";
        
        if ($filter === 'active') {
            $query .= " AND t.end_date >= CURDATE()";
        } elseif ($filter === 'achieved') {
            $query .= " AND t.status = 'achieved'";
        } elseif ($filter === 'near') {
            $query .= " AND t.status = 'near'";
        } elseif ($filter === 'below') {
            $query .= " AND t.status = 'below'";
        }
        
        $query .= " GROUP BY t.id ORDER BY t.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$uid]);
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
            }
            
            $targetValue = (float)$target['target_value'];
            $progress = $targetValue > 0 ? ($current / $targetValue) * 100 : 0;
            
            // Determine status
            if ($progress >= 100) {
                $status = 'achieved';
            } elseif ($progress >= 80) {
                $status = 'near';
            } else {
                $status = 'below';
            }
            
            $target['current_value'] = $current;
            $target['progress'] = round($progress, 2);
            $target['status'] = $status;
        }
        
        j_ok(['targets' => $targets]);
        
    } catch (Throwable $e) {
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
        
        if (empty($name) || empty($type) || $value <= 0 || empty($startDate) || empty($endDate)) {
            j_err('Missing required fields', 422);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO targets 
            (user_id, target_name, target_type, target_value, start_date, end_date, store, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$uid, $name, $type, $value, $startDate, $endDate, $store]);
        
        j_ok(['saved' => true, 'id' => (int)$pdo->lastInsertId()]);
        
    } catch (Throwable $e) {
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
        
        $stmt = $pdo->prepare("DELETE FROM targets WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $uid]);
        
        j_ok(['deleted' => true]);
        
    } catch (Throwable $e) {
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
        $stmtToday = $pdo->prepare("SELECT * FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtToday->execute([$uid, $today]);
        $todayData = $stmtToday->fetch(PDO::FETCH_ASSOC);
        
        // Yesterday's data
        $stmtYesterday = $pdo->prepare("
            SELECT * FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmtYesterday->execute([$uid, $yesterday]);
        $yesterdayData = $stmtYesterday->fetch(PDO::FETCH_ASSOC);
        
        // Calculate changes
        $todaySales = (float)($todayData['sales_volume'] ?? 0);
        $yesterdaySales = (float)($yesterdayData['sales_volume'] ?? 0);
        $salesChange = $yesterdaySales > 0 ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 : 0;
        
        $todayCustomers = (int)($todayData['customer_traffic'] ?? 0);
        $yesterdayCustomers = (int)($yesterdayData['customer_traffic'] ?? 0);
        $customersChange = $yesterdayCustomers > 0 ? (($todayCustomers - $yesterdayCustomers) / $yesterdayCustomers) * 100 : 0;
        
        $todayTransactions = (int)($todayData['receipt_count'] ?? 0);
        $yesterdayTransactions = (int)($yesterdayData['receipt_count'] ?? 0);
        $transactionsChange = $yesterdayTransactions > 0 ? (($todayTransactions - $yesterdayTransactions) / $yesterdayTransactions) * 100 : 0;
        
        // Get active target achievement
        $stmtTarget = $pdo->prepare("
            SELECT t.*, 
                   COALESCE(SUM(cd.sales_volume), 0) as current_sales
            FROM targets t
            LEFT JOIN churn_data cd ON cd.user_id = t.user_id 
                AND cd.date BETWEEN t.start_date AND t.end_date
            WHERE t.user_id = ? 
                AND t.end_date >= CURDATE()
                AND t.target_type = 'sales'
            GROUP BY t.id
            ORDER BY t.created_at DESC
            LIMIT 1
        ");
        $stmtTarget->execute([$uid]);
        $targetData = $stmtTarget->fetch(PDO::FETCH_ASSOC);
        
        $targetAchievement = 0;
        $targetStatus = 'No active target';
        
        if ($targetData) {
            $currentSales = (float)$targetData['current_sales'];
            $targetValue = (float)$targetData['target_value'];
            $targetAchievement = $targetValue > 0 ? ($currentSales / $targetValue) * 100 : 0;
            $targetStatus = $targetData['target_name'];
        }
        
        j_ok([
            'today_sales' => $todaySales,
            'sales_change' => round($salesChange, 2),
            'today_customers' => $todayCustomers,
            'customers_change' => round($customersChange, 2),
            'today_transactions' => $todayTransactions,
            'transactions_change' => round($transactionsChange, 2),
            'target_achievement' => round($targetAchievement, 2),
            'target_status' => $targetStatus
        ]);
        
    } catch (Throwable $e) {
        j_err('Failed to load KPI summary', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Get trend data for charts
if ($action === 'trend_data') {
    try {
        $days = (int)($_GET['days'] ?? 30);
        $days = max(7, min(90, $days));
        
        $stmt = $pdo->prepare("
            SELECT date, sales_volume, receipt_count, customer_traffic
            FROM churn_data
            WHERE user_id = ? 
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$uid, $days]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        j_ok(['trend_data' => $data]);
        
    } catch (Throwable $e) {
        j_err('Failed to load trend data', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

j_err('Unknown action', 400);