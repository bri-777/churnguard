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
        $currentDate = $_GET['current_date'] ?? date('Y-m-d');
        $compareDate = $_GET['compare_date'] ?? date('Y-m-d', strtotime('-1 day'));
        
        // Get current date data
        $stmt = $pdo->prepare("
            SELECT * FROM churn_data 
            WHERE user_id = ? AND date = ?
        ");
        $stmt->execute([$uid, $currentDate]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get compare date data
        $stmt->execute([$uid, $compareDate]);
        $compare = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$current) $current = ['receipt_count' => 0, 'sales_volume' => 0, 'customer_traffic' => 0];
        if (!$compare) $compare = ['receipt_count' => 0, 'sales_volume' => 0, 'customer_traffic' => 0];
        
        // Calculate comparisons
        $metrics = [
            [
                'name' => 'Sales Volume',
                'current' => (float)($current['sales_volume'] ?? 0),
                'compare' => (float)($compare['sales_volume'] ?? 0),
                'format' => 'currency'
            ],
            [
                'name' => 'Transactions',
                'current' => (int)($current['receipt_count'] ?? 0),
                'compare' => (int)($compare['receipt_count'] ?? 0),
                'format' => 'number'
            ],
            [
                'name' => 'Customer Traffic',
                'current' => (int)($current['customer_traffic'] ?? 0),
                'compare' => (int)($compare['customer_traffic'] ?? 0),
                'format' => 'number'
            ],
            [
                'name' => 'Avg Transaction Value',
                'current' => $current['receipt_count'] > 0 ? $current['sales_volume'] / $current['receipt_count'] : 0,
                'compare' => $compare['receipt_count'] > 0 ? $compare['sales_volume'] / $compare['receipt_count'] : 0,
                'format' => 'currency'
            ]
        ];
        
        $results = [];
        foreach ($metrics as $metric) {
            $diff = $metric['current'] - $metric['compare'];
            $pct = $metric['compare'] > 0 ? ($diff / $metric['compare']) * 100 : 0;
            
            $results[] = [
                'metric' => $metric['name'],
                'current' => $metric['current'],
                'compare' => $metric['compare'],
                'difference' => $diff,
                'percentage' => round($pct, 2),
                'trend' => $diff >= 0 ? 'up' : 'down',
                'format' => $metric['format']
            ];
        }
        
        j_ok([
            'success' => true,
            'current_date' => $currentDate,
            'compare_date' => $compareDate,
            'metrics' => $results
        ]);
    } catch (Throwable $e) {
        j_err('Comparison failed', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Get today's summary
if ($action === 'today_summary') {
    try {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $stmt = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? AND date = ?");
        
        $stmt->execute([$uid, $today]);
        $todayData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt->execute([$uid, $yesterday]);
        $yesterdayData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$todayData) $todayData = ['receipt_count' => 0, 'sales_volume' => 0, 'customer_traffic' => 0];
        if (!$yesterdayData) $yesterdayData = ['receipt_count' => 0, 'sales_volume' => 0, 'customer_traffic' => 0];
        
        j_ok([
            'sales' => (float)($todayData['sales_volume'] ?? 0),
            'traffic' => (int)($todayData['customer_traffic'] ?? 0),
            'transactions' => (int)($todayData['receipt_count'] ?? 0),
            'sales_change' => $yesterdayData['sales_volume'] > 0 ? 
                round((($todayData['sales_volume'] - $yesterdayData['sales_volume']) / $yesterdayData['sales_volume']) * 100, 2) : 0,
            'traffic_change' => $yesterdayData['customer_traffic'] > 0 ? 
                round((($todayData['customer_traffic'] - $yesterdayData['customer_traffic']) / $yesterdayData['customer_traffic']) * 100, 2) : 0,
            'transactions_change' => $yesterdayData['receipt_count'] > 0 ? 
                round((($todayData['receipt_count'] - $yesterdayData['receipt_count']) / $yesterdayData['receipt_count']) * 100, 2) : 0
        ]);
    } catch (Throwable $e) {
        j_err('Failed to get today summary', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

// Get trend data for charts
if ($action === 'trend_data') {
    try {
        $days = (int)($_GET['days'] ?? 7);
        
        $stmt = $pdo->prepare("
            SELECT date, sales_volume, receipt_count, customer_traffic
            FROM churn_data
            WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$uid, $days]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        j_ok(['data' => $data]);
    } catch (Throwable $e) {
        j_err('Failed to get trend data', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

j_err('Invalid action', 400);