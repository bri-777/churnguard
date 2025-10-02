<?php
// api/performance_tracker.php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

$action = $_GET['action'] ?? 'dashboard';

if ($action === 'dashboard') {
    try {
        $current_year = date('Y');
        $previous_year = $current_year - 1;
        
        // Get aggregated data for both years
        $sql = "
            SELECT 
                year,
                SUM(total_sales) as total_sales,
                SUM(total_customers) as total_customers,
                SUM(new_customers) as new_customers,
                SUM(returning_customers) as returning_customers,
                AVG(average_transaction_value) as avg_transaction_value,
                SUM(total_transactions) as total_transactions,
                COUNT(DISTINCT month) as months_recorded
            FROM yearly_performance 
            WHERE user_id = ? AND year IN (?, ?)
            GROUP BY year
            ORDER BY year DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $current_year, $previous_year]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $current_data = null;
        $previous_data = null;
        
        foreach ($results as $row) {
            if ($row['year'] == $current_year) {
                $current_data = $row;
            } else {
                $previous_data = $row;
            }
        }
        
        // Initialize with zeros if no data
        if (!$current_data) {
            $current_data = [
                'year' => $current_year,
                'total_sales' => 0,
                'total_customers' => 0,
                'new_customers' => 0,
                'returning_customers' => 0,
                'avg_transaction_value' => 0,
                'total_transactions' => 0,
                'months_recorded' => 0
            ];
        }
        
        if (!$previous_data) {
            $previous_data = [
                'year' => $previous_year,
                'total_sales' => 0,
                'total_customers' => 0,
                'new_customers' => 0,
                'returning_customers' => 0,
                'avg_transaction_value' => 0,
                'total_transactions' => 0,
                'months_recorded' => 0
            ];
        }
        
        // Calculate growth percentages
        $sales_growth = $previous_data['total_sales'] > 0 ? 
            (($current_data['total_sales'] - $previous_data['total_sales']) / $previous_data['total_sales']) * 100 : 0;
        
        $customer_growth = $previous_data['total_customers'] > 0 ? 
            (($current_data['total_customers'] - $previous_data['total_customers']) / $previous_data['total_customers']) * 100 : 0;
        
        // Get monthly data for charts
        $monthly_sql = "
            SELECT month, year, total_sales, total_customers, total_transactions
            FROM yearly_performance
            WHERE user_id = ? AND year IN (?, ?)
            ORDER BY year, month
        ";
        
        $monthly_stmt = $pdo->prepare($monthly_sql);
        $monthly_stmt->execute([$uid, $current_year, $previous_year]);
        $monthly_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get targets
        $targets_sql = "
            SELECT target_type, target_value, target_period
            FROM performance_targets
            WHERE user_id = ? AND year = ?
        ";
        
        $targets_stmt = $pdo->prepare($targets_sql);
        $targets_stmt->execute([$uid, $current_year]);
        $targets = $targets_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        j_ok([
            'current' => $current_data,
            'previous' => $previous_data,
            'growth' => [
                'sales_percent' => round($sales_growth, 2),
                'customer_percent' => round($customer_growth, 2),
                'sales_amount' => $current_data['total_sales'] - $previous_data['total_sales'],
                'customer_count' => $current_data['total_customers'] - $previous_data['total_customers']
            ],
            'monthly' => $monthly_data,
            'targets' => $targets
        ]);
        
    } catch (Throwable $e) {
        j_err('Failed to load dashboard', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_target') {
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $year = $input['year'] ?? date('Y');
        $type = $input['type'] ?? '';
        $value = (float)($input['value'] ?? 0);
        $period = $input['period'] ?? 'yearly';
        
        $sql = "
            INSERT INTO performance_targets 
                (user_id, year, target_type, target_value, target_period)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                target_value = VALUES(target_value),
                updated_at = NOW()
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$uid, $year, $type, $value, $period]);
        
        j_ok(['saved' => true]);
        
    } catch (Throwable $e) {
        j_err('Failed to save target', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'save_monthly') {
    try {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        $year = $input['year'] ?? date('Y');
        $month = $input['month'] ?? date('n');
        $sales = (float)($input['sales'] ?? 0);
        $customers = (int)($input['customers'] ?? 0);
        $new = (int)($input['new_customers'] ?? 0);
        $transactions = (int)($input['transactions'] ?? 0);
        
        $avg_value = $transactions > 0 ? $sales / $transactions : 0;
        $returning = max(0, $customers - $new);
        
        $sql = "
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
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $uid, $year, $month, $sales, $customers, $new, 
            $returning, $avg_value, $transactions
        ]);
        
        j_ok(['saved' => true]);
        
    } catch (Throwable $e) {
        j_err('Failed to save monthly data', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

j_err('Unknown action', 400);