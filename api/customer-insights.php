<?php
session_start();
header('Content-Type: application/json');

define('DB_HOST', 'localhost');
define('DB_NAME', 'u393812660_churnguard');
define('DB_USER', 'u393812660_churnguard');
define('DB_PASS', '102202Brian_');

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        case 'loyal_customers':
            $result = getLoyalCustomers($pdo, $user_id);
            break;
        case 'retention_analytics':
            $result = getRetentionAnalytics($pdo, $user_id);
            break;
        case 'purchase_intelligence':
            $result = getPurchaseIntelligence($pdo, $user_id);
            break;
        case 'churn_segments':
            $result = getChurnSegments($pdo, $user_id);
            break;
        case 'executive_summary':
            $result = getExecutiveSummary($pdo, $user_id);
            break;
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function getLoyalCustomers($pdo, $user_id) {
    $sql = "
        SELECT 
            customer_name,
            gender,
            COUNT(*) as total_visits,
            SUM(total_amount) as total_spent,
            AVG(total_amount) as avg_transaction,
            MAX(date_visited) as last_visit,
            MIN(date_visited) as first_visit,
            ROUND(SUM(total_amount) / 2, 2) as monthly_avg,
            ROUND(COUNT(*) / 2, 0) as monthly_visits
        FROM transaction_logs
        WHERE user_id = :user_id 
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
          AND customer_name NOT LIKE 'Customer_%'
        GROUP BY customer_name, gender
        HAVING total_visits >= 200
        ORDER BY total_spent DESC, total_visits DESC
        LIMIT 3
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $rank = 1;
    foreach ($customers as &$customer) {
        $customer['rank'] = $rank++;
        $customer['initials'] = getInitials($customer['customer_name']);
        $customer['last_visit_formatted'] = formatDate($customer['last_visit']);
        $customer['trend'] = generateTrendData($pdo, $user_id, $customer['customer_name']);
    }
    
    return $customers;
}

function getRetentionAnalytics($pdo, $user_id) {
    $analytics = [];
    
    $sql_week = "
        SELECT COUNT(DISTINCT customer_name) as count
        FROM (
            SELECT 
                customer_name,
                COUNT(*) as this_week_visits,
                (SELECT COUNT(*) 
                 FROM transaction_logs t2 
                 WHERE t2.customer_name = t1.customer_name 
                   AND t2.user_id = :user_id
                   AND t2.date_visited BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) 
                                            AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                ) as last_week_visits
            FROM transaction_logs t1
            WHERE user_id = :user_id
              AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND customer_name NOT LIKE 'Customer_%'
            GROUP BY customer_name
            HAVING this_week_visits < last_week_visits
        ) as dropped
    ";
    
    $stmt = $pdo->prepare($sql_week);
    $stmt->execute(['user_id' => $user_id]);
    $analytics['dropped_this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $sql_month = "
        SELECT COUNT(DISTINCT customer_name) as count
        FROM (
            SELECT 
                customer_name,
                COUNT(*) as this_month_visits,
                (SELECT COUNT(*) 
                 FROM transaction_logs t2 
                 WHERE t2.customer_name = t1.customer_name 
                   AND t2.user_id = :user_id
                   AND t2.date_visited BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) 
                                            AND DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ) as last_month_visits
            FROM transaction_logs t1
            WHERE user_id = :user_id
              AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              AND customer_name NOT LIKE 'Customer_%'
            GROUP BY customer_name
            HAVING this_month_visits < last_month_visits
        ) as dropped
    ";
    
    $stmt = $pdo->prepare($sql_month);
    $stmt->execute(['user_id' => $user_id]);
    $analytics['dropped_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $sql_health = "
        SELECT 
            risk_level,
            COUNT(*) as customer_count
        FROM (
            SELECT DISTINCT 
                customer_name,
                CASE 
                    WHEN MAX(date_visited) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) THEN 'Low'
                    WHEN MAX(date_visited) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 'Medium'
                    ELSE 'High'
                END as risk_level
            FROM transaction_logs
            WHERE user_id = :user_id
              AND customer_name NOT LIKE 'Customer_%'
            GROUP BY customer_name
        ) as customer_health
        GROUP BY risk_level
    ";
    
    $stmt = $pdo->prepare($sql_health);
    $stmt->execute(['user_id' => $user_id]);
    $health = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $analytics['health_segments'] = [
        'healthy' => 0,
        'at_risk' => 0,
        'critical' => 0
    ];
    
    foreach ($health as $segment) {
        if ($segment['risk_level'] == 'Low') {
            $analytics['health_segments']['healthy'] = $segment['customer_count'];
        } elseif ($segment['risk_level'] == 'Medium') {
            $analytics['health_segments']['at_risk'] = $segment['customer_count'];
        } else {
            $analytics['health_segments']['critical'] = $segment['customer_count'];
        }
    }
    
    $sql_risk = "
        SELECT 
            customer_name,
            gender,
            SUM(total_amount) as ltv,
            MAX(date_visited) as last_visit,
            DATEDIFF(CURDATE(), MAX(date_visited)) as days_inactive
        FROM transaction_logs
        WHERE user_id = :user_id
          AND customer_name NOT LIKE 'Customer_%'
        GROUP BY customer_name, gender
        HAVING days_inactive >= 15 AND ltv >= 50000
        ORDER BY ltv DESC
        LIMIT 3
    ";
    
    $stmt = $pdo->prepare($sql_risk);
    $stmt->execute(['user_id' => $user_id]);
    $risk_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($risk_customers as &$customer) {
        $customer['initials'] = getInitials($customer['customer_name']);
        $customer['ltv_formatted'] = '₱' . number_format($customer['ltv'], 0);
    }
    
    $analytics['at_risk_customers'] = $risk_customers;
    
    $total = $analytics['health_segments']['healthy'] + 
             $analytics['health_segments']['at_risk'] + 
             $analytics['health_segments']['critical'];
    
    if ($total > 0) {
        $analytics['health_percentages'] = [
            'healthy' => round(($analytics['health_segments']['healthy'] / $total) * 100),
            'at_risk' => round(($analytics['health_segments']['at_risk'] / $total) * 100),
            'critical' => round(($analytics['health_segments']['critical'] / $total) * 100)
        ];
    }
    
    return $analytics;
}

function getPurchaseIntelligence($pdo, $user_id) {
    $intelligence = [];
    
    $sql_overview = "
        SELECT 
            AVG(quantity_of_drinks) as avg_basket_size,
            AVG(total_amount) as avg_transaction,
            COUNT(*) as total_transactions
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    
    $stmt = $pdo->prepare($sql_overview);
    $stmt->execute(['user_id' => $user_id]);
    $overview = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $intelligence['avg_basket_size'] = round($overview['avg_basket_size'], 1);
    $intelligence['avg_transaction'] = round($overview['avg_transaction'], 0);
    
    $sql_products = "
        SELECT 
            type_of_drink as product,
            COUNT(*) as order_count,
            SUM(total_amount) as revenue
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY type_of_drink
        ORDER BY revenue DESC
        LIMIT 3
    ";
    
    $stmt = $pdo->prepare($sql_products);
    $stmt->execute(['user_id' => $user_id]);
    $intelligence['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // FIXED: Correct repeat purchase calculation
    $sql_repeat = "
        SELECT 
            type_of_drink as product,
            COUNT(DISTINCT customer_name) as unique_customers,
            COUNT(*) as total_orders,
            COUNT(DISTINCT customer_name) - COUNT(DISTINCT CASE WHEN purchase_count = 1 THEN customer_name END) as returning_customers,
            ROUND(
                ((COUNT(DISTINCT customer_name) - COUNT(DISTINCT CASE WHEN purchase_count = 1 THEN customer_name END)) / 
                COUNT(DISTINCT customer_name)) * 100, 
                0
            ) as repeat_rate
        FROM (
            SELECT 
                type_of_drink,
                customer_name,
                COUNT(*) as purchase_count
            FROM transaction_logs
            WHERE user_id = :user_id
              AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
              AND customer_name NOT LIKE 'Customer_%'
            GROUP BY type_of_drink, customer_name
        ) as customer_purchases
        GROUP BY type_of_drink
        HAVING unique_customers >= 3
        ORDER BY repeat_rate DESC, total_orders DESC
        LIMIT 3
    ";
    
    $stmt = $pdo->prepare($sql_repeat);
    $stmt->execute(['user_id' => $user_id]);
    $repeat_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data
    foreach ($repeat_products as &$product) {
        $product['unique_customers'] = $product['returning_customers'];
    }
    
    $intelligence['repeat_rate_products'] = $repeat_products;
    
    return $intelligence;
}

function getChurnSegments($pdo, $user_id) {
    $segments = [];
    
    // FIXED: Proper churn calculation by gender
    $sql_gender = "
        SELECT 
            gender,
            COUNT(DISTINCT customer_name) as total_customers,
            SUM(CASE WHEN days_since_last > 30 THEN 1 ELSE 0 END) as churned_customers,
            ROUND(
                (SUM(CASE WHEN days_since_last > 30 THEN 1 ELSE 0 END) / COUNT(DISTINCT customer_name)) * 100,
                1
            ) as churn_rate
        FROM (
            SELECT 
                customer_name,
                gender,
                DATEDIFF(CURDATE(), MAX(date_visited)) as days_since_last
            FROM transaction_logs
            WHERE user_id = :user_id
              AND customer_name NOT LIKE 'Customer_%'
              AND gender IS NOT NULL
              AND gender != ''
            GROUP BY customer_name, gender
        ) as customer_activity
        GROUP BY gender
        HAVING total_customers > 0
        ORDER BY gender
    ";
    
    $stmt = $pdo->prepare($sql_gender);
    $stmt->execute(['user_id' => $user_id]);
    $segments['by_gender'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // FIXED: Proper churn calculation by category
    $sql_category = "
        SELECT 
            category,
            COUNT(DISTINCT customer_name) as total_customers,
            SUM(CASE WHEN days_since_last > 30 THEN 1 ELSE 0 END) as churned_customers,
            ROUND(
                (SUM(CASE WHEN days_since_last > 30 THEN 1 ELSE 0 END) / COUNT(DISTINCT customer_name)) * 100,
                1
            ) as churn_rate
        FROM (
            SELECT 
                customer_name,
                CASE 
                    WHEN type_of_drink IN ('Iced Coffee', 'Americano', 'Cappuccino', 'Latte', 'Espresso', 'Mocha') THEN 'Hot Beverages'
                    WHEN type_of_drink IN ('Frappe', 'Matcha Latte', 'Iced Tea', 'Smoothie', 'Cold Brew') THEN 'Cold Beverages'
                    ELSE 'Other'
                END as category,
                DATEDIFF(CURDATE(), MAX(date_visited)) as days_since_last
            FROM transaction_logs
            WHERE user_id = :user_id
              AND customer_name NOT LIKE 'Customer_%'
            GROUP BY customer_name, type_of_drink
        ) as customer_categories
        GROUP BY category
        HAVING total_customers > 0
        ORDER BY category
    ";
    
    $stmt = $pdo->prepare($sql_category);
    $stmt->execute(['user_id' => $user_id]);
    $segments['by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $segments;
}

function getExecutiveSummary($pdo, $user_id) {
    $summary = [];
    
    $sql_customers = "
        SELECT COUNT(DISTINCT customer_name) as total
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND customer_name NOT LIKE 'Customer_%'
    ";
    
    $stmt = $pdo->prepare($sql_customers);
    $stmt->execute(['user_id' => $user_id]);
    $summary['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $sql_revenue = "
        SELECT SUM(sales_volume) as total
        FROM churn_data
        WHERE user_id = :user_id
          AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    
    $stmt = $pdo->prepare($sql_revenue);
    $stmt->execute(['user_id' => $user_id]);
    $summary['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    return $summary;
}

function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2);
}

function formatDate($date) {
    $datetime = new DateTime($date);
    $now = new DateTime();
    $diff = $now->diff($datetime);
    
    if ($diff->days == 0) return 'Today';
    if ($diff->days == 1) return 'Yesterday';
    if ($diff->days < 7) return $diff->days . ' days ago';
    
    return $datetime->format('M d Y');
}

function generateTrendData($pdo, $user_id, $customer_name) {
    $sql = "
        SELECT total_amount
        FROM transaction_logs
        WHERE user_id = :user_id 
          AND customer_name = :customer_name
        ORDER BY date_visited DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id, 'customer_name' => $customer_name]);
    $amounts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($amounts)) {
        return "0,20 100,20";
    }
    
    $points = [];
    $max = max($amounts) ?: 1;
    foreach (array_reverse($amounts) as $i => $amount) {
        $x = $i * (100 / (count($amounts) - 1));
        $y = 35 - (($amount / $max) * 30);
        $points[] = round($x, 2) . ',' . round($y, 2);
    }
    
    return implode(' ', $points);
}
?>