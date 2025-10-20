<?php
session_start();
header('Content-Type: application/json');

// Load environment variables
$env_file = __DIR__ . '/../.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'u393812660_churnguard');
define('DB_USER', 'u393812660_churnguard');
define('DB_PASS', '102202Brian_');
define('OPENAI_API_KEY', getenv('OPENAI_API_KEY'));

$user_id = (int)$_SESSION['user_id'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $predictions = [];
    
    // ========================================
    // 1. CHURN RISK ALERT - FIXED QUERY
    // ========================================
    // Count ALL customers who are 15+ days inactive
    $sql_churn = "
        SELECT COUNT(*) as at_risk_count
        FROM (
            SELECT customer_name
            FROM transaction_logs
            WHERE user_id = :user_id
            GROUP BY customer_name
            HAVING DATEDIFF(CURDATE(), MAX(date_visited)) >= 15
        ) as churned_customers
    ";
    $stmt = $pdo->prepare($sql_churn);
    $stmt->execute(['user_id' => $user_id]);
    $predictions['churn_risk_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['at_risk_count'] ?? 0;
    
    // ========================================
    // 2. REVENUE FORECAST
    // ========================================
    // Calculate trend from last 3 months and predict next month
    $sql_revenue = "
        SELECT 
            DATE_FORMAT(date, '%Y-%m') as month,
            SUM(sales_volume) as monthly_revenue
        FROM churn_data
        WHERE user_id = :user_id
          AND date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        GROUP BY DATE_FORMAT(date, '%Y-%m')
        ORDER BY month ASC
    ";
    $stmt = $pdo->prepare($sql_revenue);
    $stmt->execute(['user_id' => $user_id]);
    $revenue_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($revenue_data) >= 2) {
        $revenues = array_column($revenue_data, 'monthly_revenue');
        
        // Simple linear trend prediction
        $avg_growth = (end($revenues) - $revenues[0]) / (count($revenues) - 1);
        $predicted_revenue = end($revenues) + $avg_growth;
        $predictions['revenue_forecast'] = round($predicted_revenue);
        
        // Calculate confidence based on revenue consistency
        // Lower variance = higher confidence
        $variance = 0;
        for ($i = 1; $i < count($revenues); $i++) {
            $variance += abs($revenues[$i] - $revenues[$i-1]);
        }
        $avg_variance = $variance / (count($revenues) - 1);
        $variability_ratio = $avg_variance / end($revenues);
        
        // Convert to confidence: high variability = low confidence
        $confidence = max(65, min(95, 100 - ($variability_ratio * 100)));
        $predictions['forecast_confidence'] = round($confidence);
    } else {
        // Fallback if not enough data
        $predictions['revenue_forecast'] = 500000;
        $predictions['forecast_confidence'] = 70;
    }
    
    // ========================================
    // 3. TRENDING PRODUCTS
    // ========================================
    // Find fastest growing/declining product this week vs last week
    $sql_trending = "
        SELECT 
            type_of_drink as product,
            SUM(CASE 
                WHEN date_visited >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                THEN 1 ELSE 0 
            END) as this_week_count,
            SUM(CASE 
                WHEN date_visited BETWEEN DATE_SUB(CURDATE(), INTERVAL 14 DAY) 
                                      AND DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                THEN 1 ELSE 0 
            END) as last_week_count
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY type_of_drink
        HAVING this_week_count > 0 OR last_week_count > 0
        ORDER BY this_week_count DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql_trending);
    $stmt->execute(['user_id' => $user_id]);
    $trending = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($trending) {
        $predictions['trending_product'] = $trending['product'];
        
        // Calculate week-over-week growth
        $last_week = $trending['last_week_count'] ?: 1; // Prevent division by zero
        $this_week = $trending['this_week_count'];
        $growth = (($this_week - $last_week) / $last_week) * 100;
        $predictions['trending_growth'] = round($growth);
    } else {
        $predictions['trending_product'] = 'No data';
        $predictions['trending_growth'] = 0;
    }
    
    // ========================================
    // 4. AI INSIGHTS (OpenAI or Fallback)
    // ========================================
    if (OPENAI_API_KEY && OPENAI_API_KEY !== 'false' && OPENAI_API_KEY !== '') {
        // Use OpenAI to generate personalized insights
        $predictions['ai_insights'] = getOpenAIInsights($pdo, $user_id);
    } else {
        // Fallback: Calculate basic insights from data
        $predictions['ai_insights'] = getBasicInsights($pdo, $user_id);
    }
    
    echo json_encode($predictions);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

// ========================================
// FUNCTION: Get OpenAI Insights
// ========================================
function getOpenAIInsights($pdo, $user_id) {
    // Gather comprehensive metrics for AI analysis
    $sql_summary = "
        SELECT 
            COUNT(*) as total_transactions,
            COUNT(DISTINCT customer_name) as unique_customers,
            AVG(total_amount) as avg_transaction,
            SUM(total_amount) as total_revenue,
            COUNT(DISTINCT DATE(date_visited)) as active_days
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    $stmt = $pdo->prepare($sql_summary);
    $stmt->execute(['user_id' => $user_id]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top product
    $sql_top = "
        SELECT type_of_drink, COUNT(*) as count
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY type_of_drink
        ORDER BY count DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql_top);
    $stmt->execute(['user_id' => $user_id]);
    $top_product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $prompt = "You are a business analytics AI. Analyze this coffee shop data and provide exactly 3 specific, actionable insights in JSON format.\n\n" .
              "Data:\n" .
              "- {$summary['total_transactions']} transactions in last 30 days\n" .
              "- {$summary['unique_customers']} unique customers\n" .
              "- ₱" . round($summary['avg_transaction']) . " average transaction\n" .
              "- ₱" . round($summary['total_revenue']) . " total revenue\n" .
              "- Top product: {$top_product['type_of_drink']}\n\n" .
              "Provide insights in this exact JSON format:\n" .
              '{"query_1":"[specific insight about customer behavior]","query_2":"[specific insight about products/revenue]","query_3":"[specific insight about growth opportunities]"}';
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 250,
            'temperature' => 0.7
        ]),
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($response && $http_code === 200) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            $content = $data['choices'][0]['message']['content'];
            // Try to extract JSON from response
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $insights = json_decode($matches[0], true);
                if ($insights && count($insights) === 3) {
                    return $insights;
                }
            }
        }
    }
    
    // Fallback if OpenAI fails
    return getBasicInsights($pdo, $user_id);
}

// ========================================
// FUNCTION: Get Basic Insights (Fallback)
// ========================================
function getBasicInsights($pdo, $user_id) {
    $insights = [];
    
    // Insight 1: Peak day analysis
    $sql_peak = "
        SELECT 
            DAYNAME(date_visited) as day_name,
            COUNT(*) as count,
            (COUNT(*) / (SELECT COUNT(*) FROM transaction_logs WHERE user_id = :user_id AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))) * 100 as percentage
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DAYNAME(date_visited), DAYOFWEEK(date_visited)
        ORDER BY count DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql_peak);
    $stmt->execute(['user_id' => $user_id]);
    $peak = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($peak) {
        $insights['query_1'] = "{$peak['day_name']} accounts for " . round($peak['percentage']) . "% of weekly transactions - optimize staffing for this day";
    } else {
        $insights['query_1'] = "Transaction patterns are consistent across all days of the week";
    }
    
    // Insight 2: Repeat customer rate
    $sql_repeat = "
        SELECT 
            COUNT(DISTINCT customer_name) as total,
            SUM(CASE WHEN visit_count > 1 THEN 1 ELSE 0 END) as repeat_count
        FROM (
            SELECT customer_name, COUNT(*) as visit_count
            FROM transaction_logs
            WHERE user_id = :user_id AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY customer_name
        ) as visits
    ";
    $stmt = $pdo->prepare($sql_repeat);
    $stmt->execute(['user_id' => $user_id]);
    $repeat = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($repeat && $repeat['total'] > 0) {
        $rate = round(($repeat['repeat_count'] / $repeat['total']) * 100);
        $insights['query_2'] = "{$rate}% of customers are repeat visitors - implement loyalty program to increase retention";
    } else {
        $insights['query_2'] = "Focus on converting first-time visitors into repeat customers";
    }
    
    // Insight 3: Top product contribution
    $sql_top = "
        SELECT 
            type_of_drink,
            SUM(total_amount) as revenue,
            (SUM(total_amount) / (SELECT SUM(total_amount) FROM transaction_logs WHERE user_id = :user_id AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY))) * 100 as percentage
        FROM transaction_logs
        WHERE user_id = :user_id
          AND date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY type_of_drink
        ORDER BY revenue DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($sql_top);
    $stmt->execute(['user_id' => $user_id]);
    $top = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($top) {
        $insights['query_3'] = "{$top['type_of_drink']} generates " . round($top['percentage']) . "% of revenue - create bundle offers to boost average order value";
    } else {
        $insights['query_3'] = "Diversify product offerings to maximize revenue potential";
    }
    
    return $insights;
}
?>