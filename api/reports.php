<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

class ChurnReportGenerator {
    private PDO $pdo;
    private int $userId;
    
    public function __construct(PDO $pdo, int $userId) {
        $this->pdo = $pdo;
        $this->userId = $userId;
    }
    
    public function getExecutiveSummary(int $days = 30): array {
        // Get current metrics
        $currentMetrics = $this->getCurrentMetrics();
        $historicalData = $this->getHistoricalData($days);
        $predictions = $this->getRecentPredictions($days);
        
        // Calculate retention and churn rates
        $retentionRate = $this->calculateRetentionRate($historicalData);
        $churnRate = 100 - $retentionRate;
        
        // Revenue metrics
        $revenueMetrics = $this->calculateRevenueMetrics($historicalData);
        
        // Risk distribution
        $riskDistribution = $this->getRiskDistribution($predictions);
        
        // Trend analysis
        $trends = $this->analyzeTrends($historicalData);
        
        return [
            'period_days' => $days,
            'current_status' => $currentMetrics,
            'retention_metrics' => [
                'retention_rate' => $retentionRate,
                'churn_rate' => $churnRate,
                'at_risk_days' => $this->countAtRiskDays($predictions),
                'stable_days' => $this->countStableDays($predictions)
            ],
            'revenue_metrics' => $revenueMetrics,
            'risk_distribution' => $riskDistribution,
            'trends' => $trends,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getCurrentMetrics(): array {
        $stmt = $this->pdo->prepare("
            SELECT 
                cp.risk_percentage,
                cp.risk_level,
                cp.description,
                cd.receipt_count,
                cd.sales_volume,
                cd.customer_traffic,
                cd.date
            FROM churn_predictions cp
            LEFT JOIN churn_data cd ON cd.user_id = cp.user_id 
                AND cd.date = cp.for_date
            WHERE cp.user_id = ?
            ORDER BY cp.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    private function getHistoricalData(int $days): array {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM churn_data
            WHERE user_id = ?
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY date ASC
        ");
        $stmt->execute([$this->userId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    private function getRecentPredictions(int $days): array {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM churn_predictions
            WHERE user_id = ?
                AND for_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            ORDER BY for_date ASC
        ");
        $stmt->execute([$this->userId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    private function calculateRetentionRate(array $data): float {
        if (empty($data)) return 0.0;
        
        $totalDays = count($data);
        $activeDays = 0;
        
        foreach ($data as $day) {
            if ($day['receipt_count'] > 0 || $day['sales_volume'] > 0) {
                $activeDays++;
            }
        }
        
        return $totalDays > 0 ? round(($activeDays / $totalDays) * 100, 2) : 0.0;
    }
    
    private function calculateRevenueMetrics(array $data): array {
        $totalRevenue = 0;
        $avgDailyRevenue = 0;
        $peakRevenue = 0;
        $revenueTrend = 0;
        
        if (!empty($data)) {
            foreach ($data as $day) {
                $dayRevenue = (float)($day['sales_volume'] ?? 0);
                $totalRevenue += $dayRevenue;
                if ($dayRevenue > $peakRevenue) {
                    $peakRevenue = $dayRevenue;
                }
            }
            
            $avgDailyRevenue = count($data) > 0 ? $totalRevenue / count($data) : 0;
            
            // Calculate trend (compare first half vs second half)
            if (count($data) >= 14) {
                $midpoint = intval(count($data) / 2);
                $firstHalf = array_slice($data, 0, $midpoint);
                $secondHalf = array_slice($data, $midpoint);
                
                $firstHalfRevenue = array_sum(array_column($firstHalf, 'sales_volume'));
                $secondHalfRevenue = array_sum(array_column($secondHalf, 'sales_volume'));
                
                if ($firstHalfRevenue > 0) {
                    $revenueTrend = (($secondHalfRevenue - $firstHalfRevenue) / $firstHalfRevenue) * 100;
                }
            }
        }
        
        // Calculate potential revenue loss from churn
        $potentialLoss = $this->calculatePotentialRevenueLoss($data);
        $savedRevenue = $this->calculateSavedRevenue($data);
        
        return [
            'total_revenue' => round($totalRevenue, 2),
            'avg_daily_revenue' => round($avgDailyRevenue, 2),
            'peak_revenue' => round($peakRevenue, 2),
            'revenue_trend_pct' => round($revenueTrend, 2),
            'potential_loss' => round($potentialLoss, 2),
            'saved_revenue' => round($savedRevenue, 2),
            'customer_lifetime_value' => round($avgDailyRevenue * 365, 2)
        ];
    }
    
    private function calculatePotentialRevenueLoss(array $data): float {
        $avgRevenue = 0;
        $highRiskDays = 0;
        
        if (!empty($data)) {
            $revenues = array_column($data, 'sales_volume');
            $avgRevenue = count($revenues) > 0 ? array_sum($revenues) / count($revenues) : 0;
            
            foreach ($data as $day) {
                $tdp = (float)($day['transaction_drop_percentage'] ?? 0);
                if ($tdp > 25) {
                    $highRiskDays++;
                }
            }
        }
        
        return $avgRevenue * $highRiskDays * 0.3; // Assuming 30% revenue loss on high-risk days
    }
    
    private function calculateSavedRevenue(array $data): float {
        $saved = 0;
        
        foreach ($data as $day) {
            $sales = (float)($day['sales_volume'] ?? 0);
            $tdp = (float)($day['transaction_drop_percentage'] ?? 0);
            
            // If recovery from high drop
            if ($tdp < 10 && $sales > 0) {
                $saved += $sales * 0.1; // 10% of recovered sales
            }
        }
        
        return $saved;
    }
    
    private function getRiskDistribution(array $predictions): array {
        $distribution = [
            'low' => 0,
            'medium' => 0,
            'high' => 0
        ];
        
        foreach ($predictions as $pred) {
            $level = strtolower($pred['risk_level'] ?? 'low');
            if (isset($distribution[$level])) {
                $distribution[$level]++;
            }
        }
        
        $total = array_sum($distribution);
        
        return [
            'counts' => $distribution,
            'percentages' => [
                'low' => $total > 0 ? round(($distribution['low'] / $total) * 100, 2) : 0,
                'medium' => $total > 0 ? round(($distribution['medium'] / $total) * 100, 2) : 0,
                'high' => $total > 0 ? round(($distribution['high'] / $total) * 100, 2) : 0
            ],
            'total_predictions' => $total
        ];
    }
    
    private function analyzeTrends(array $data): array {
        $trends = [
            'receipts' => [],
            'sales' => [],
            'traffic' => [],
            'conversion' => []
        ];
        
        foreach ($data as $day) {
            $date = $day['date'];
            $trends['receipts'][] = [
                'date' => $date,
                'value' => (int)($day['receipt_count'] ?? 0)
            ];
            $trends['sales'][] = [
                'date' => $date,
                'value' => round((float)($day['sales_volume'] ?? 0), 2)
            ];
            $trends['traffic'][] = [
                'date' => $date,
                'value' => (int)($day['customer_traffic'] ?? 0)
            ];
            
            $traffic = (int)($day['customer_traffic'] ?? 0);
            $receipts = (int)($day['receipt_count'] ?? 0);
            $conversion = $traffic > 0 ? round(($receipts / $traffic) * 100, 2) : 0;
            
            $trends['conversion'][] = [
                'date' => $date,
                'value' => $conversion
            ];
        }
        
        return $trends;
    }
    
    private function countAtRiskDays(array $predictions): int {
        $count = 0;
        foreach ($predictions as $pred) {
            if (($pred['risk_percentage'] ?? 0) >= 65) {
                $count++;
            }
        }
        return $count;
    }
    
    private function countStableDays(array $predictions): int {
        $count = 0;
        foreach ($predictions as $pred) {
            if (($pred['risk_percentage'] ?? 0) < 25) {
                $count++;
            }
        }
        return $count;
    }
    
    public function getDetailedAnalysis(int $days = 30): array {
        $data = $this->getHistoricalData($days);
        
        // Shift performance analysis
        $shiftAnalysis = $this->analyzeShiftPerformance($data);
        
        // Customer behavior patterns
        $behaviorPatterns = $this->analyzeCustomerBehavior($data);
        
        // Risk factors analysis
        $riskFactors = $this->analyzeRiskFactors($data);
        
        // Predictive insights
        $insights = $this->generatePredictiveInsights($data);
        
        return [
            'shift_performance' => $shiftAnalysis,
            'customer_behavior' => $behaviorPatterns,
            'risk_factors' => $riskFactors,
            'predictive_insights' => $insights,
            'analysis_period' => $days
        ];
    }
    
    private function analyzeShiftPerformance(array $data): array {
        $morningTotal = 0;
        $swingTotal = 0;
        $graveyardTotal = 0;
        $morningSales = 0;
        $swingSales = 0;
        $graveyardSales = 0;
        
        foreach ($data as $day) {
            $morningTotal += (int)($day['morning_receipt_count'] ?? 0);
            $swingTotal += (int)($day['swing_receipt_count'] ?? 0);
            $graveyardTotal += (int)($day['graveyard_receipt_count'] ?? 0);
            $morningSales += (float)($day['morning_sales_volume'] ?? 0);
            $swingSales += (float)($day['swing_sales_volume'] ?? 0);
            $graveyardSales += (float)($day['graveyard_sales_volume'] ?? 0);
        }
        
        $totalReceipts = $morningTotal + $swingTotal + $graveyardTotal;
        $totalSales = $morningSales + $swingSales + $graveyardSales;
        
        return [
            'morning' => [
                'receipts' => $morningTotal,
                'sales' => round($morningSales, 2),
                'receipts_pct' => $totalReceipts > 0 ? round(($morningTotal / $totalReceipts) * 100, 2) : 0,
                'sales_pct' => $totalSales > 0 ? round(($morningSales / $totalSales) * 100, 2) : 0
            ],
            'swing' => [
                'receipts' => $swingTotal,
                'sales' => round($swingSales, 2),
                'receipts_pct' => $totalReceipts > 0 ? round(($swingTotal / $totalReceipts) * 100, 2) : 0,
                'sales_pct' => $totalSales > 0 ? round(($swingSales / $totalSales) * 100, 2) : 0
            ],
            'graveyard' => [
                'receipts' => $graveyardTotal,
                'sales' => round($graveyardSales, 2),
                'receipts_pct' => $totalReceipts > 0 ? round(($graveyardTotal / $totalReceipts) * 100, 2) : 0,
                'sales_pct' => $totalSales > 0 ? round(($graveyardSales / $totalSales) * 100, 2) : 0
            ],
            'best_performing_shift' => $this->determineBestShift($morningTotal, $swingTotal, $graveyardTotal)
        ];
    }
    
    private function determineBestShift(int $morning, int $swing, int $graveyard): string {
        $max = max($morning, $swing, $graveyard);
        if ($max === $morning) return 'Morning (6AM-2PM)';
        if ($max === $swing) return 'Swing (2PM-10PM)';
        return 'Graveyard (10PM-6AM)';
    }
    
    private function analyzeCustomerBehavior(array $data): array {
        $totalTraffic = 0;
        $totalReceipts = 0;
        $totalSales = 0;
        $daysWithData = 0;
        
        foreach ($data as $day) {
            $traffic = (int)($day['customer_traffic'] ?? 0);
            $receipts = (int)($day['receipt_count'] ?? 0);
            $sales = (float)($day['sales_volume'] ?? 0);
            
            if ($traffic > 0 || $receipts > 0) {
                $daysWithData++;
                $totalTraffic += $traffic;
                $totalReceipts += $receipts;
                $totalSales += $sales;
            }
        }
        
        $avgTicket = $totalReceipts > 0 ? $totalSales / $totalReceipts : 0;
        $conversionRate = $totalTraffic > 0 ? ($totalReceipts / $totalTraffic) * 100 : 0;
        $avgDailyTraffic = $daysWithData > 0 ? $totalTraffic / $daysWithData : 0;
        
        return [
            'avg_transaction_value' => round($avgTicket, 2),
            'conversion_rate' => round($conversionRate, 2),
            'avg_daily_traffic' => round($avgDailyTraffic, 1),
            'total_customers_served' => $totalReceipts,
            'loyalty_score' => $this->calculateLoyaltyScore($conversionRate, $avgTicket),
            'customer_segments' => $this->segmentCustomers($avgTicket)
        ];
    }
    
    private function calculateLoyaltyScore(float $conversion, float $avgTicket): float {
        $score = 0;
        
        // Conversion component (0-50 points)
        $score += min(50, $conversion * 0.5);
        
        // Transaction value component (0-50 points)
        if ($avgTicket >= 200) $score += 50;
        elseif ($avgTicket >= 100) $score += 35;
        elseif ($avgTicket >= 50) $score += 20;
        else $score += 10;
        
        return round($score, 1);
    }
    
    private function segmentCustomers(float $avgTicket): array {
        if ($avgTicket >= 200) {
            return [
                'primary' => 'Premium',
                'description' => 'High-value customers with premium spending habits'
            ];
        } elseif ($avgTicket >= 100) {
            return [
                'primary' => 'Regular',
                'description' => 'Consistent customers with moderate spending'
            ];
        } elseif ($avgTicket >= 50) {
            return [
                'primary' => 'Casual',
                'description' => 'Occasional customers with basic needs'
            ];
        } else {
            return [
                'primary' => 'Budget',
                'description' => 'Price-conscious customers with minimal spending'
            ];
        }
    }
    
    private function analyzeRiskFactors(array $data): array {
        $factors = [
            'declining_transactions' => 0,
            'declining_sales' => 0,
            'low_traffic' => 0,
            'poor_conversion' => 0,
            'shift_imbalance' => 0
        ];
        
        foreach ($data as $day) {
            if (($day['transaction_drop_percentage'] ?? 0) > 15) {
                $factors['declining_transactions']++;
            }
            if (($day['sales_drop_percentage'] ?? 0) > 20) {
                $factors['declining_sales']++;
            }
            if (($day['customer_traffic'] ?? 0) < 10) {
                $factors['low_traffic']++;
            }
            
            $traffic = (int)($day['customer_traffic'] ?? 0);
            $receipts = (int)($day['receipt_count'] ?? 0);
            if ($traffic > 0 && ($receipts / $traffic) < 0.3) {
                $factors['poor_conversion']++;
            }
            
            // Check shift imbalance
            $shifts = [
                (int)($day['morning_receipt_count'] ?? 0),
                (int)($day['swing_receipt_count'] ?? 0),
                (int)($day['graveyard_receipt_count'] ?? 0)
            ];
            $maxShift = max($shifts);
            $totalShifts = array_sum($shifts);
            if ($totalShifts > 0 && ($maxShift / $totalShifts) > 0.6) {
                $factors['shift_imbalance']++;
            }
        }
        
        $totalDays = count($data);
        
        return [
            'critical_factors' => array_filter($factors, fn($v) => $v > ($totalDays * 0.3)),
            'warning_factors' => array_filter($factors, fn($v) => $v > ($totalDays * 0.15) && $v <= ($totalDays * 0.3)),
            'risk_score' => $this->calculateRiskScore($factors, $totalDays)
        ];
    }
    
    private function calculateRiskScore(array $factors, int $totalDays): float {
        if ($totalDays === 0) return 0;
        
        $score = 0;
        $weights = [
            'declining_transactions' => 0.3,
            'declining_sales' => 0.25,
            'low_traffic' => 0.2,
            'poor_conversion' => 0.15,
            'shift_imbalance' => 0.1
        ];
        
        foreach ($factors as $factor => $count) {
            $factorScore = ($count / $totalDays) * 100 * ($weights[$factor] ?? 0);
            $score += $factorScore;
        }
        
        return round(min(100, $score), 2);
    }
    
    private function generatePredictiveInsights(array $data): array {
        $insights = [];
        
        // Analyze recent trends
        $recentDays = array_slice($data, -7);
        $olderDays = array_slice($data, 0, -7);
        
        if (!empty($recentDays) && !empty($olderDays)) {
            $recentAvgSales = array_sum(array_column($recentDays, 'sales_volume')) / count($recentDays);
            $olderAvgSales = array_sum(array_column($olderDays, 'sales_volume')) / count($olderDays);
            
            if ($recentAvgSales < ($olderAvgSales * 0.8)) {
                $insights[] = [
                    'type' => 'warning',
                    'message' => 'Sales declining rapidly - 20% drop detected in recent week',
                    'recommendation' => 'Implement immediate retention strategies and customer engagement campaigns'
                ];
            } elseif ($recentAvgSales > ($olderAvgSales * 1.2)) {
                $insights[] = [
                    'type' => 'positive',
                    'message' => 'Strong growth momentum - 20% increase in recent week',
                    'recommendation' => 'Capitalize on positive trend with expansion strategies'
                ];
            }
            
            // Traffic analysis
            $recentAvgTraffic = array_sum(array_column($recentDays, 'customer_traffic')) / count($recentDays);
            $olderAvgTraffic = array_sum(array_column($olderDays, 'customer_traffic')) / count($olderDays);
            
            if ($recentAvgTraffic < ($olderAvgTraffic * 0.7)) {
                $insights[] = [
                    'type' => 'critical',
                    'message' => 'Customer traffic crisis - 30% decline in footfall',
                    'recommendation' => 'Launch promotional campaigns and review competitive positioning'
                ];
            }
        }
        
        // Shift optimization insight
        $morningTotal = array_sum(array_column($data, 'morning_receipt_count'));
        $swingTotal = array_sum(array_column($data, 'swing_receipt_count'));
        $graveyardTotal = array_sum(array_column($data, 'graveyard_receipt_count'));
        
        $total = $morningTotal + $swingTotal + $graveyardTotal;
        if ($total > 0) {
            if (($swingTotal / $total) < 0.35 && $total > 100) {
                $insights[] = [
                    'type' => 'optimization',
                    'message' => 'Peak hours underperforming - only ' . round(($swingTotal / $total) * 100, 1) . '% of transactions',
                    'recommendation' => 'Focus resources on 2PM-10PM shift to capture peak demand'
                ];
            }
        }
        
        // Add default insight if none generated
        if (empty($insights)) {
            $insights[] = [
                'type' => 'info',
                'message' => 'Business performance stable - maintain current strategies',
                'recommendation' => 'Continue monitoring metrics for early warning signs'
            ];
        }
        
        return $insights;
    }
    
    public function downloadReport(string $format = 'json', int $days = 30): array {
        $summary = $this->getExecutiveSummary($days);
        $analysis = $this->getDetailedAnalysis($days);
        
        $report = [
            'report_type' => 'Churn Analysis Report',
            'business_id' => $this->userId,
            'generated_at' => date('Y-m-d H:i:s'),
            'report_period' => $days . ' days',
            'executive_summary' => $summary,
            'detailed_analysis' => $analysis
        ];
        
        if ($format === 'csv') {
            // Flatten for CSV export
            return $this->flattenForCSV($report);
        }
        
        return $report;
    }
    
    private function flattenForCSV(array $data): array {
        $rows = [];
        
        // Header row
        $rows[] = ['Churn Analysis Report'];
        $rows[] = ['Generated', date('Y-m-d H:i:s')];
        $rows[] = [];
        
        // Retention metrics
        $rows[] = ['RETENTION METRICS'];
        $rows[] = ['Retention Rate', $data['executive_summary']['retention_metrics']['retention_rate'] . '%'];
        $rows[] = ['Churn Rate', $data['executive_summary']['retention_metrics']['churn_rate'] . '%'];
        $rows[] = ['At Risk Days', $data['executive_summary']['retention_metrics']['at_risk_days']];
        $rows[] = [];
        
        // Revenue metrics
        $rows[] = ['REVENUE METRICS'];
        $revenue = $data['executive_summary']['revenue_metrics'];
        $rows[] = ['Total Revenue', '₱' . number_format($revenue['total_revenue'], 2)];
        $rows[] = ['Average Daily Revenue', '₱' . number_format($revenue['avg_daily_revenue'], 2)];
        $rows[] = ['Customer Lifetime Value', '₱' . number_format($revenue['customer_lifetime_value'], 2)];
        $rows[] = [];
        
        // Customer behavior
        $rows[] = ['CUSTOMER BEHAVIOR'];
        $behavior = $data['detailed_analysis']['customer_behavior'];
        $rows[] = ['Average Transaction Value', '₱' . number_format($behavior['avg_transaction_value'], 2)];
        $rows[] = ['Conversion Rate', $behavior['conversion_rate'] . '%'];
        $rows[] = ['Loyalty Score', $behavior['loyalty_score']];
        
        return $rows;
    }
}

// Controller
$action = $_GET['action'] ?? 'summary';
$generator = new ChurnReportGenerator($pdo, $uid);

try {
    switch ($action) {
        case 'summary':
            $days = (int)($_GET['days'] ?? 30);
            $summary = $generator->getExecutiveSummary($days);
            j_ok($summary);
            break;
            
        case 'detailed':
            $days = (int)($_GET['days'] ?? 30);
            $analysis = $generator->getDetailedAnalysis($days);
            j_ok($analysis);
            break;
            
        case 'download':
            $format = $_GET['format'] ?? 'json';
            $days = (int)($_GET['days'] ?? 30);
            $report = $generator->downloadReport($format, $days);
            
            if ($format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="churn_report_' . date('Ymd') . '.csv"');
                
                $output = fopen('php://output', 'w');
                foreach ($report as $row) {
                    fputcsv($output, $row);
                }
                fclose($output);
                exit;
            } else {
                j_ok($report);
            }
            break;
            
        default:
            j_err('Invalid action', 400);
    }
} catch (Throwable $e) {
    j_err('Report generation failed', 500, ['detail' => $e->getMessage()]);
}