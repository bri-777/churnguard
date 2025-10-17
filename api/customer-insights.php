<?php
/**
 * Customer Insights API
 * Complete backend for customer analytics dashboard
 */

// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u393812660_churnguard');
define('DB_USER', 'u393812660_churnguard');
define('DB_PASS', '102202Brian_');

class CustomerInsightsAPI {
    private $conn;
    private $response = [];
    
    public function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch(PDOException $e) {
            $this->sendError('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Main method to handle API requests
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? 'metrics';
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        // Validate dates
        $startDate = $this->validateDate($startDate) ? $startDate : date('Y-m-d', strtotime('-30 days'));
        $endDate = $this->validateDate($endDate) ? $endDate : date('Y-m-d');
        
        try {
            switch($action) {
                case 'metrics':
                    $this->response = $this->getKeyMetrics($startDate, $endDate);
                    break;
                    
                case 'loyalty':
                    $this->response = $this->getLoyaltyData($startDate, $endDate);
                    break;
                    
                case 'behavior':
                    $this->response = $this->getBehaviorData($startDate, $endDate);
                    break;
                    
                case 'segmentation':
                    $this->response = $this->getSegmentationData($startDate, $endDate);
                    break;
                    
                case 'engagement':
                    $this->response = $this->getEngagementData($startDate, $endDate);
                    break;
                    
                case 'performance':
                    $this->response = $this->getPerformanceData($startDate, $endDate);
                    break;
                    
                case 'traffic':
                    $this->response = $this->getTrafficData($startDate, $endDate);
                    break;
                    
                case 'predictions':
                    $this->response = $this->getPredictiveData($startDate, $endDate);
                    break;
                    
                case 'customers':
                    $this->response = $this->getCustomersList($startDate, $endDate);
                    break;
                    
                case 'customer_detail':
                    $customerId = $_GET['customer_id'] ?? null;
                    $this->response = $this->getCustomerDetail($customerId);
                    break;
                    
                case 'export':
                    $this->exportData($startDate, $endDate);
                    break;
                    
                default:
                    $this->sendError('Invalid action specified');
            }
            
            $this->sendResponse();
            
        } catch(Exception $e) {
            $this->sendError('Error processing request: ' . $e->getMessage());
        }
    }
    
    /**
     * Get key metrics overview
     */
    private function getKeyMetrics($startDate, $endDate) {
        $metrics = [];
        
        // Total customers
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as total_customers,
                COUNT(DISTINCT CASE 
                    WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                    THEN user_id END) as new_customers_month,
                COUNT(DISTINCT CASE 
                    WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                    THEN user_id END) as new_customers_week
            FROM users 
            WHERE isActive = 1 AND isVerified = 1
        ");
        $stmt->execute();
        $customerData = $stmt->fetch();
        
        // Transaction metrics from transaction_logs
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                COUNT(DISTINCT user_id) as active_customers,
                COALESCE(SUM(total_amount), 0) as total_revenue,
                COALESCE(AVG(total_amount), 0) as avg_order_value,
                COALESCE(AVG(quantity_of_drinks), 0) as avg_items_per_order,
                COUNT(DISTINCT DATE(date_visited)) as active_days
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $transactionData = $stmt->fetch();
        
        // Calculate retention rate
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(DISTINCT CASE 
                    WHEN visit_count > 1 THEN user_id 
                END) * 100.0 / NULLIF(COUNT(DISTINCT user_id), 0) as retention_rate
            FROM (
                SELECT user_id, COUNT(*) as visit_count
                FROM transaction_logs
                WHERE date_visited BETWEEN :start_date AND :end_date
                GROUP BY user_id
            ) as user_visits
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $retentionData = $stmt->fetch();
        
        // Previous period comparison for growth calculation
        $daysDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
        $prevStartDate = date('Y-m-d', strtotime($startDate) - ($daysDiff * 86400));
        $prevEndDate = date('Y-m-d', strtotime($startDate) - 86400);
        
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as prev_transactions,
                COUNT(DISTINCT user_id) as prev_customers,
                COALESCE(SUM(total_amount), 0) as prev_revenue
            FROM transaction_logs
            WHERE date_visited BETWEEN :prev_start AND :prev_end
        ");
        $stmt->execute([
            ':prev_start' => $prevStartDate,
            ':prev_end' => $prevEndDate
        ]);
        $prevData = $stmt->fetch();
        
        // Churn data from churn_data table
        $stmt = $this->conn->prepare("
            SELECT 
                AVG(risk_percentage) as avg_risk_score,
                COUNT(CASE WHEN risk_level = 'High' THEN 1 END) as high_risk_count,
                COUNT(CASE WHEN risk_level = 'Medium' THEN 1 END) as medium_risk_count,
                COUNT(CASE WHEN risk_level = 'Low' THEN 1 END) as low_risk_count
            FROM churn_data
            WHERE date BETWEEN :start_date AND :end_date
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $churnData = $stmt->fetch();
        
        // Calculate growth percentages
        $customerGrowth = $this->calculateGrowth($transactionData['active_customers'], $prevData['prev_customers']);
        $transactionGrowth = $this->calculateGrowth($transactionData['total_transactions'], $prevData['prev_transactions']);
        $revenueGrowth = $this->calculateGrowth($transactionData['total_revenue'], $prevData['prev_revenue']);
        
        return [
            'total_customers' => $customerData['total_customers'],
            'active_customers' => $transactionData['active_customers'],
            'new_customers_month' => $customerData['new_customers_month'],
            'new_customers_week' => $customerData['new_customers_week'],
            'total_transactions' => $transactionData['total_transactions'],
            'total_revenue' => round($transactionData['total_revenue'], 2),
            'avg_order_value' => round($transactionData['avg_order_value'], 2),
            'avg_items_per_order' => round($transactionData['avg_items_per_order'], 1),
            'retention_rate' => round($retentionData['retention_rate'] ?? 0, 1),
            'customer_growth' => $customerGrowth,
            'transaction_growth' => $transactionGrowth,
            'revenue_growth' => $revenueGrowth,
            'avg_risk_score' => round($churnData['avg_risk_score'] ?? 0, 2),
            'high_risk_customers' => $churnData['high_risk_count'] ?? 0,
            'medium_risk_customers' => $churnData['medium_risk_count'] ?? 0,
            'low_risk_customers' => $churnData['low_risk_count'] ?? 0,
            'active_days' => $transactionData['active_days'],
            'daily_avg_revenue' => round($transactionData['total_revenue'] / max(1, $transactionData['active_days']), 2)
        ];
    }
    
    /**
     * Get loyalty and retention data
     */
    private function getLoyaltyData($startDate, $endDate) {
        // Customer categorization by visit frequency
        $stmt = $this->conn->prepare("
            SELECT 
                tl.user_id,
                COALESCE(u.username, CONCAT('Customer ', tl.user_id)) as customer_name,
                COUNT(*) as visit_count,
                MAX(tl.date_visited) as last_visit,
                MIN(tl.date_visited) as first_visit,
                SUM(tl.total_amount) as total_spent,
                AVG(tl.total_amount) as avg_spent,
                DATEDIFF(CURDATE(), MAX(tl.date_visited)) as days_since_visit,
                CASE 
                    WHEN COUNT(*) >= 20 THEN 'VIP'
                    WHEN COUNT(*) >= 10 THEN 'Loyal'
                    WHEN COUNT(*) >= 5 THEN 'Regular'
                    WHEN COUNT(*) >= 2 THEN 'Occasional'
                    ELSE 'New'
                END as customer_type,
                CASE 
                    WHEN DATEDIFF(CURDATE(), MAX(tl.date_visited)) > 60 THEN 'Very High'
                    WHEN DATEDIFF(CURDATE(), MAX(tl.date_visited)) > 30 THEN 'High'
                    WHEN DATEDIFF(CURDATE(), MAX(tl.date_visited)) > 14 THEN 'Medium'
                    ELSE 'Low'
                END as risk_level,
                COALESCE(cd.risk_percentage, 0) as churn_risk_score
            FROM transaction_logs tl
            LEFT JOIN users u ON tl.user_id = u.user_id
            LEFT JOIN (
                SELECT user_id, MAX(risk_percentage) as risk_percentage
                FROM churn_data
                WHERE date BETWEEN :start_date2 AND :end_date2
                GROUP BY user_id
            ) cd ON tl.user_id = cd.user_id
            WHERE tl.date_visited BETWEEN :start_date AND :end_date
            GROUP BY tl.user_id
            ORDER BY risk_level DESC, days_since_visit DESC
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':start_date2' => $startDate,
            ':end_date2' => $endDate
        ]);
        $customers = $stmt->fetchAll();
        
        // Customer distribution
        $stmt = $this->conn->prepare("
            SELECT 
                customer_type,
                COUNT(*) as count,
                SUM(total_revenue) as revenue,
                AVG(total_revenue) as avg_revenue
            FROM (
                SELECT 
                    user_id,
                    COUNT(*) as visit_count,
                    SUM(total_amount) as total_revenue,
                    CASE 
                        WHEN COUNT(*) >= 20 THEN 'VIP'
                        WHEN COUNT(*) >= 10 THEN 'Loyal'
                        WHEN COUNT(*) >= 5 THEN 'Regular'
                        WHEN COUNT(*) >= 2 THEN 'Occasional'
                        ELSE 'New'
                    END as customer_type
                FROM transaction_logs
                WHERE date_visited BETWEEN :start_date AND :end_date
                GROUP BY user_id
            ) as customer_stats
            GROUP BY customer_type
            ORDER BY FIELD(customer_type, 'VIP', 'Loyal', 'Regular', 'Occasional', 'New')
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $distribution = $stmt->fetchAll();
        
        // Monthly retention trend
        $stmt = $this->conn->prepare("
            SELECT 
                DATE_FORMAT(date_visited, '%Y-%m') as month,
                COUNT(DISTINCT user_id) as total_customers,
                COUNT(DISTINCT CASE 
                    WHEN user_id IN (
                        SELECT DISTINCT user_id 
                        FROM transaction_logs 
                        WHERE date_visited < DATE_FORMAT(date_visited, '%Y-%m-01')
                    ) THEN user_id 
                END) as returning_customers
            FROM transaction_logs
            WHERE date_visited >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(date_visited, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute();
        $retentionTrend = $stmt->fetchAll();
        
        // Comeback customers (returned after 30+ days)
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT user_id) as comeback_customers
            FROM (
                SELECT 
                    user_id,
                    date_visited,
                    LAG(date_visited) OVER (PARTITION BY user_id ORDER BY date_visited) as prev_visit
                FROM transaction_logs
            ) as visits
            WHERE DATEDIFF(date_visited, prev_visit) > 30
                AND date_visited BETWEEN :start_date AND :end_date
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $comebackData = $stmt->fetch();
        
        return [
            'customers' => $customers,
            'distribution' => $distribution,
            'retention_trend' => $retentionTrend,
            'comeback_customers' => $comebackData['comeback_customers'] ?? 0,
            'total_customers' => count($customers),
            'at_risk_count' => count(array_filter($customers, function($c) {
                return in_array($c['risk_level'], ['High', 'Very High']);
            })),
            'loyal_count' => count(array_filter($customers, function($c) {
                return in_array($c['customer_type'], ['VIP', 'Loyal']);
            }))
        ];
    }
    
    /**
     * Get purchase behavior data
     */
    private function getBehaviorData($startDate, $endDate) {
        // Top products
        $stmt = $this->conn->prepare("
            SELECT 
                type_of_drink as product,
                COUNT(*) as order_count,
                SUM(quantity_of_drinks) as total_quantity,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_price,
                COUNT(DISTINCT user_id) as unique_buyers
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
                AND type_of_drink IS NOT NULL
            GROUP BY type_of_drink
            ORDER BY order_count DESC
            LIMIT 20
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $topProducts = $stmt->fetchAll();
        
        // Time analysis
        $stmt = $this->conn->prepare("
            SELECT 
                time_of_day,
                COUNT(*) as transaction_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_transaction,
                COUNT(DISTINCT user_id) as unique_customers
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
                AND time_of_day IS NOT NULL
            GROUP BY time_of_day
            ORDER BY FIELD(time_of_day, 'morning', 'swing', 'graveyard')
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $timeAnalysis = $stmt->fetchAll();
        
        // Day of week analysis
        $stmt = $this->conn->prepare("
            SELECT 
                day,
                COUNT(*) as transaction_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_transaction,
                COUNT(DISTINCT user_id) as unique_customers
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
                AND day IS NOT NULL
            GROUP BY day
            ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $dailyPattern = $stmt->fetchAll();
        
        // Purchase frequency pattern
        $stmt = $this->conn->prepare("
            SELECT 
                DATE(date_visited) as date,
                COUNT(*) as transactions,
                SUM(total_amount) as revenue,
                COUNT(DISTINCT user_id) as customers,
                AVG(total_amount) as avg_transaction
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
            GROUP BY DATE(date_visited)
            ORDER BY date
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $purchasePattern = $stmt->fetchAll();
        
        // Payment method analysis
        $stmt = $this->conn->prepare("
            SELECT 
                payment_method,
                COUNT(*) as transaction_count,
                SUM(total_amount) as total_amount,
                AVG(total_amount) as avg_amount
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
                AND payment_method IS NOT NULL
            GROUP BY payment_method
            ORDER BY transaction_count DESC
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $paymentMethods = $stmt->fetchAll();
        
        // Category performance from revenue_categories if exists
        $categoryData = [];
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    category_name,
                    SUM(revenue) as category_revenue
                FROM revenue_categories
                WHERE date BETWEEN :start_date AND :end_date
                GROUP BY category_name
                ORDER BY category_revenue DESC
            ");
            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);
            $categoryData = $stmt->fetchAll();
        } catch(Exception $e) {
            // Table might not exist
            $categoryData = [];
        }
        
        return [
            'top_products' => $topProducts,
            'time_analysis' => $timeAnalysis,
            'daily_pattern' => $dailyPattern,
            'purchase_pattern' => $purchasePattern,
            'payment_methods' => $paymentMethods,
            'categories' => $categoryData
        ];
    }
    
    /**
     * Get customer segmentation data
     */
    private function getSegmentationData($startDate, $endDate) {
        // Frequency-based segmentation
        $stmt = $this->conn->prepare("
            SELECT 
                segment,
                COUNT(*) as customer_count,
                SUM(total_spent) as total_revenue,
                AVG(total_spent) as avg_revenue,
                AVG(visit_count) as avg_visits,
                MIN(total_spent) as min_spent,
                MAX(total_spent) as max_spent
            FROM (
                SELECT 
                    user_id,
                    COUNT(*) as visit_count,
                    SUM(total_amount) as total_spent,
                    CASE 
                        WHEN COUNT(*) >= 30 THEN 'Frequent'
                        WHEN COUNT(*) >= 15 THEN 'Regular'
                        WHEN COUNT(*) >= 5 THEN 'Occasional'
                        ELSE 'Inactive'
                    END as segment
                FROM transaction_logs
                WHERE date_visited BETWEEN :start_date AND :end_date
                GROUP BY user_id
            ) as customer_segments
            GROUP BY segment
            ORDER BY FIELD(segment, 'Frequent', 'Regular', 'Occasional', 'Inactive')
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $segments = $stmt->fetchAll();
        
        // LTV distribution
        $stmt = $this->conn->prepare("
            SELECT 
                ltv_segment,
                COUNT(*) as customer_count,
                SUM(total_spent) as total_revenue,
                AVG(total_spent) as avg_ltv
            FROM (
                SELECT 
                    user_id,
                    SUM(total_amount) as total_spent,
                    CASE 
                        WHEN SUM(total_amount) >= 20000 THEN 'High Value'
                        WHEN SUM(total_amount) >= 10000 THEN 'Medium Value'
                        WHEN SUM(total_amount) >= 5000 THEN 'Low Value'
                        ELSE 'Minimal Value'
                    END as ltv_segment
                FROM transaction_logs
                WHERE date_visited BETWEEN :start_date AND :end_date
                GROUP BY user_id
            ) as ltv_data
            GROUP BY ltv_segment
            ORDER BY avg_ltv DESC
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $ltvDistribution = $stmt->fetchAll();
        
        // RFM Analysis (Recency, Frequency, Monetary)
        $stmt = $this->conn->prepare("
            SELECT 
                user_id,
                DATEDIFF(CURDATE(), MAX(date_visited)) as recency,
                COUNT(*) as frequency,
                SUM(total_amount) as monetary,
                CASE 
                    WHEN DATEDIFF(CURDATE(), MAX(date_visited)) <= 7 THEN 5
                    WHEN DATEDIFF(CURDATE(), MAX(date_visited)) <= 14 THEN 4
                    WHEN DATEDIFF(CURDATE(), MAX(date_visited)) <= 30 THEN 3
                    WHEN DATEDIFF(CURDATE(), MAX(date_visited)) <= 60 THEN 2
                    ELSE 1
                END as r_score,
                CASE 
                    WHEN COUNT(*) >= 20 THEN 5
                    WHEN COUNT(*) >= 15 THEN 4
                    WHEN COUNT(*) >= 10 THEN 3
                    WHEN COUNT(*) >= 5 THEN 2
                    ELSE 1
                END as f_score,
                CASE 
                    WHEN SUM(total_amount) >= 10000 THEN 5
                    WHEN SUM(total_amount) >= 7500 THEN 4
                    WHEN SUM(total_amount) >= 5000 THEN 3
                    WHEN SUM(total_amount) >= 2500 THEN 2
                    ELSE 1
                END as m_score
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
            GROUP BY user_id
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $rfmData = $stmt->fetchAll();
        
        // Calculate RFM segments
        $rfmSegments = [];
        foreach ($rfmData as $customer) {
            $rfmScore = $customer['r_score'] + $customer['f_score'] + $customer['m_score'];
            if ($rfmScore >= 13) {
                $segment = 'Champions';
            } elseif ($rfmScore >= 10) {
                $segment = 'Loyal Customers';
            } elseif ($rfmScore >= 7) {
                $segment = 'Potential Loyalists';
            } elseif ($rfmScore >= 5) {
                $segment = 'At Risk';
            } else {
                $segment = 'Lost';
            }
            
            if (!isset($rfmSegments[$segment])) {
                $rfmSegments[$segment] = ['count' => 0, 'revenue' => 0];
            }
            $rfmSegments[$segment]['count']++;
            $rfmSegments[$segment]['revenue'] += $customer['monetary'];
        }
        
        return [
            'segments' => $segments,
            'ltv_distribution' => $ltvDistribution,
            'rfm_segments' => $rfmSegments,
            'total_segments' => count($segments),
            'highest_value_segment' => $segments[0] ?? null
        ];
    }
    
    /**
     * Get engagement metrics
     */
    private function getEngagementData($startDate, $endDate) {
        // Visit frequency analysis
        $stmt = $this->conn->prepare("
            SELECT 
                AVG(days_between_visits) as avg_visit_gap,
                MIN(days_between_visits) as min_visit_gap,
                MAX(days_between_visits) as max_visit_gap,
                STDDEV(days_between_visits) as stddev_visit_gap,
                COUNT(*) as total_visit_pairs
            FROM (
                SELECT 
                    user_id,
                    date_visited,
                    LAG(date_visited) OVER (PARTITION BY user_id ORDER BY date_visited) as prev_visit,
                    DATEDIFF(date_visited, LAG(date_visited) OVER (PARTITION BY user_id ORDER BY date_visited)) as days_between_visits
                FROM transaction_logs
                WHERE date_visited BETWEEN :start_date AND :end_date
            ) as visit_gaps
            WHERE days_between_visits IS NOT NULL
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $visitFrequency = $stmt->fetch();
        
        // Engagement by day and time
        $stmt = $this->conn->prepare("
            SELECT 
                day,
                time_of_day,
                COUNT(*) as visit_count,
                COUNT(DISTINCT user_id) as unique_visitors,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_transaction
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
                AND day IS NOT NULL 
                AND time_of_day IS NOT NULL
            GROUP BY day, time_of_day
            ORDER BY visit_count DESC
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $engagementPattern = $stmt->fetchAll();
        
        // Most engaged customers
        $stmt = $this->conn->prepare("
            SELECT 
                tl.user_id,
                COALESCE(u.username, CONCAT('Customer ', tl.user_id)) as customer_name,
                COUNT(*) as visit_count,
                SUM(tl.total_amount) as total_spent,
                AVG(tl.total_amount) as avg_spent,
                MIN(tl.date_visited) as first_visit,
                MAX(tl.date_visited) as last_visit,
                DATEDIFF(MAX(tl.date_visited), MIN(tl.date_visited)) as customer_lifetime_days,
                COUNT(DISTINCT DATE(tl.date_visited)) as active_days,
                COUNT(DISTINCT tl.type_of_drink) as product_variety
            FROM transaction_logs tl
            LEFT JOIN users u ON tl.user_id = u.user_id
            WHERE tl.date_visited BETWEEN :start_date AND :end_date
            GROUP BY tl.user_id
            HAVING visit_count > 1
            ORDER BY visit_count DESC, total_spent DESC
            LIMIT 50
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $topCustomers = $stmt->fetchAll();
        
        // Engagement score calculation
        $stmt = $this->conn->prepare("
            SELECT 
                AVG(engagement_score) as avg_engagement_score,
                MAX(engagement_score) as max_engagement_score,
                MIN(engagement_score) as min_engagement_score,
                COUNT(CASE WHEN engagement_score >= 80 THEN 1 END) as highly_engaged,
                COUNT(CASE WHEN engagement_score >= 60 AND engagement_score < 80 THEN 1 END) as moderately_engaged,
                COUNT(CASE WHEN engagement_score < 60 THEN 1 END) as low_engaged
            FROM (
                SELECT 
                    user_id,
                    (
                        (COUNT(*) * 10) + -- Visit frequency weight
                        (SUM(total_amount) / 100) + -- Monetary weight
                        (COUNT(DISTINCT DATE(date_visited)) * 5) + -- Consistency weight
                        (CASE WHEN DATEDIFF(CURDATE(), MAX(date_visited)) <= 7 THEN 20 ELSE 0 END) -- Recency bonus
                    ) / 4 as engagement_score
                FROM transaction_logs
                WHERE date_visited BETWEEN :start_date AND :end_date
                GROUP BY user_id
            ) as engagement_scores
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $engagementScores = $stmt->fetch();
        
        return [
            'visit_frequency' => $visitFrequency,
            'engagement_pattern' => $engagementPattern,
            'top_customers' => $topCustomers,
            'engagement_scores' => $engagementScores,
            'avg_days_between_visits' => round($visitFrequency['avg_visit_gap'] ?? 0, 1),
            'total_engaged_customers' => ($engagementScores['highly_engaged'] ?? 0) + ($engagementScores['moderately_engaged'] ?? 0)
        ];
    }
    
    /**
     * Get business performance data
     */
    private function getPerformanceData($startDate, $endDate) {
        // Monthly performance
        $stmt = $this->conn->prepare("
            SELECT 
                DATE_FORMAT(date_visited, '%Y-%m') as month,
                COUNT(DISTINCT user_id) as customers,
                COUNT(*) as transactions,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_transaction,
                COUNT(DISTINCT DATE(date_visited)) as active_days
            FROM transaction_logs
            WHERE date_visited >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(date_visited, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        $stmt->execute();
        $monthlyPerformance = $stmt->fetchAll();
        
        // Shift performance from churn_data
        $stmt = $this->conn->prepare("
            SELECT 
                DATE_FORMAT(date, '%Y-%m') as month,
                SUM(morning_sales_volume) as morning_sales,
                SUM(swing_sales_volume) as swing_sales,
                SUM(graveyard_sales_volume) as graveyard_sales,
                SUM(sales_volume) as total_sales,
                SUM(morning_receipt_count) as morning_receipts,
                SUM(swing_receipt_count) as swing_receipts,
                SUM(graveyard_receipt_count) as graveyard_receipts,
                SUM(receipt_count) as total_receipts
            FROM churn_data
            WHERE date BETWEEN :start_date AND :end_date
            GROUP BY DATE_FORMAT(date, '%Y-%m')
            ORDER BY month DESC
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $shiftPerformance = $stmt->fetchAll();
        
        // Growth metrics
        $stmt = $this->conn->prepare("
            SELECT 
                DATE_FORMAT(date_visited, '%Y-%m') as month,
                COUNT(DISTINCT CASE 
                    WHEN is_new = 1 THEN user_id 
                END) as new_customers,
                COUNT(DISTINCT CASE 
                    WHEN is_new = 0 THEN user_id 
                END) as returning_customers,
                COUNT(DISTINCT user_id) as total_customers
            FROM (
                SELECT 
                    user_id,
                    date_visited,
                    CASE 
                        WHEN date_visited = MIN(date_visited) OVER (PARTITION BY user_id) 
                        THEN 1 ELSE 0 
                    END as is_new
                FROM transaction_logs
                WHERE date_visited >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            ) as customer_status
            GROUP BY DATE_FORMAT(date_visited, '%Y-%m')
            ORDER BY month DESC
            LIMIT 6
        ");
        $stmt->execute();
        $growthMetrics = $stmt->fetchAll();
        
        // Target achievement from targets table
        $targetData = [];
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    t.target_name,
                    t.target_type,
                    t.target_value,
                    t.status,
                    CASE t.target_type
                        WHEN 'sales' THEN (
                            SELECT SUM(total_amount) 
                            FROM transaction_logs 
                            WHERE date_visited BETWEEN t.start_date AND t.end_date
                        )
                        WHEN 'customers' THEN (
                            SELECT COUNT(DISTINCT user_id) 
                            FROM transaction_logs 
                            WHERE date_visited BETWEEN t.start_date AND t.end_date
                        )
                        WHEN 'transactions' THEN (
                            SELECT COUNT(*) 
                            FROM transaction_logs 
                            WHERE date_visited BETWEEN t.start_date AND t.end_date
                        )
                        ELSE 0
                    END as current_value,
                    t.start_date,
                    t.end_date
                FROM targets t
                WHERE t.status = 'active'
                    AND t.end_date >= CURDATE()
                ORDER BY t.created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $targetData = $stmt->fetchAll();
            
            // Calculate achievement percentage
            foreach ($targetData as &$target) {
                $target['achievement_percentage'] = 
                    $target['target_value'] > 0 
                    ? round(($target['current_value'] / $target['target_value']) * 100, 1)
                    : 0;
            }
        } catch(Exception $e) {
            // Targets table might not be used
        }
        
        // Daily average calculations
        $stmt = $this->conn->prepare("
            SELECT 
                AVG(daily_revenue) as avg_daily_revenue,
                AVG(daily_transactions) as avg_daily_transactions,
                AVG(daily_customers) as avg_daily_customers,
                MAX(daily_revenue) as peak_daily_revenue,
                MAX(daily_transactions) as peak_daily_transactions
            FROM (
                SELECT 
                    DATE(date_visited) as date,
                    SUM(total_amount) as daily_revenue,
                    COUNT(*) as daily_transactions,
                    COUNT(DISTINCT user_id) as daily_customers
                FROM transaction_logs
                WHERE date_visited BETWEEN :start_date AND :end_date
                GROUP BY DATE(date_visited)
            ) as daily_stats
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $dailyAverages = $stmt->fetch();
        
        return [
            'monthly_performance' => $monthlyPerformance,
            'shift_performance' => $shiftPerformance,
            'growth_metrics' => $growthMetrics,
            'targets' => $targetData,
            'daily_averages' => $dailyAverages
        ];
    }
    
    /**
     * Get traffic and conversion data
     */
    private function getTrafficData($startDate, $endDate) {
        // Hourly traffic from churn_data shifts
        $stmt = $this->conn->prepare("
            SELECT 
                'Morning' as period,
                SUM(morning_receipt_count) as receipts,
                SUM(morning_sales_volume) as sales,
                AVG(morning_sales_volume / NULLIF(morning_receipt_count, 0)) as avg_ticket
            FROM churn_data
            WHERE date BETWEEN :start_date AND :end_date
            UNION ALL
            SELECT 
                'Swing' as period,
                SUM(swing_receipt_count) as receipts,
                SUM(swing_sales_volume) as sales,
                AVG(swing_sales_volume / NULLIF(swing_receipt_count, 0)) as avg_ticket
            FROM churn_data
            WHERE date BETWEEN :start_date2 AND :end_date2
            UNION ALL
            SELECT 
                'Graveyard' as period,
                SUM(graveyard_receipt_count) as receipts,
                SUM(graveyard_sales_volume) as sales,
                AVG(graveyard_sales_volume / NULLIF(graveyard_receipt_count, 0)) as avg_ticket
            FROM churn_data
            WHERE date BETWEEN :start_date3 AND :end_date3
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':start_date2' => $startDate,
            ':end_date2' => $endDate,
            ':start_date3' => $startDate,
            ':end_date3' => $endDate
        ]);
        $hourlyTraffic = $stmt->fetchAll();
        
        // Daily traffic patterns
        $stmt = $this->conn->prepare("
            SELECT 
                day,
                COUNT(*) as visits,
                COUNT(DISTINCT user_id) as unique_visitors,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_transaction,
                MAX(total_amount) as max_transaction,
                MIN(total_amount) as min_transaction
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
                AND day IS NOT NULL
            GROUP BY day
            ORDER BY FIELD(day, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $dailyTraffic = $stmt->fetchAll();
        
        // Conversion metrics from churn_data
        $stmt = $this->conn->prepare("
            SELECT 
                date,
                customer_traffic,
                receipt_count,
                sales_volume,
                CASE 
                    WHEN customer_traffic > 0 
                    THEN (receipt_count * 100.0 / customer_traffic) 
                    ELSE 0 
                END as conversion_rate,
                transaction_drop_percentage,
                sales_drop_percentage,
                risk_level
            FROM churn_data
            WHERE date BETWEEN :start_date AND :end_date
                AND customer_traffic > 0
            ORDER BY date DESC
            LIMIT 30
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $conversionMetrics = $stmt->fetchAll();
        
        // Peak hours analysis
        $stmt = $this->conn->prepare("
            SELECT 
                time_of_day,
                day,
                COUNT(*) as transaction_count,
                SUM(total_amount) as revenue
            FROM transaction_logs
            WHERE date_visited BETWEEN :start_date AND :end_date
                AND time_of_day IS NOT NULL 
                AND day IS NOT NULL
            GROUP BY time_of_day, day
            ORDER BY transaction_count DESC
            LIMIT 21
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $peakHours = $stmt->fetchAll();
        
        // Traffic anomalies detection
        $stmt = $this->conn->prepare("
            SELECT 
                date,
                daily_traffic,
                avg_traffic,
                CASE 
                    WHEN daily_traffic < (avg_traffic * 0.7) THEN 'Low'
                    WHEN daily_traffic > (avg_traffic * 1.3) THEN 'High'
                    ELSE 'Normal'
                END as anomaly_type,
                ABS(daily_traffic - avg_traffic) / avg_traffic * 100 as variance_percentage
            FROM (
                SELECT 
                    DATE(date_visited) as date,
                    COUNT(DISTINCT user_id) as daily_traffic,
                    AVG(COUNT(DISTINCT user_id)) OVER (
                        ORDER BY DATE(date_visited) 
                        ROWS BETWEEN 6 PRECEDING AND CURRENT ROW
                    ) as avg_traffic
                FROM transaction_logs
                WHERE date_visited BETWEEN :start_date AND :end_date
                GROUP BY DATE(date_visited)
            ) as traffic_analysis
            WHERE daily_traffic < (avg_traffic * 0.7) 
                OR daily_traffic > (avg_traffic * 1.3)
            ORDER BY variance_percentage DESC
            LIMIT 10
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $anomalies = $stmt->fetchAll();
        
        return [
            'hourly_traffic' => $hourlyTraffic,
            'daily_traffic' => $dailyTraffic,
            'conversion_metrics' => $conversionMetrics,
            'peak_hours' => $peakHours,
            'anomalies' => $anomalies,
            'avg_conversion_rate' => round(
                array_sum(array_column($conversionMetrics, 'conversion_rate')) / 
                max(1, count($conversionMetrics)), 2
            )
        ];
    }
    
    /**
     * Get predictive analytics data
     */
    private function getPredictiveData($startDate, $endDate) {
        // Get churn predictions
        $stmt = $this->conn->prepare("
            SELECT 
                cp.user_id,
                cp.risk_score,
                cp.risk_level,
                cp.risk_percentage,
                cp.description,
                cp.factors,
                u.username as customer_name,
                (SELECT COUNT(*) FROM transaction_logs WHERE user_id = cp.user_id) as total_visits,
                (SELECT SUM(total_amount) FROM transaction_logs WHERE user_id = cp.user_id) as total_spent
            FROM churn_predictions cp
            LEFT JOIN users u ON cp.user_id = u.user_id
            WHERE cp.for_date BETWEEN :start_date AND :end_date
            ORDER BY cp.risk_percentage DESC
            LIMIT 50
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        $churnPredictions = $stmt->fetchAll();
        
        // Revenue prediction (simple moving average)
        $stmt = $this->conn->prepare("
            SELECT 
                AVG(daily_revenue) as avg_daily_revenue,
                STDDEV(daily_revenue) as stddev_revenue,
                MAX(daily_revenue) as max_daily_revenue,
                MIN(daily_revenue) as min_daily_revenue
            FROM (
                SELECT 
                    DATE(date_visited) as date,
                    SUM(total_amount) as daily_revenue
                FROM transaction_logs
                WHERE date_visited >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY DATE(date_visited)
            ) as daily_stats
        ");
        $stmt->execute();
        $revenueTrend = $stmt->fetch();
        
        // Calculate predicted revenue for next 30 days
        $predictedRevenue = [
            'next_day' => round($revenueTrend['avg_daily_revenue'] ?? 0, 2),
            'next_week' => round(($revenueTrend['avg_daily_revenue'] ?? 0) * 7, 2),
            'next_month' => round(($revenueTrend['avg_daily_revenue'] ?? 0) * 30, 2),
            'confidence' => $revenueTrend['stddev_revenue'] > 0 
                ? round(100 - (($revenueTrend['stddev_revenue'] / $revenueTrend['avg_daily_revenue']) * 100), 1)
                : 0
        ];
        
        // Trending products (growth rate)
        $stmt = $this->conn->prepare("
            SELECT 
                current.product,
                current.current_orders,
                COALESCE(previous.previous_orders, 0) as previous_orders,
                CASE 
                    WHEN COALESCE(previous.previous_orders, 0) > 0
                    THEN ((current.current_orders - previous.previous_orders) * 100.0 / previous.previous_orders)
                    ELSE 100
                END as growth_rate
            FROM (
                SELECT 
                    type_of_drink as product,
                    COUNT(*) as current_orders
                FROM transaction_logs
                WHERE date_visited >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    AND type_of_drink IS NOT NULL
                GROUP BY type_of_drink
            ) current
            LEFT JOIN (
                SELECT 
                    type_of_drink as product,
                    COUNT(*) as previous_orders
                FROM transaction_logs
                WHERE date_visited >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                    AND date_visited < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    AND type_of_drink IS NOT NULL
                GROUP BY type_of_drink
            ) previous ON current.product = previous.product
            ORDER BY growth_rate DESC
            LIMIT 10
        ");
        $stmt->execute();
        $trendingProducts = $stmt->fetchAll();
        
        // Customer lifetime value prediction
        $stmt = $this->conn->prepare("
            SELECT 
                AVG(ltv) as avg_ltv,
                MAX(ltv) as max_ltv,
                MIN(ltv) as min_ltv,
                COUNT(CASE WHEN ltv >= 10000 THEN 1 END) as high_ltv_count,
                COUNT(CASE WHEN ltv >= 5000 AND ltv < 10000 THEN 1 END) as medium_ltv_count,
                COUNT(CASE WHEN ltv < 5000 THEN 1 END) as low_ltv_count
            FROM (
                SELECT 
                    user_id,
                    SUM(total_amount) as ltv
                FROM transaction_logs
                GROUP BY user_id
            ) as ltv_calc
        ");
        $stmt->execute();
        $ltvPrediction = $stmt->fetch();
        
        // Seasonal patterns
        $stmt = $this->conn->prepare("
            SELECT 
                MONTH(date_visited) as month,
                AVG(total_amount) as avg_revenue,
                COUNT(*) as transaction_count
            FROM transaction_logs
            GROUP BY MONTH(date_visited)
            ORDER BY month
        ");
        $stmt->execute();
        $seasonalPatterns = $stmt->fetchAll();
        
        return [
            'churn_predictions' => $churnPredictions,
            'revenue_prediction' => $predictedRevenue,
            'trending_products' => $trendingProducts,
            'ltv_prediction' => $ltvPrediction,
            'seasonal_patterns' => $seasonalPatterns,
            'high_risk_count' => count(array_filter($churnPredictions, function($p) {
                return $p['risk_level'] === 'High';
            }))
        ];
    }
    
    /**
     * Get customers list for table
     */
    private function getCustomersList($startDate, $endDate) {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        $sortBy = $_GET['sort'] ?? 'user_id';
        $sortOrder = $_GET['order'] ?? 'ASC';
        
        // Validate sort parameters
        $allowedSorts = ['user_id', 'customer_name', 'visit_count', 'total_spent', 'last_visit', 'risk_level'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'user_id';
        }
        $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
        
        // Build query
        $whereClause = "WHERE tl.date_visited BETWEEN :start_date AND :end_date";
        $params = [
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ];
        
        if (!empty($search)) {
            $whereClause .= " AND (u.username LIKE :search OR u.email LIKE :search2 OR tl.user_id = :search_id)";
            $params[':search'] = '%' . $search . '%';
            $params[':search2'] = '%' . $search . '%';
            $params[':search_id'] = intval($search);
        }
        
        // Count total
        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT tl.user_id) as total
            FROM transaction_logs tl
            LEFT JOIN users u ON tl.user_id = u.user_id
            $whereClause
        ");
        $stmt->execute($params);
        $totalCount = $stmt->fetch()['total'];
        
        // Get paginated results
        $stmt = $this->conn->prepare("
            SELECT 
                tl.user_id,
                COALESCE(u.username, CONCAT('Customer ', tl.user_id)) as customer_name,
                u.email,
                u.phone,
                COUNT(*) as visit_count,
                MAX(tl.date_visited) as last_visit,
                MIN(tl.date_visited) as first_visit,
                SUM(tl.total_amount) as total_spent,
                AVG(tl.total_amount) as avg_spent,
                DATEDIFF(CURDATE(), MAX(tl.date_visited)) as days_since_visit,
                CASE 
                    WHEN DATEDIFF(CURDATE(), MAX(tl.date_visited)) > 30 THEN 'High'
                    WHEN DATEDIFF(CURDATE(), MAX(tl.date_visited)) > 14 THEN 'Medium'
                    ELSE 'Low'
                END as risk_level,
                CASE 
                    WHEN COUNT(*) >= 10 THEN 'Loyal'
                    WHEN COUNT(*) >= 5 THEN 'Regular'
                    WHEN COUNT(*) >= 2 THEN 'Occasional'
                    ELSE 'New'
                END as customer_type
            FROM transaction_logs tl
            LEFT JOIN users u ON tl.user_id = u.user_id
            $whereClause
            GROUP BY tl.user_id
            ORDER BY $sortBy $sortOrder
            LIMIT :limit OFFSET :offset
        ");
        
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt->execute($params);
        $customers = $stmt->fetchAll();
        
        return [
            'customers' => $customers,
            'pagination' => [
                'total' => $totalCount,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($totalCount / $limit),
                'has_next' => $page < ceil($totalCount / $limit),
                'has_prev' => $page > 1
            ]
        ];
    }
    
    /**
     * Get individual customer details
     */
    private function getCustomerDetail($customerId) {
        if (!$customerId) {
            return ['error' => 'Customer ID required'];
        }
        
        // Get customer info
        $stmt = $this->conn->prepare("
            SELECT 
                u.user_id,
                u.username,
                u.email,
                u.firstname,
                u.lastname,
                u.phone,
                u.address,
                u.created_at as member_since,
                u.isActive,
                u.isVerified
            FROM users u
            WHERE u.user_id = :user_id
        ");
        $stmt->execute([':user_id' => $customerId]);
        $customerInfo = $stmt->fetch();
        
        // Get transaction history
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_visits,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as avg_spent,
                MAX(total_amount) as max_spent,
                MIN(total_amount) as min_spent,
                MAX(date_visited) as last_visit,
                MIN(date_visited) as first_visit,
                COUNT(DISTINCT type_of_drink) as product_variety,
                COUNT(DISTINCT DATE(date_visited)) as active_days,
                GROUP_CONCAT(DISTINCT type_of_drink) as favorite_products
            FROM transaction_logs
            WHERE user_id = :user_id
        ");
        $stmt->execute([':user_id' => $customerId]);
        $transactionStats = $stmt->fetch();
        
        // Get recent transactions
        $stmt = $this->conn->prepare("
            SELECT 
                date_visited,
                type_of_drink,
                quantity_of_drinks,
                total_amount,
                payment_method,
                time_of_day,
                day
            FROM transaction_logs
            WHERE user_id = :user_id
            ORDER BY date_visited DESC
            LIMIT 20
        ");
        $stmt->execute([':user_id' => $customerId]);
        $recentTransactions = $stmt->fetchAll();
        
        // Get churn risk
        $stmt = $this->conn->prepare("
            SELECT 
                risk_score,
                risk_level,
                risk_percentage,
                description,
                for_date
            FROM churn_predictions
            WHERE user_id = :user_id
            ORDER BY for_date DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $customerId]);
        $churnRisk = $stmt->fetch();
        
        return [
            'customer_info' => $customerInfo,
            'stats' => $transactionStats,
            'recent_transactions' => $recentTransactions,
            'churn_risk' => $churnRisk,
            'ltv_score' => $this->calculateLTV($transactionStats)
        ];
    }
    
    /**
     * Export data to CSV
     */
    private function exportData($startDate, $endDate) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customer_insights_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'User ID',
            'Customer Name',
            'Email',
            'Phone',
            'Total Visits',
            'Total Spent',
            'Average Spent',
            'Last Visit',
            'Days Since Visit',
            'Customer Type',
            'Risk Level'
        ]);
        
        // Get all customer data
        $stmt = $this->conn->prepare("
            SELECT 
                tl.user_id,
                COALESCE(u.username, CONCAT('Customer ', tl.user_id)) as customer_name,
                u.email,
                u.phone,
                COUNT(*) as visit_count,
                SUM(tl.total_amount) as total_spent,
                AVG(tl.total_amount) as avg_spent,
                MAX(tl.date_visited) as last_visit,
                DATEDIFF(CURDATE(), MAX(tl.date_visited)) as days_since_visit,
                CASE 
                    WHEN COUNT(*) >= 10 THEN 'Loyal'
                    WHEN COUNT(*) >= 5 THEN 'Regular'
                    WHEN COUNT(*) >= 2 THEN 'Occasional'
                    ELSE 'New'
                END as customer_type,
                CASE 
                    WHEN DATEDIFF(CURDATE(), MAX(tl.date_visited)) > 30 THEN 'High'
                    WHEN DATEDIFF(CURDATE(), MAX(tl.date_visited)) > 14 THEN 'Medium'
                    ELSE 'Low'
                END as risk_level
            FROM transaction_logs tl
            LEFT JOIN users u ON tl.user_id = u.user_id
            WHERE tl.date_visited BETWEEN :start_date AND :end_date
            GROUP BY tl.user_id
            ORDER BY tl.user_id
        ");
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['user_id'],
                $row['customer_name'],
                $row['email'] ?? '',
                $row['phone'] ?? '',
                $row['visit_count'],
                number_format($row['total_spent'], 2),
                number_format($row['avg_spent'], 2),
                $row['last_visit'],
                $row['days_since_visit'],
                $row['customer_type'],
                $row['risk_level']
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Utility Functions
     */
    private function calculateGrowth($current, $previous) {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }
    
    private function calculateLTV($stats) {
        if (!$stats) return 0;
        
        // Simple LTV calculation based on historical data
        $totalSpent = $stats['total_spent'] ?? 0;
        $visits = $stats['total_visits'] ?? 1;
        
        // Estimate future value based on average behavior
        $projectedMonthlyValue = ($totalSpent / max(1, $visits)) * 4; // Assuming 4 visits per month for active customers
        $retentionMonths = 12; // Assume 12 month retention
        
        return round($projectedMonthlyValue * $retentionMonths, 2);
    }
    
    private function validateDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    private function sendResponse() {
        echo json_encode($this->response, JSON_PRETTY_PRINT);
        exit;
    }
    
    private function sendError($message) {
        http_response_code(400);
        echo json_encode(['error' => $message]);
        exit;
    }
}

// Initialize and handle request
try {
    $api = new CustomerInsightsAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'System error: ' . $e->getMessage()]);
}
?>