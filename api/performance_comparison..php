<?php
// api/performance_comparison.php - Professional Performance Analytics API

$db_host = 'localhost';
$db_name = 'u393812660_churnguard';
$db_user = 'u393812660_churnguard';
$db_pass = '102202Brian_';

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','error'=>'unauthorized','message'=>'User not authenticated']);
        exit;
    }
    
    $current_user_id = (int)$_SESSION['user_id'];
    
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    function json_success($data = []) {
        echo json_encode(['status' => 'success', 'data' => $data]);
        exit;
    }
    
    function json_error($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit;
    }
    
    $action = $_GET['action'] ?? 'dashboard';
    
    // ========== DASHBOARD OVERVIEW ==========
    if ($action === 'dashboard') {
        $currentYear = (int)date('Y');
        $previousYear = $currentYear - 1;
        $currentMonth = (int)date('n');
        
        // Yearly performance
        $qCurrent = $pdo->prepare("
            SELECT 
                SUM(total_sales) as total_sales,
                SUM(total_customers) as total_customers,
                SUM(new_customers) as new_customers,
                SUM(returning_customers) as returning_customers,
                SUM(total_transactions) as total_transactions,
                AVG(average_transaction_value) as avg_transaction_value
            FROM yearly_performance
            WHERE user_id = ? AND year = ?
        ");
        $qCurrent->execute([$current_user_id, $currentYear]);
        $current = $qCurrent->fetch() ?: [
            'total_sales'=>0, 'total_customers'=>0, 'new_customers'=>0, 
            'returning_customers'=>0, 'total_transactions'=>0, 'avg_transaction_value'=>0
        ];
        
        $qPrevious = $pdo->prepare("
            SELECT 
                SUM(total_sales) as total_sales,
                SUM(total_customers) as total_customers,
                SUM(new_customers) as new_customers,
                SUM(returning_customers) as returning_customers,
                SUM(total_transactions) as total_transactions,
                AVG(average_transaction_value) as avg_transaction_value
            FROM yearly_performance
            WHERE user_id = ? AND year = ?
        ");
        $qPrevious->execute([$current_user_id, $previousYear]);
        $previous = $qPrevious->fetch() ?: [
            'total_sales'=>0, 'total_customers'=>0, 'new_customers'=>0,
            'returning_customers'=>0, 'total_transactions'=>0, 'avg_transaction_value'=>0
        ];
        
        // Calculate growth metrics
        $salesGrowth = $previous['total_sales'] > 0 ? 
            (($current['total_sales'] - $previous['total_sales']) / $previous['total_sales']) * 100 : 0;
        $customerGrowth = $previous['total_customers'] > 0 ? 
            (($current['total_customers'] - $previous['total_customers']) / $previous['total_customers']) * 100 : 0;
        $transactionGrowth = $previous['total_transactions'] > 0 ? 
            (($current['total_transactions'] - $previous['total_transactions']) / $previous['total_transactions']) * 100 : 0;
        $newCustomerGrowth = $previous['new_customers'] > 0 ? 
            (($current['new_customers'] - $previous['new_customers']) / $previous['new_customers']) * 100 : 0;
        
        // Monthly data for charts
        $qMonthly = $pdo->prepare("
            SELECT year, month, total_sales, total_customers, total_transactions, new_customers
            FROM yearly_performance
            WHERE user_id = ? AND year IN (?, ?)
            ORDER BY year, month
        ");
        $qMonthly->execute([$current_user_id, $currentYear, $previousYear]);
        $monthlyRaw = $qMonthly->fetchAll();
        
        $monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $monthlyChart = [
            'labels' => $monthLabels,
            'current_sales' => array_fill(0, 12, null),
            'previous_sales' => array_fill(0, 12, null),
            'current_customers' => array_fill(0, 12, null),
            'previous_customers' => array_fill(0, 12, null),
            'current_transactions' => array_fill(0, 12, null)
        ];
        
        foreach ($monthlyRaw as $row) {
            $idx = (int)$row['month'] - 1;
            if ($row['year'] == $currentYear) {
                $monthlyChart['current_sales'][$idx] = (float)$row['total_sales'];
                $monthlyChart['current_customers'][$idx] = (int)$row['total_customers'];
                $monthlyChart['current_transactions'][$idx] = (int)$row['total_transactions'];
            } else {
                $monthlyChart['previous_sales'][$idx] = (float)$row['total_sales'];
                $monthlyChart['previous_customers'][$idx] = (int)$row['total_customers'];
            }
        }
        
        // Get all targets
        $qTargets = $pdo->prepare("
            SELECT * FROM performance_targets
            WHERE user_id = ? AND year = ?
            ORDER BY 
                CASE target_period 
                    WHEN 'yearly' THEN 1 
                    WHEN 'quarterly' THEN 2 
                    WHEN 'monthly' THEN 3 
                END,
                target_type
        ");
        $qTargets->execute([$current_user_id, $currentYear]);
        $targets = $qTargets->fetchAll();
        
        // Calculate target progress
        $targetProgress = [];
        $achievedCount = 0;
        $onTrackCount = 0;
        $needsAttentionCount = 0;
        
        foreach ($targets as $target) {
            $currentValue = 0;
            $targetValue = (float)$target['target_value'];
            $period = $target['target_period'];
            
            // Adjust target value based on period
            $adjustedTarget = $targetValue;
            if ($period === 'monthly') {
                $adjustedTarget = $targetValue * $currentMonth;
            } elseif ($period === 'quarterly') {
                $quarter = ceil($currentMonth / 3);
                $adjustedTarget = $targetValue * $quarter;
            }
            
            // Get current value
            switch ($target['target_type']) {
                case 'sales':
                    $currentValue = (float)$current['total_sales'];
                    break;
                case 'customers':
                    $currentValue = (float)$current['total_customers'];
                    break;
                case 'transactions':
                    $currentValue = (float)$current['total_transactions'];
                    break;
                case 'growth_rate':
                    $currentValue = $salesGrowth;
                    $adjustedTarget = $targetValue; // Growth rate doesn't scale
                    break;
            }
            
            $progress = $adjustedTarget > 0 ? min(100, ($currentValue / $adjustedTarget) * 100) : 0;
            
            // Determine status
            $status = 'needs_attention';
            if ($progress >= 100) {
                $status = 'achieved';
                $achievedCount++;
            } elseif ($progress >= 70) {
                $status = 'on_track';
                $onTrackCount++;
            } else {
                $needsAttentionCount++;
            }
            
            // Calculate remaining amount
            $remaining = max(0, $adjustedTarget - $currentValue);
            
            $targetProgress[] = [
                'id' => (int)$target['id'],
                'type' => $target['target_type'],
                'period' => $target['target_period'],
                'target_value' => $targetValue,
                'adjusted_target' => round($adjustedTarget, 2),
                'current_value' => round($currentValue, 2),
                'remaining' => round($remaining, 2),
                'progress' => round($progress, 2),
                'status' => $status
            ];
        }
        
        // Performance insights
        $insights = [];
        
        if ($salesGrowth > 20) {
            $insights[] = ['type'=>'success', 'text'=>'Exceptional sales growth of '.round($salesGrowth,1).'% compared to last year'];
        } elseif ($salesGrowth < -10) {
            $insights[] = ['type'=>'warning', 'text'=>'Sales have declined by '.round(abs($salesGrowth),1).'% - review pricing and marketing strategies'];
        }
        
        if ($customerGrowth > 15) {
            $insights[] = ['type'=>'success', 'text'=>'Customer base growing strongly at '.round($customerGrowth,1).'%'];
        } elseif ($customerGrowth < 0) {
            $insights[] = ['type'=>'warning', 'text'=>'Customer base shrinking - focus on retention and acquisition'];
        }
        
        $retentionRate = $current['total_customers'] > 0 ? 
            ($current['returning_customers'] / $current['total_customers']) * 100 : 0;
        if ($retentionRate > 70) {
            $insights[] = ['type'=>'success', 'text'=>'Strong customer retention at '.round($retentionRate,1).'%'];
        } elseif ($retentionRate < 40) {
            $insights[] = ['type'=>'warning', 'text'=>'Low retention rate of '.round($retentionRate,1).'% - improve customer experience'];
        }
        
        if ($achievedCount > 0) {
            $insights[] = ['type'=>'success', 'text'=>$achievedCount.' target'.($achievedCount>1?'s':'').' achieved!'];
        }
        
        json_success([
            'years' => ['current' => $currentYear, 'previous' => $previousYear],
            'current_month' => $currentMonth,
            'current' => [
                'sales' => (float)$current['total_sales'],
                'customers' => (int)$current['total_customers'],
                'new_customers' => (int)$current['new_customers'],
                'returning_customers' => (int)$current['returning_customers'],
                'transactions' => (int)$current['total_transactions'],
                'avg_value' => (float)$current['avg_transaction_value']
            ],
            'previous' => [
                'sales' => (float)$previous['total_sales'],
                'customers' => (int)$previous['total_customers'],
                'transactions' => (int)$previous['total_transactions'],
                'avg_value' => (float)$previous['avg_transaction_value']
            ],
            'growth' => [
                'sales' => round($salesGrowth, 2),
                'customers' => round($customerGrowth, 2),
                'transactions' => round($transactionGrowth, 2),
                'new_customers' => round($newCustomerGrowth, 2)
            ],
            'monthly_chart' => $monthlyChart,
            'targets' => $targetProgress,
            'target_summary' => [
                'total' => count($targets),
                'achieved' => $achievedCount,
                'on_track' => $onTrackCount,
                'needs_attention' => $needsAttentionCount
            ],
            'insights' => $insights,
            'has_data' => ($current['total_sales'] > 0 || $previous['total_sales'] > 0)
        ]);
    }
    
    // ========== SET TARGET ==========
    if ($action === 'set_target') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        
        $year = (int)($data['year'] ?? date('Y'));
        $targetType = trim($data['target_type'] ?? '');
        $targetValue = (float)($data['target_value'] ?? 0);
        $targetPeriod = trim($data['target_period'] ?? 'yearly');
        
        if (!in_array($targetType, ['sales', 'customers', 'growth_rate', 'transactions'])) {
            json_error('Invalid target type', 422);
        }
        
        if (!in_array($targetPeriod, ['monthly', 'quarterly', 'yearly'])) {
            json_error('Invalid target period', 422);
        }
        
        if ($targetValue <= 0) {
            json_error('Target value must be greater than 0', 422);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO performance_targets 
                (user_id, year, target_type, target_value, target_period, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                target_value = VALUES(target_value),
                updated_at = NOW()
        ");
        $stmt->execute([$current_user_id, $year, $targetType, $targetValue, $targetPeriod]);
        
        json_success(['saved' => true, 'message' => 'Target saved successfully']);
    }
    
    // ========== UPDATE TARGET ==========
    if ($action === 'update_target') {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;
        
        $id = (int)($data['id'] ?? 0);
        $targetValue = (float)($data['target_value'] ?? 0);
        
        if ($targetValue <= 0) {
            json_error('Target value must be greater than 0', 422);
        }
        
        $stmt = $pdo->prepare("
            UPDATE performance_targets 
            SET target_value = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$targetValue, $id, $current_user_id]);
        
        json_success(['updated' => true, 'message' => 'Target updated successfully']);
    }
    
    // ========== DELETE TARGET ==========
    if ($action === 'delete_target') {
        $id = (int)($_GET['id'] ?? 0);
        
        $stmt = $pdo->prepare("DELETE FROM performance_targets WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $current_user_id]);
        
        json_success(['deleted' => true, 'message' => 'Target deleted successfully']);
    }
    
    json_error('Unknown action', 400);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
}