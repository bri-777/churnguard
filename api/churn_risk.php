<?php
// api/churn_risk.php - Threshold-Based Churn Prediction
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

/**
 * Get business thresholds (customizable per user)
 */
function get_business_thresholds(int $uid, PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT baseline_sales, baseline_traffic, baseline_receipts
        FROM business_thresholds 
        WHERE user_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$uid]);
    $custom = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($custom && $custom['baseline_sales'] > 0) {
        return [
            'baseline_sales' => (float)$custom['baseline_sales'],
            'baseline_traffic' => (int)$custom['baseline_traffic'],
            'baseline_receipts' => (int)$custom['baseline_receipts']
        ];
    }
    
    // Default: Convenience store baselines
    return [
        'baseline_sales' => 40000.0,
        'baseline_traffic' => 450,
        'baseline_receipts' => 120,
    ];
}

/**
 * Map risk score to 3-level system
 */
function risk_level_from_score(float $score): string {
    if ($score >= 0.55) return 'High';
    if ($score >= 0.30) return 'Medium';
    return 'Low';
}

/**
 * Calculate threshold-based risk
 */
function calculate_threshold_risk(array $data, array $thresholds): float {
    $riskScore = 0.0;
    
    $currentSales = (float)$data['sales'];
    $currentTraffic = (int)$data['ct'];
    $currentReceipts = (int)$data['rc'];
    
    $baselineSales = $thresholds['baseline_sales'];
    $baselineTraffic = $thresholds['baseline_traffic'];
    $baselineReceipts = $thresholds['baseline_receipts'];
    
    // Sales risk (40% weight)
    $salesPct = $baselineSales > 0 ? $currentSales / $baselineSales : 0;
    if ($salesPct >= 1.0) {
        $riskScore += 0.0;
    } elseif ($salesPct >= 0.8) {
        $riskScore += 0.05;
    } elseif ($salesPct >= 0.6) {
        $riskScore += 0.15;
    } elseif ($salesPct >= 0.4) {
        $riskScore += 0.30;
    } elseif ($salesPct >= 0.2) {
        $riskScore += 0.50;
    } else {
        $riskScore += 0.70;
    }
    
    // Traffic risk (35% weight)
    $trafficPct = $baselineTraffic > 0 ? $currentTraffic / $baselineTraffic : 0;
    if ($trafficPct >= 1.0) {
        $riskScore += 0.0;
    } elseif ($trafficPct >= 0.8) {
        $riskScore += 0.04;
    } elseif ($trafficPct >= 0.6) {
        $riskScore += 0.12;
    } elseif ($trafficPct >= 0.4) {
        $riskScore += 0.25;
    } elseif ($trafficPct >= 0.2) {
        $riskScore += 0.40;
    } else {
        $riskScore += 0.60;
    }
    
    // Receipts risk (25% weight)
    $receiptsPct = $baselineReceipts > 0 ? $currentReceipts / $baselineReceipts : 0;
    if ($receiptsPct >= 1.0) {
        $riskScore += 0.0;
    } elseif ($receiptsPct >= 0.8) {
        $riskScore += 0.03;
    } elseif ($receiptsPct >= 0.6) {
        $riskScore += 0.08;
    } elseif ($receiptsPct >= 0.4) {
        $riskScore += 0.20;
    } elseif ($receiptsPct >= 0.2) {
        $riskScore += 0.35;
    } else {
        $riskScore += 0.50;
    }
    
    return min(1.0, max(0.0, $riskScore));
}

/**
 * Generate straightforward threshold-based factors
 */
function generate_threshold_factors(array $data, array $thresholds, array $rollups): array {
    $factors = [];
    
    $currentSales = (float)$data['sales'];
    $currentTraffic = (int)$data['ct'];
    $currentReceipts = (int)$data['rc'];
    
    $baselineSales = $thresholds['baseline_sales'];
    $baselineTraffic = $thresholds['baseline_traffic'];
    $baselineReceipts = $thresholds['baseline_receipts'];
    
    // 1. Sales analysis (most important)
    $salesPct = $baselineSales > 0 ? ($currentSales / $baselineSales) * 100 : 0;
    if ($salesPct >= 100) {
        $factors[] = "Sales at baseline: ₱" . number_format($currentSales, 0);
    } elseif ($salesPct >= 80) {
        $factors[] = "Sales stable: ₱" . number_format($currentSales, 0) . " (" . round($salesPct, 0) . "% of ₱" . number_format($baselineSales, 0) . " baseline)";
    } elseif ($salesPct >= 60) {
        $factors[] = "Sales below baseline: ₱" . number_format($currentSales, 0) . " (" . round($salesPct, 0) . "%)";
    } elseif ($salesPct >= 40) {
        $factors[] = "Sales declining: ₱" . number_format($currentSales, 0) . " (only " . round($salesPct, 0) . "% of baseline)";
    } elseif ($salesPct > 0) {
        $factors[] = "Critical sales: ₱" . number_format($currentSales, 0) . " (" . round($salesPct, 0) . "% of baseline)";
    } else {
        $factors[] = "No sales recorded today";
    }
    
    // 2. Traffic analysis
    $trafficPct = $baselineTraffic > 0 ? ($currentTraffic / $baselineTraffic) * 100 : 0;
    if ($trafficPct >= 100) {
        $factors[] = "Traffic stable: " . $currentTraffic . " customers";
    } elseif ($trafficPct >= 80) {
        $factors[] = "Traffic normal: " . $currentTraffic . " customers (" . round($trafficPct, 0) . "%)";
    } elseif ($trafficPct >= 60) {
        $factors[] = "Traffic below average: " . $currentTraffic . " customers (" . round($trafficPct, 0) . "%)";
    } elseif ($trafficPct >= 40) {
        $factors[] = "Low traffic: " . $currentTraffic . " customers (only " . round($trafficPct, 0) . "%)";
    } elseif ($trafficPct > 0) {
        $factors[] = "Very low traffic: " . $currentTraffic . " customers (" . round($trafficPct, 0) . "%)";
    } else {
        $factors[] = "No customer traffic recorded";
    }
    
    // 3. Transaction analysis
    $receiptsPct = $baselineReceipts > 0 ? ($currentReceipts / $baselineReceipts) * 100 : 0;
    if ($receiptsPct >= 100) {
        $factors[] = "Transactions on target: " . $currentReceipts . " receipts";
    } elseif ($receiptsPct >= 80) {
        $factors[] = "Transaction volume stable: " . $currentReceipts . " receipts";
    } elseif ($receiptsPct >= 60) {
        $factors[] = "Transactions below target: " . $currentReceipts . " receipts (" . round($receiptsPct, 0) . "%)";
    } elseif ($receiptsPct >= 40) {
        $factors[] = "Low transactions: " . $currentReceipts . " receipts (" . round($receiptsPct, 0) . "%)";
    } elseif ($receiptsPct > 0) {
        $factors[] = "Very low transactions: " . $currentReceipts . " receipts (" . round($receiptsPct, 0) . "%)";
    }
    
    // 4. Conversion rate (if applicable)
    if ($currentTraffic > 0) {
        $conversionRate = ($currentReceipts / $currentTraffic) * 100;
        if ($conversionRate < 20) {
            $factors[] = "Poor conversion: " . round($conversionRate, 1) . "% of visitors buy";
        } elseif ($conversionRate >= 60) {
            $factors[] = "Good conversion: " . round($conversionRate, 1) . "%";
        }
    }
    
    // 5. Average ticket
    if ($currentReceipts > 0) {
        $avgTicket = $currentSales / $currentReceipts;
        if ($avgTicket < 50) {
            $factors[] = "Low ticket: ₱" . round($avgTicket, 0) . " per transaction";
        } elseif ($avgTicket > 200) {
            $factors[] = "High-value sales: ₱" . round($avgTicket, 0) . " average";
        }
    }
    
    // 6. Historical trend (if available)
    if (isset($rollups['avgSales']) && $rollups['avgSales'] > 0 && $rollups['days'] >= 7) {
        $trendPct = (($currentSales - $rollups['avgSales']) / $rollups['avgSales']) * 100;
        if ($trendPct < -20) {
            $factors[] = "Declining trend: " . round(abs($trendPct), 0) . "% below recent average";
        } elseif ($trendPct > 20) {
            $factors[] = "Improving: +" . round($trendPct, 0) . "% above recent average";
        }
    }
    
    // Handle zero data case
    if ($currentSales == 0 && $currentReceipts == 0 && $currentTraffic == 0) {
        return ["No business activity recorded", "Add transaction data for accurate assessment"];
    }
    
    return array_slice($factors, 0, 5); // Limit to top 5 factors
}

/**
 * Generate straightforward description
 */
function generate_threshold_description(string $level, array $data, array $thresholds): string {
    $currentSales = (float)$data['sales'];
    $baselineSales = $thresholds['baseline_sales'];
    $salesPct = $baselineSales > 0 ? ($currentSales / $baselineSales) * 100 : 0;
    
    switch ($level) {
        case 'High':
            if ($salesPct < 40) {
                return "High churn risk: Sales at " . round($salesPct, 0) . "% of baseline (₱" . number_format($baselineSales, 0) . "). Immediate action required.";
            }
            return "High churn risk: Key metrics significantly below baseline. Implement retention strategies now.";
            
        case 'Medium':
            if ($salesPct >= 60 && $salesPct < 80) {
                return "Moderate risk: Sales at " . round($salesPct, 0) . "% of baseline. Monitor closely and address performance gaps.";
            }
            return "Moderate churn risk: Performance below target levels. Take preventive action to avoid escalation.";
            
        default: // Low
            if ($salesPct >= 100) {
                return "Low risk: Business at or above baseline. All key metrics stable. Continue current strategies.";
            } elseif ($salesPct >= 80) {
                return "Low risk: Performance near baseline levels. Minor variations within acceptable range.";
            }
            return "Low churn risk: Metrics within acceptable ranges. Standard monitoring recommended.";
    }
}

/**
 * Compute rolling averages
 */
function compute_rollups(PDO $pdo, int $uid, string $refDate): array {
    $stmt = $pdo->prepare("
        SELECT receipt_count rc, sales_volume sales, customer_traffic ct
        FROM churn_data
        WHERE user_id = :uid AND date < :d
        ORDER BY date DESC
        LIMIT 14
    ");
    $stmt->execute([':uid'=>$uid, ':d'=>$refDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $n = 0; 
    $sumRc = 0; 
    $sumSales = 0; 
    $sumCt = 0;
    
    foreach ($rows as $r) {
        $sumRc += (int)($r['rc'] ?? 0);
        $sumSales += (float)($r['sales'] ?? 0);
        $sumCt += (int)($r['ct'] ?? 0);
        $n++;
    }
    
    return [
        'avgRc' => $n ? $sumRc / $n : 0.0,
        'avgSales' => $n ? $sumSales / $n : 0.0,
        'avgCt' => $n ? $sumCt / $n : 0.0,
        'days' => $n
    ];
}

/**
 * Get current Manila date
 */
function get_manila_date(): string {
    $tz = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $tz);
    return $now->format('Y-m-d');
}

/**
 * Simple ML-based pattern detection (30% weight in hybrid)
 */
function calculate_ml_risk(array $data, array $rollups): float {
    $score = 0.0;
    
    $rc = (int)$data['rc'];
    $sales = (float)$data['sales'];
    $ct = (int)$data['ct'];
    
    // Pattern 1: Steep decline from historical average
    if ($rollups['days'] >= 7) {
        if ($rollups['avgSales'] > 0) {
            $salesDecline = (($rollups['avgSales'] - $sales) / $rollups['avgSales']) * 100;
            if ($salesDecline > 30) $score += 0.30;
            elseif ($salesDecline > 15) $score += 0.15;
        }
        
        if ($rollups['avgRc'] > 0) {
            $rcDecline = (($rollups['avgRc'] - $rc) / $rollups['avgRc']) * 100;
            if ($rcDecline > 30) $score += 0.25;
            elseif ($rcDecline > 15) $score += 0.12;
        }
    }
    
    // Pattern 2: Zero conversion with traffic
    if ($ct > 0 && $rc == 0) {
        $score += 0.35;
    }
    
    // Pattern 3: Very low activity
    if ($rc < 5 && $sales < 500) {
        $score += 0.20;
    }
    
    // Pattern 4: Poor conversion rate
    if ($ct > 0) {
        $conversionRate = ($rc / $ct) * 100;
        if ($conversionRate < 20) $score += 0.15;
    }
    
    return min(1.0, $score);
}

// ============================================================================
// ENDPOINTS
// ============================================================================

$action = $_GET['action'] ?? 'latest';

if ($action === 'latest') {
    try {
        $q = $pdo->prepare("
            SELECT id, user_id, risk_score, risk_level, level, description, factors, 
                   risk_percentage, for_date, created_at, model_confidence, analysis_quality
            FROM churn_predictions
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $q->execute([$uid]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            j_ok(['has'=>false]);
        } else {
            $factors = [];
            if (!empty($row['factors'])) {
                $decoded = json_decode($row['factors'], true);
                if (is_array($decoded)) $factors = $decoded;
            }
            
            j_ok([
                'has' => true,
                'id' => (int)$row['id'],
                'for_date' => $row['for_date'],
                'risk_percentage' => (float)$row['risk_percentage'],
                'risk_score' => (float)$row['risk_score'],
                'risk_level' => (string)($row['risk_level'] ?: $row['level']),
                'level' => (string)($row['risk_level'] ?: $row['level']),
                'description' => (string)($row['description'] ?? ''),
                'factors' => $factors,
                'model_confidence' => (float)($row['model_confidence'] ?? 0.9),
                'analysis_quality' => (string)($row['analysis_quality'] ?? 'high')
            ]);
        }
    } catch (Throwable $e) {
        j_err('Load prediction failed', 500, ['detail'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'run') {
    try {
        $manilaDate = get_manila_date();
        
        // Get business thresholds
        $thresholds = get_business_thresholds($uid, $pdo);
        
        // Get latest churn data
        $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? ORDER BY date DESC LIMIT 1");
        $q->execute([$uid]);
        $cd = $q->fetch(PDO::FETCH_ASSOC);
        
        if (!$cd) {
            // Create default entry
            $defaultInsert = $pdo->prepare("
                INSERT INTO churn_data 
                (user_id, date, receipt_count, sales_volume, customer_traffic,
                 morning_receipt_count, swing_receipt_count, graveyard_receipt_count,
                 morning_sales_volume, swing_sales_volume, graveyard_sales_volume,
                 previous_day_receipt_count, previous_day_sales_volume,
                 weekly_average_receipts, weekly_average_sales,
                 transaction_drop_percentage, sales_drop_percentage, created_at)
                VALUES 
                (?, ?, 0, 0.00, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, NOW())
            ");
            $defaultInsert->execute([$uid, $manilaDate]);
            
            $q->execute([$uid]);
            $cd = $q->fetch(PDO::FETCH_ASSOC);
        }
        
        $forDate = $cd['date'];
        
        // Extract current data
        $currentData = [
            'rc' => max(0, (int)($cd['receipt_count'] ?? 0)),
            'sales' => max(0.0, (float)($cd['sales_volume'] ?? 0)),
            'ct' => max(0, (int)($cd['customer_traffic'] ?? 0))
        ];
        
        // Get historical data
        $rollups = compute_rollups($pdo, $uid, $forDate);
        
        // HYBRID RISK CALCULATION
        
        // 1. Threshold-based risk (70% weight)
        $thresholdRisk = calculate_threshold_risk($currentData, $thresholds);
        
        // 2. ML pattern detection (30% weight)
        $mlRisk = calculate_ml_risk($currentData, $rollups);
        
        // 3. Combine scores
        $finalRisk = ($thresholdRisk * 0.70) + ($mlRisk * 0.30);
        
        // 4. Map to risk level
        $level = risk_level_from_score($finalRisk);
        $riskPct = round($finalRisk * 100, 2);
        
        // 5. Generate factors and description
        $factors = generate_threshold_factors($currentData, $thresholds, $rollups);
        $description = generate_threshold_description($level, $currentData, $thresholds);
        
        // 6. Determine confidence and quality
        $modelConfidence = 0.90; // High confidence with threshold-based approach
        $analysisQuality = $rollups['days'] >= 7 ? 'high' : ($rollups['days'] >= 3 ? 'medium' : 'low');
        
        if ($currentData['rc'] == 0 && $currentData['sales'] == 0 && $currentData['ct'] == 0) {
            $modelConfidence = 0.10;
            $analysisQuality = 'low';
            $description = "New business profile. Add transaction data for accurate assessment.";
        }
        
        // 7. Save to database
        $deletePrev = $pdo->prepare("DELETE FROM churn_predictions WHERE user_id = ? AND for_date = ?");
        $deletePrev->execute([$uid, $forDate]);
        
        // Create predictions table if not exists (with new columns)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS churn_predictions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                date DATE NOT NULL,
                risk_score DECIMAL(5,4) NOT NULL,
                risk_level VARCHAR(20) NOT NULL,
                level VARCHAR(20),
                factors TEXT,
                description TEXT,
                risk_percentage DECIMAL(5,2),
                for_date DATE,
                model_confidence DECIMAL(3,2) DEFAULT 0.90,
                analysis_quality VARCHAR(20) DEFAULT 'high',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_date (user_id, date),
                INDEX idx_user_fordate (user_id, for_date)
            )
        ");
        
        $insert = $pdo->prepare("
            INSERT INTO churn_predictions
            (user_id, date, risk_score, risk_level, factors, description, 
             level, risk_percentage, for_date, model_confidence, analysis_quality, created_at)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insert->execute([
            $uid,
            $manilaDate,
            round($finalRisk, 4),
            $level,
            json_encode($factors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $description,
            $level,
            $riskPct,
            $forDate,
            $modelConfidence,
            $analysisQuality
        ]);
        
        j_ok([
            'saved' => true,
            'has' => true,
            'for_date' => $forDate,
            'risk_percentage' => $riskPct,
            'risk_score' => round($finalRisk, 4),
            'risk_level' => $level,
            'level' => $level,
            'description' => $description,
            'factors' => $factors,
            'is_new_user' => ($currentData['rc'] == 0 && $currentData['sales'] == 0 && $currentData['ct'] == 0),
            'data_available' => ($currentData['rc'] > 0 || $currentData['sales'] > 0 || $currentData['ct'] > 0),
            'model_confidence' => $modelConfidence,
            'analysis_quality' => $analysisQuality,
            'thresholds_used' => $thresholds,
            'current_vs_baseline' => [
                'sales' => round(($currentData['sales'] / $thresholds['baseline_sales']) * 100, 1) . '%',
                'traffic' => round(($currentData['ct'] / $thresholds['baseline_traffic']) * 100, 1) . '%',
                'receipts' => round(($currentData['rc'] / $thresholds['baseline_receipts']) * 100, 1) . '%'
            ]
        ]);
    } catch (Throwable $e) {
        j_err('Prediction run failed', 500, ['detail'=>$e->getMessage()]);
    }
    exit;
}

if ($action === 'set_thresholds') {
    try {
        $raw = file_get_contents('php://input') ?: '';
        $in = json_decode($raw, true);
        if (!is_array($in)) $in = $_POST;
        
        $baselineSales = max(0, (float)($in['baseline_sales'] ?? 40000));
        $baselineTraffic = max(0, (int)($in['baseline_traffic'] ?? 450));
        $baselineReceipts = max(0, (int)($in['baseline_receipts'] ?? 120));
        
        // Create table if doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS business_thresholds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                baseline_sales DECIMAL(12,2) DEFAULT 40000.00,
                baseline_traffic INT DEFAULT 450,
                baseline_receipts INT DEFAULT 120,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user (user_id)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO business_thresholds (user_id, baseline_sales, baseline_traffic, baseline_receipts)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                baseline_sales = VALUES(baseline_sales),
                baseline_traffic = VALUES(baseline_traffic),
                baseline_receipts = VALUES(baseline_receipts),
                updated_at = NOW()
        ");
        $stmt->execute([$uid, $baselineSales, $baselineTraffic, $baselineReceipts]);
        
        j_ok([
            'saved' => true,
            'thresholds' => [
                'baseline_sales' => $baselineSales,
                'baseline_traffic' => $baselineTraffic,
                'baseline_receipts' => $baselineReceipts
            ]
        ]);
    } catch (Throwable $e) {
        j_err('Failed to set thresholds', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_thresholds') {
    try {
        $thresholds = get_business_thresholds($uid, $pdo);
        j_ok(['thresholds' => $thresholds]);
    } catch (Throwable $e) {
        j_err('Failed to get thresholds', 500, ['detail' => $e->getMessage()]);
    }
    exit;
}

j_err('Unknown action', 400);
?>