<?php
session_start();
header('Content-Type: application/json');

define('DB_HOST', 'localhost');
define('DB_NAME', 'u393812660_churnguard');
define('DB_USER', 'u393812660_churnguard');
define('DB_PASS', '102202Brian_');

$user_id = (int)$_SESSION['user_id'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $insights = [];
    
    // 1. DAILY AVERAGE RECEIPTS (transactions per day)
    $sql_daily = "
        SELECT 
            COUNT(*) / COUNT(DISTINCT DATE(date_visited)) as daily_avg_receipts
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    $stmt = $pdo->prepare($sql_daily);
    $stmt->execute(['user_id' => $user_id]);
    $insights['daily_avg_receipts'] = round($stmt->fetch(PDO::FETCH_ASSOC)['daily_avg_receipts']);
    
    // 2. CONVERSION RATE (unique customers who made purchases vs total visits)
    $sql_conversion = "
        SELECT 
            COUNT(*) as total_transactions,
            COUNT(DISTINCT customer_name) as unique_customers,
            ROUND((COUNT(*) / COUNT(DISTINCT customer_name)) * 100, 1) as conversion_rate
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    $stmt = $pdo->prepare($sql_conversion);
    $stmt->execute(['user_id' => $user_id]);
    $conversion = $stmt->fetch(PDO::FETCH_ASSOC);
    $insights['conversion_rate'] = $conversion['conversion_rate'];
    
    // 3. PEAK DAY (day of week with most transactions)
    $sql_peak_day = "
        SELECT 
            DAYNAME(date_visited) as day_name,
            COUNT(*) as transaction_count
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DAYNAME(date_visited), DAYOFWEEK(date_visited)
        ORDER BY transaction_count DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql_peak_day);
    $stmt->execute(['user_id' => $user_id]);
    $peak_day = $stmt->fetch(PDO::FETCH_ASSOC);
    $insights['peak_day'] = $peak_day['day_name'] ?? 'N/A';
    
    // 4. RUSH HOURS (hour with most transactions)
    $sql_rush = "
        SELECT 
            HOUR(date_visited) as hour,
            COUNT(*) as count
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY HOUR(date_visited)
        ORDER BY count DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql_rush);
    $stmt->execute(['user_id' => $user_id]);
    $rush_hour = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rush_hour) {
        $hour = $rush_hour['hour'];
        $end_hour = $hour + 2;
        $insights['rush_hours'] = sprintf("%dAM - %dAM", $hour, $end_hour);
    } else {
        $insights['rush_hours'] = '8AM - 10AM';
    }
    
    // 5. WEEKEND vs WEEKDAY TRAFFIC
    $sql_weekend = "
        SELECT 
            SUM(CASE WHEN DAYOFWEEK(date_visited) IN (1,7) THEN 1 ELSE 0 END) as weekend_count,
            SUM(CASE WHEN DAYOFWEEK(date_visited) NOT IN (1,7) THEN 1 ELSE 0 END) as weekday_count
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    $stmt = $pdo->prepare($sql_weekend);
    $stmt->execute(['user_id' => $user_id]);
    $weekend_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $weekend_avg = $weekend_data['weekend_count'] / 8; // ~8 weekend days in 30 days
    $weekday_avg = $weekend_data['weekday_count'] / 22; // ~22 weekdays in 30 days
    $weekend_diff = (($weekend_avg - $weekday_avg) / $weekday_avg) * 100;
    $insights['weekend_traffic'] = round($weekend_diff) . '% ' . ($weekend_diff > 0 ? 'higher' : 'lower');
    
    // 6. LAST 7 DAYS MINI CHART DATA
    $sql_chart = "
        SELECT 
            DATE(date_visited) as date,
            COUNT(*) as count
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(date_visited)
        ORDER BY date ASC
    ";
    $stmt = $pdo->prepare($sql_chart);
    $stmt->execute(['user_id' => $user_id]);
    $chart_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Normalize to percentages for mini chart
    $counts = array_column($chart_data, 'count');
    $max_count = max($counts) ?: 1;
    $insights['mini_chart'] = array_map(function($count) use ($max_count) {
        return round(($count / $max_count) * 100);
    }, $counts);
    
    // 7. ANOMALY DETECTION (day with unusual drop)
    $sql_anomaly = "
        SELECT 
            DATE(date_visited) as date,
            DAYNAME(date_visited) as day_name,
            COUNT(*) as count
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY DATE(date_visited), DAYNAME(date_visited)
        ORDER BY date DESC
    ";
    $stmt = $pdo->prepare($sql_anomaly);
    $stmt->execute(['user_id' => $user_id]);
    $anomaly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find day with biggest drop
    $insights['anomaly'] = null;
    if (count($anomaly_data) > 1) {
        $avg = array_sum(array_column($anomaly_data, 'count')) / count($anomaly_data);
        foreach ($anomaly_data as $day) {
            $drop = (($avg - $day['count']) / $avg) * 100;
            if ($drop > 15) {
                $insights['anomaly'] = [
                    'day' => $day['day_name'],
                    'drop' => round($drop)
                ];
                break;
            }
        }
    }
    
    echo json_encode($insights);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>