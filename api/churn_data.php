<?php
// api/churn_data.php - Advanced Pure PHP Churn Prediction Engine
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

$action = $_GET['action'] ?? 'save';

// ============================================================================
// ADVANCED FEATURE ENGINEERING & ANALYTICS ENGINE
// ============================================================================

class ChurnPredictor {
    private array $data;
    private array $features = [];
    
    public function __construct(array $data) {
        $this->data = $data;
        // Sort by date ascending for proper analysis
        usort($this->data, fn($a, $b) => strcmp($a['date'], $b['date']));
    }
    
    /**
     * Extract 30+ advanced features from time series data
     */
    public function extractFeatures(): array {
        if (empty($this->data)) return [];
        
        $receipts = array_column($this->data, 'receipt_count');
        $sales = array_column($this->data, 'sales_volume');
        $traffic = array_column($this->data, 'customer_traffic');
        
        $features = [];
        
        // 1. STATISTICAL FEATURES
        $features['avg_receipts'] = $this->mean($receipts);
        $features['avg_sales'] = $this->mean($sales);
        $features['avg_traffic'] = $this->mean($traffic);
        $features['std_receipts'] = $this->stdDev($receipts);
        $features['std_sales'] = $this->stdDev($sales);
        $features['std_traffic'] = $this->stdDev($traffic);
        $features['cv_receipts'] = $features['avg_receipts'] > 0 ? $features['std_receipts'] / $features['avg_receipts'] : 0;
        $features['cv_sales'] = $features['avg_sales'] > 0 ? $features['std_sales'] / $features['avg_sales'] : 0;
        
        // 2. MEDIAN & QUARTILES (more robust than mean)
        $features['median_receipts'] = $this->median($receipts);
        $features['q1_receipts'] = $this->percentile($receipts, 25);
        $features['q3_receipts'] = $this->percentile($receipts, 75);
        $features['iqr_receipts'] = $features['q3_receipts'] - $features['q1_receipts'];
        
        // 3. TREND ANALYSIS (multiple time windows)
        $features['trend_3d'] = $this->calculateTrend($receipts, 3);
        $features['trend_7d'] = $this->calculateTrend($receipts, 7);
        $features['trend_14d'] = $this->calculateTrend($receipts, 14);
        $features['trend_30d'] = $this->calculateTrend($receipts, 30);
        $features['sales_trend_7d'] = $this->calculateTrend($sales, 7);
        $features['traffic_trend_7d'] = $this->calculateTrend($traffic, 7);
        
        // 4. MOMENTUM & ACCELERATION
        $features['momentum_3d'] = $this->calculateMomentum($receipts, 3);
        $features['momentum_7d'] = $this->calculateMomentum($receipts, 7);
        $features['acceleration'] = $this->calculateAcceleration($receipts);
        
        // 5. VOLATILITY METRICS
        $features['max_drop_pct'] = $this->maxDrop($receipts);
        $features['max_spike_pct'] = $this->maxSpike($receipts);
        $features['dod_volatility'] = $this->dodVolatility($receipts);
        $features['range_ratio'] = $this->rangeRatio($receipts);
        
        // 6. DECLINE PATTERNS
        $features['consecutive_declines'] = $this->consecutiveDeclines($receipts);
        $features['decline_frequency'] = $this->declineFrequency($receipts);
        $features['severe_drops'] = $this->severeDrops($receipts, 15); // >15% drops
        
        // 7. RECOVERY METRICS
        $features['recovery_rate'] = $this->recoveryRate($receipts);
        $features['bounce_strength'] = $this->bounceStrength($receipts);
        $features['stability_score'] = $this->stabilityScore($receipts);
        
        // 8. WINDOWED AVERAGES
        $features['last_3d_avg'] = $this->windowAverage($receipts, 3);
        $features['last_7d_avg'] = $this->windowAverage($receipts, 7);
        $features['last_14d_avg'] = $this->windowAverage($receipts, 14);
        $features['first_7d_avg'] = $this->windowAverage($receipts, 7, true);
        
        // 9. PERFORMANCE RATIOS
        $features['recent_vs_overall'] = $features['avg_receipts'] > 0 ? 
            $features['last_7d_avg'] / $features['avg_receipts'] : 1;
        $features['recent_vs_early'] = $features['first_7d_avg'] > 0 ? 
            $features['last_7d_avg'] / $features['first_7d_avg'] : 1;
        
        // 10. CONVERSION METRICS
        $features['avg_conversion'] = $this->conversionRate($traffic, $receipts);
        $features['conversion_trend'] = $this->conversionTrend();
        $features['conversion_volatility'] = $this->conversionVolatility();
        
        // 11. TRANSACTION VALUE
        $features['avg_transaction_value'] = $this->avgTransactionValue($sales, $receipts);
        $features['atv_trend'] = $this->atvTrend();
        $features['atv_stability'] = $this->atvStability();
        
        // 12. SHIFT ANALYSIS
        $shiftMetrics = $this->shiftAnalysis();
        $features = array_merge($features, $shiftMetrics);
        
        // 13. PEAK ANALYSIS
        $features['days_since_peak'] = $this->daysSincePeak($receipts);
        $features['peak_to_current_ratio'] = $this->peakToCurrentRatio($receipts);
        $features['peak_frequency'] = $this->peakFrequency($receipts);
        
        // 14. SEASONALITY & PATTERNS
        $features['weekday_effect'] = $this->weekdayEffect();
        $features['weekend_strength'] = $this->weekendStrength();
        
        // 15. CONSISTENCY METRICS
        $features['consistency_score'] = $this->consistencyScore($receipts);
        $features['predictability'] = $this->predictability($receipts);
        
        $this->features = $features;
        return $features;
    }
    
    /**
     * Multi-model ensemble prediction
     */
    public function predict(): array {
        $features = $this->extractFeatures();
        if (empty($features)) {
            return [
                'churn_probability' => 0,
                'risk_score' => 0,
                'risk_level' => 'UNKNOWN',
                'confidence' => 0
            ];
        }
        
        // Run multiple prediction models
        $trendModel = $this->trendBasedPrediction($features);
        $volatilityModel = $this->volatilityBasedPrediction($features);
        $patternModel = $this->patternBasedPrediction($features);
        $performanceModel = $this->performanceBasedPrediction($features);
        $ensembleModel = $this->ensembleDecisionTree($features);
        
        // Weighted ensemble (optimized weights)
        $weights = [
            'trend' => 0.25,
            'volatility' => 0.20,
            'pattern' => 0.20,
            'performance' => 0.20,
            'ensemble' => 0.15
        ];
        
        $finalScore = (
            $trendModel['score'] * $weights['trend'] +
            $volatilityModel['score'] * $weights['volatility'] +
            $patternModel['score'] * $weights['pattern'] +
            $performanceModel['score'] * $weights['performance'] +
            $ensembleModel['score'] * $weights['ensemble']
        );
        
        // Calculate confidence based on data quality and agreement
        $confidence = $this->calculateConfidence([
            $trendModel, $volatilityModel, $patternModel, 
            $performanceModel, $ensembleModel
        ]);
        
        $churnProb = min(1.0, max(0.0, $finalScore / 100));
        
        // Collect all risk factors
        $riskFactors = array_merge(
            $trendModel['factors'],
            $volatilityModel['factors'],
            $patternModel['factors'],
            $performanceModel['factors']
        );
        
        return [
            'churn_probability' => round($churnProb, 4),
            'risk_score' => round($finalScore, 2),
            'risk_level' => $this->getRiskLevel($churnProb),
            'confidence' => round($confidence, 2),
            'model_scores' => [
                'trend' => round($trendModel['score'], 2),
                'volatility' => round($volatilityModel['score'], 2),
                'pattern' => round($patternModel['score'], 2),
                'performance' => round($performanceModel['score'], 2),
                'ensemble' => round($ensembleModel['score'], 2)
            ],
            'risk_factors' => array_unique($riskFactors),
            'model' => 'php_ensemble_v2'
        ];
    }
    
    // ========================================================================
    // PREDICTION MODELS
    // ========================================================================
    
    private function trendBasedPrediction(array $f): array {
        $score = 0;
        $factors = [];
        
        // Strong negative trends
        if ($f['trend_7d'] < -15) {
            $score += 35;
            $factors[] = 'Severe 7-day decline: ' . round($f['trend_7d'], 1) . '%';
        } elseif ($f['trend_7d'] < -10) {
            $score += 25;
            $factors[] = 'Strong 7-day decline: ' . round($f['trend_7d'], 1) . '%';
        } elseif ($f['trend_7d'] < -5) {
            $score += 15;
            $factors[] = 'Moderate decline trend';
        }
        
        // Accelerating decline
        if ($f['acceleration'] < -2) {
            $score += 20;
            $factors[] = 'Accelerating downward trend';
        } elseif ($f['acceleration'] < -1) {
            $score += 10;
            $factors[] = 'Worsening trend';
        }
        
        // Short-term vs long-term divergence
        if ($f['trend_3d'] < -10 && $f['trend_30d'] > -5) {
            $score += 15;
            $factors[] = 'Sudden recent deterioration';
        }
        
        // Multi-timeframe alignment
        if ($f['trend_3d'] < 0 && $f['trend_7d'] < 0 && $f['trend_14d'] < 0) {
            $score += 15;
            $factors[] = 'Consistently negative across all timeframes';
        }
        
        return ['score' => $score, 'factors' => $factors];
    }
    
    private function volatilityBasedPrediction(array $f): array {
        $score = 0;
        $factors = [];
        
        // High coefficient of variation
        if ($f['cv_receipts'] > 0.6) {
            $score += 25;
            $factors[] = 'Extremely high volatility (CV: ' . round($f['cv_receipts'], 2) . ')';
        } elseif ($f['cv_receipts'] > 0.4) {
            $score += 15;
            $factors[] = 'High business volatility';
        } elseif ($f['cv_receipts'] > 0.25) {
            $score += 8;
            $factors[] = 'Moderate volatility';
        }
        
        // Large drops
        if ($f['max_drop_pct'] > 40) {
            $score += 20;
            $factors[] = 'Severe single-day drop: ' . round($f['max_drop_pct'], 1) . '%';
        } elseif ($f['max_drop_pct'] > 25) {
            $score += 12;
            $factors[] = 'Significant drop detected';
        }
        
        // Frequent severe drops
        if ($f['severe_drops'] >= 3) {
            $score += 15;
            $factors[] = 'Multiple severe drops (' . $f['severe_drops'] . ' occurrences)';
        }
        
        // Day-over-day instability
        if ($f['dod_volatility'] > 25) {
            $score += 12;
            $factors[] = 'High day-to-day instability';
        }
        
        // Low stability
        if ($f['stability_score'] < 0.5) {
            $score += 10;
            $factors[] = 'Poor overall stability';
        }
        
        return ['score' => $score, 'factors' => $factors];
    }
    
    private function patternBasedPrediction(array $f): array {
        $score = 0;
        $factors = [];
        
        // Consecutive declines
        if ($f['consecutive_declines'] >= 7) {
            $score += 30;
            $factors[] = 'Critical: ' . $f['consecutive_declines'] . ' consecutive declining days';
        } elseif ($f['consecutive_declines'] >= 5) {
            $score += 20;
            $factors[] = 'Extended decline pattern';
        } elseif ($f['consecutive_declines'] >= 3) {
            $score += 10;
            $factors[] = 'Recent consecutive declines';
        }
        
        // High decline frequency
        if ($f['decline_frequency'] > 60) {
            $score += 15;
            $factors[] = 'Declining ' . round($f['decline_frequency'], 0) . '% of days';
        } elseif ($f['decline_frequency'] > 50) {
            $score += 8;
            $factors[] = 'More declining days than growing days';
        }
        
        // Poor recovery
        if ($f['recovery_rate'] < 25) {
            $score += 15;
            $factors[] = 'Very poor recovery rate: ' . round($f['recovery_rate'], 1) . '%';
        } elseif ($f['recovery_rate'] < 40) {
            $score += 8;
            $factors[] = 'Weak recovery capability';
        }
        
        // Weak bounce strength
        if ($f['bounce_strength'] < 0.5) {
            $score += 10;
            $factors[] = 'Weak recovery bounces';
        }
        
        // Low predictability
        if ($f['predictability'] < 0.6) {
            $score += 8;
            $factors[] = 'Erratic, unpredictable behavior';
        }
        
        return ['score' => $score, 'factors' => $factors];
    }
    
    private function performanceBasedPrediction(array $f): array {
        $score = 0;
        $factors = [];
        
        // Negative momentum
        if ($f['momentum_7d'] < -0.25) {
            $score += 25;
            $factors[] = 'Strong negative momentum';
        } elseif ($f['momentum_7d'] < -0.15) {
            $score += 15;
            $factors[] = 'Negative momentum';
        } elseif ($f['momentum_7d'] < -0.05) {
            $score += 8;
            $factors[] = 'Slight negative momentum';
        }
        
        // Recent underperformance
        if ($f['recent_vs_overall'] < 0.75) {
            $score += 20;
            $factors[] = 'Recent performance 25%+ below average';
        } elseif ($f['recent_vs_overall'] < 0.85) {
            $score += 12;
            $factors[] = 'Recent underperformance detected';
        }
        
        // Deterioration over time
        if ($f['recent_vs_early'] < 0.7) {
            $score += 15;
            $factors[] = 'Significant deterioration from initial levels';
        }
        
        // Low conversion rate
        if ($f['avg_conversion'] < 25) {
            $score += 12;
            $factors[] = 'Low conversion rate: ' . round($f['avg_conversion'], 1) . '%';
        } elseif ($f['avg_conversion'] < 35) {
            $score += 6;
            $factors[] = 'Below-average conversion';
        }
        
        // Declining conversion
        if ($f['conversion_trend'] < -10) {
            $score += 10;
            $factors[] = 'Conversion rate declining';
        }
        
        // Far from peak
        if ($f['peak_to_current_ratio'] < 0.6) {
            $score += 12;
            $factors[] = 'Performance 40%+ below peak';
        }
        
        return ['score' => $score, 'factors' => $factors];
    }
    
    private function ensembleDecisionTree(array $f): array {
        $score = 0;
        
        // Decision tree logic
        if ($f['trend_7d'] < -10) {
            $score += 20;
            if ($f['consecutive_declines'] >= 4) {
                $score += 15;
                if ($f['recovery_rate'] < 30) {
                    $score += 15;
                }
            }
        }
        
        if ($f['cv_receipts'] > 0.5 && $f['max_drop_pct'] > 30) {
            $score += 20;
        }
        
        if ($f['momentum_7d'] < -0.2 && $f['recent_vs_overall'] < 0.8) {
            $score += 20;
        }
        
        if ($f['decline_frequency'] > 55 && $f['bounce_strength'] < 0.6) {
            $score += 15;
        }
        
        if ($f['avg_conversion'] < 30 && $f['conversion_trend'] < -5) {
            $score += 10;
        }
        
        return ['score' => $score, 'factors' => []];
    }
    
    // ========================================================================
    // STATISTICAL FUNCTIONS
    // ========================================================================
    
    private function mean(array $values): float {
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }
    
    private function stdDev(array $values): float {
        $n = count($values);
        if ($n === 0) return 0;
        $mean = $this->mean($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        return sqrt($variance);
    }
    
    private function median(array $values): float {
        if (empty($values)) return 0;
        sort($values);
        $n = count($values);
        $mid = (int)floor($n / 2);
        return $n % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2 : $values[$mid];
    }
    
    private function percentile(array $values, float $p): float {
        if (empty($values)) return 0;
        sort($values);
        $index = ($p / 100) * (count($values) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;
        return $values[(int)$lower] * (1 - $weight) + $values[(int)$upper] * $weight;
    }
    
    private function calculateTrend(array $values, int $days): float {
        $n = count($values);
        if ($n < $days * 2) return 0;
        
        $recent = array_slice($values, -$days);
        $previous = array_slice($values, -$days * 2, $days);
        
        $recentAvg = $this->mean($recent);
        $previousAvg = $this->mean($previous);
        
        return $previousAvg > 0 ? (($recentAvg - $previousAvg) / $previousAvg) * 100 : 0;
    }
    
    private function calculateMomentum(array $values, int $days): float {
        $n = count($values);
        if ($n < $days) return 0;
        
        $recent = $this->mean(array_slice($values, -$days));
        $overall = $this->mean($values);
        
        return $overall > 0 ? ($recent / $overall) - 1 : 0;
    }
    
    private function calculateAcceleration(array $values): float {
        $n = count($values);
        if ($n < 9) return 0;
        
        $trend3 = $this->calculateTrend($values, 3);
        $trend7 = $this->calculateTrend($values, 7);
        
        return $trend3 - $trend7;
    }
    
    private function maxDrop(array $values): float {
        $maxDrop = 0;
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i - 1] > 0) {
                $drop = (($values[$i - 1] - $values[$i]) / $values[$i - 1]) * 100;
                $maxDrop = max($maxDrop, $drop);
            }
        }
        return $maxDrop;
    }
    
    private function maxSpike(array $values): float {
        $maxSpike = 0;
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i - 1] > 0) {
                $spike = (($values[$i] - $values[$i - 1]) / $values[$i - 1]) * 100;
                $maxSpike = max($maxSpike, $spike);
            }
        }
        return $maxSpike;
    }
    
    private function dodVolatility(array $values): float {
        if (count($values) < 2) return 0;
        $changes = [];
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i - 1] > 0) {
                $changes[] = abs(($values[$i] - $values[$i - 1]) / $values[$i - 1]);
            }
        }
        return !empty($changes) ? $this->mean($changes) * 100 : 0;
    }
    
    private function rangeRatio(array $values): float {
        if (empty($values)) return 0;
        $min = min($values);
        $max = max($values);
        return $min > 0 ? $max / $min : 0;
    }
    
    private function consecutiveDeclines(array $values): int {
        $maxConsecutive = 0;
        $current = 0;
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i] < $values[$i - 1]) {
                $current++;
                $maxConsecutive = max($maxConsecutive, $current);
            } else {
                $current = 0;
            }
        }
        return $maxConsecutive;
    }
    
    private function declineFrequency(array $values): float {
        if (count($values) < 2) return 0;
        $declines = 0;
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i] < $values[$i - 1]) $declines++;
        }
        return ($declines / (count($values) - 1)) * 100;
    }
    
    private function severeDrops(array $values, float $threshold): int {
        $count = 0;
        for ($i = 1; $i < count($values); $i++) {
            if ($values[$i - 1] > 0) {
                $drop = (($values[$i - 1] - $values[$i]) / $values[$i - 1]) * 100;
                if ($drop > $threshold) $count++;
            }
        }
        return $count;
    }
    
    private function recoveryRate(array $values): float {
        if (count($values) < 3) return 0;
        $recoveries = 0;
        $declines = 0;
        for ($i = 2; $i < count($values); $i++) {
            if ($values[$i - 1] < $values[$i - 2]) {
                $declines++;
                if ($values[$i] > $values[$i - 1]) $recoveries++;
            }
        }
        return $declines > 0 ? ($recoveries / $declines) * 100 : 0;
    }
    
    private function bounceStrength(array $values): float {
        if (count($values) < 3) return 0;
        $bounces = [];
        for ($i = 2; $i < count($values); $i++) {
            if ($values[$i - 1] < $values[$i - 2] && $values[$i] > $values[$i - 1]) {
                $drop = $values[$i - 2] - $values[$i - 1];
                $bounce = $values[$i] - $values[$i - 1];
                if ($drop > 0) $bounces[] = $bounce / $drop;
            }
        }
        return !empty($bounces) ? $this->mean($bounces) : 0;
    }
    
    private function stabilityScore(array $values): float {
        $cv = $this->stdDev($values) / $this->mean($values);
        return $cv > 0 ? 1 / (1 + $cv) : 1;
    }
    
    private function windowAverage(array $values, int $window, bool $fromStart = false): float {
        $n = count($values);
        if ($n < $window) return $this->mean($values);
        return $fromStart ? 
            $this->mean(array_slice($values, 0, $window)) : 
            $this->mean(array_slice($values, -$window));
    }
    
    private function conversionRate(array $traffic, array $receipts): float {
        $conversions = [];
        for ($i = 0; $i < count($traffic); $i++) {
            if ($traffic[$i] > 0) {
                $conversions[] = ($receipts[$i] / $traffic[$i]) * 100;
            }
        }
        return !empty($conversions) ? $this->mean($conversions) : 0;
    }
    
    private function conversionTrend(): float {
        $conversions = [];
        foreach ($this->data as $row) {
            $t = (int)($row['customer_traffic'] ?? 0);
            $r = (int)($row['receipt_count'] ?? 0);
            if ($t > 0) $conversions[] = ($r / $t) * 100;
        }
        return $this->calculateTrend($conversions, 7);
    }
    
    private function conversionVolatility(): float {
        $conversions = [];
        foreach ($this->data as $row) {
            $t = (int)($row['customer_traffic'] ?? 0);
            $r = (int)($row['receipt_count'] ?? 0);
            if ($t > 0) $conversions[] = ($r / $t) * 100;
        }
        return $this->stdDev($conversions);
    }
    
    private function avgTransactionValue(array $sales, array $receipts): float {
        $total_sales = array_sum($sales);
        $total_receipts = array_sum($receipts);
        return $total_receipts > 0 ? $total_sales / $total_receipts : 0;
    }
    
    private function atvTrend(): float {
        $atvs = [];
        foreach ($this->data as $row) {
            $s = (float)($row['sales_volume'] ?? 0);
            $r = (int)($row['receipt_count'] ?? 0);
            if ($r > 0) $atvs[] = $s / $r;
        }
        return $this->calculateTrend($atvs, 7);
    }
    
    private function atvStability(): float {
        $atvs = [];
        foreach ($this->data as $row) {
            $s = (float)($row['sales_volume'] ?? 0);
            $r = (int)($row['receipt_count'] ?? 0);
            if ($r > 0) $atvs[] = $s / $r;
        }
        return $this->stabilityScore($atvs);
    }
    
    private function shiftAnalysis(): array {
        $totalMorning = $totalSwing = $totalGraveyard = 0;
        foreach ($this->data as $row) {
            $totalMorning += (int)($row['morning_receipt_count'] ?? 0);
            $totalSwing += (int)($row['swing_receipt_count'] ?? 0);
            $totalGraveyard += (int)($row['graveyard_receipt_count'] ?? 0);
        }
        $total = $totalMorning + $totalSwing + $totalGraveyard;
        
        return [
            'morning_pct' => $total > 0 ? ($totalMorning / $total) * 100 : 0,
            'swing_pct' => $total > 0 ? ($totalSwing / $total) * 100 : 0,
            'graveyard_pct' => $total > 0 ? ($totalGraveyard / $total) * 100 : 0,
            'shift_balance' => $this->stdDev([$totalMorning, $totalSwing, $totalGraveyard])
        ];
    }
    
    private function daysSincePeak(array $values): int {
        if (empty($values)) return 0;
        $peak = max($values);
        $peakIndex = array_search($peak, array_reverse($values, true));
        return $peakIndex !== false ? $peakIndex : count($values);
    }
    
    private function peakToCurrentRatio(array $values): float {
        if (empty($values)) return 1;
        $peak = max($values);
        $current = end($values);
        return $peak > 0 ? $current / $peak : 1;
    }
    
    private function peakFrequency(array $values): float {
        if (count($values) < 3) return 0;
        $peaks = 0;
        for ($i = 1; $i < count($values) - 1; $i++) {
            if ($values[$i] > $values[$i - 1] && $values[$i] > $values[$i + 1]) {
                $peaks++;
            }
        }
        return ($peaks / count($values)) * 100;
    }
    
    private function weekdayEffect(): float {
        // Calculate if weekdays perform better (would need date parsing)
        // Simplified version
        return 0;
    }
    
    private function weekendStrength(): float {
        // Weekend vs weekday comparison (would need date parsing)
        return 0;
    }
    
    private function consistencyScore(array $values): float {
        $diffs = [];
        for ($i = 1; $i < count($values); $i++) {
            $diffs[] = abs($values[$i] - $values[$i - 1]);
        }
        $avgDiff = $this->mean($diffs);
        $avgValue = $this->mean($values);
        return $avgValue > 0 ? 1 - min(1, $avgDiff / $avgValue) : 0;
    }
    
    private function predictability(array $values): float {
        // Higher is more predictable
        $cv = $this->stdDev($values) / $this->mean($values);
        return $cv > 0 ? 1 / (1 + $cv) : 1;
    }
    
    private function calculateConfidence(array $models): float {
        // Calculate agreement between models
        $scores = array_column($models, 'score');
        $avgScore = $this->mean($scores);
        $stdScore = $this->stdDev($scores);
        
        // More agreement = higher confidence
        $agreement = $avgScore > 0 ? 1 - min(1, $stdScore / $avgScore) : 0.5;
        
        // Data quality factor
        $dataPoints = count($this->data);
        $dataQuality = min(1, $dataPoints / 30); // Full confidence at 30+ days
        
        return ($agreement * 0.6 + $dataQuality * 0.4) * 100;
    }
    
    private function getRiskLevel(float $probability): string {
        if ($probability >= 0.75) return 'CRITICAL';
        if ($probability >= 0.55) return 'HIGH';
        if ($probability >= 0.35) return 'MEDIUM';
        if ($probability >= 0.15) return 'LOW';
        return 'MINIMAL';
    }
    
    public function generateRecommendations(array $prediction): array {
        $recommendations = [];
        $features = $this->features;
        $riskLevel = $prediction['risk_level'];
        
        // Critical interventions
        if ($riskLevel === 'CRITICAL' || $riskLevel === 'HIGH') {
            $recommendations[] = [
                'priority' => 'CRITICAL',
                'category' => 'Immediate Action',
                'action' => 'Emergency business review required',
                'details' => 'Schedule immediate meeting with stakeholders. Review all metrics and implement rapid response plan.',
                'impact' => 'HIGH'
            ];
        }
        
        // Trend-based recommendations
        if ($features['trend_7d'] < -10) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Revenue Recovery',
                'action' => 'Launch promotional campaign',
                'details' => sprintf('Address %s%% decline with targeted promotions, loyalty rewards, or limited-time offers.', 
                    round(abs($features['trend_7d']), 1)),
                'impact' => 'HIGH'
            ];
        }
        
        // Conversion optimization
        if ($features['avg_conversion'] < 30) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Sales Optimization',
                'action' => 'Improve conversion rate',
                'details' => sprintf('Current conversion is only %s%%. Focus on staff training, customer engagement, and sales techniques.',
                    round($features['avg_conversion'], 1)),
                'impact' => 'MEDIUM'
            ];
        }
        
        // Volatility management
        if ($features['cv_receipts'] > 0.4) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Operations',
                'action' => 'Stabilize operations',
                'details' => 'High volatility detected. Implement consistent processes, standardize operations, and improve scheduling.',
                'impact' => 'MEDIUM'
            ];
        }
        
        // Consecutive decline pattern
        if ($features['consecutive_declines'] >= 5) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'category' => 'Trend Reversal',
                'action' => 'Break decline pattern',
                'details' => sprintf('%d consecutive declining days detected. Consider special events, flash sales, or marketing push.',
                    $features['consecutive_declines']),
                'impact' => 'HIGH'
            ];
        }
        
        // Recovery capability
        if ($features['recovery_rate'] < 35) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Resilience',
                'action' => 'Build recovery systems',
                'details' => 'Low recovery rate indicates weak bounce-back ability. Develop contingency plans and quick-response strategies.',
                'impact' => 'MEDIUM'
            ];
        }
        
        // Transaction value
        if ($features['avg_transaction_value'] > 0 && $features['atvStability'] < 0.6) {
            $recommendations[] = [
                'priority' => 'LOW',
                'category' => 'Revenue Management',
                'action' => 'Stabilize transaction values',
                'details' => 'Unstable average transaction value. Review pricing strategy and upselling techniques.',
                'impact' => 'LOW'
            ];
        }
        
        // Momentum recovery
        if ($features['momentum_7d'] < -0.15) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Growth',
                'action' => 'Rebuild positive momentum',
                'details' => 'Negative momentum detected. Focus on customer acquisition, retention initiatives, and community engagement.',
                'impact' => 'MEDIUM'
            ];
        }
        
        // Peak distance
        if ($features['peak_to_current_ratio'] < 0.7) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'category' => 'Performance',
                'action' => 'Return to peak performance',
                'details' => sprintf('Currently at %s%% of peak performance. Analyze what worked during peak period and replicate.',
                    round($features['peak_to_current_ratio'] * 100, 0)),
                'impact' => 'MEDIUM'
            ];
        }
        
        // If everything is good
        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'INFO',
                'category' => 'Maintenance',
                'action' => 'Continue current strategies',
                'details' => 'Business metrics are healthy. Maintain current operations while monitoring key indicators.',
                'impact' => 'LOW'
            ];
        }
        
        // Sort by priority
        $priorityOrder = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3, 'INFO' => 4];
        usort($recommendations, fn($a, $b) => $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']]);
        
        return $recommendations;
    }
}

// ============================================================================
// EXISTING SAVE ENDPOINT
// ============================================================================

if ($action === 'save') {
  try {
    $raw = file_get_contents('php://input') ?: '';
    $in  = json_decode($raw, true);
    if (!is_array($in)) $in = $_POST;

    $date = trim((string)($in['date'] ?? ''));
    if ($date === '') j_err('Missing date', 422);

    $dt = DateTime::createFromFormat('Y-m-d', $date) ?: DateTime::createFromFormat('m/d/Y', $date);
    if (!$dt) j_err('Invalid date format', 422);
    $date = $dt->format('Y-m-d');

    $N  = static function ($k, $def = 0) use ($in) { return is_numeric($in[$k] ?? null) ? (float)$in[$k] : (float)$def; };
    $Ni = static function ($k, $def = 0) use ($in) { return (int)round($N = is_numeric($in[$k] ?? null) ? (float)$in[$k] : (float)$def); };

    $rc   = $Ni('receipt_count');
    $sales= $N('sales_volume', 0.0);
    $ct   = $Ni('customer_traffic');
    $mrc  = $Ni('morning_receipt_count');
    $src  = $Ni('swing_receipt_count');
    $grc  = $Ni('graveyard_receipt_count');
    $msv  = $N('morning_sales_volume');
    $ssv  = $N('swing_sales_volume');
    $gsv  = $N('graveyard_sales_volume');
    $prc  = $Ni('previous_day_receipt_count');
    $psv  = $N('previous_day_sales_volume');
    $war  = $N('weekly_average_receipts');
    $was  = $N('weekly_average_sales');
    $tdp  = $N('transaction_drop_percentage');
    $sdp  = $N('sales_drop_percentage');

    $sql = "
      INSERT INTO churn_data
        (user_id, date, receipt_count, sales_volume, customer_traffic,
         morning_receipt_count, swing_receipt_count, graveyard_receipt_count,
         morning_sales_volume, swing_sales_volume, graveyard_sales_volume,
         previous_day_receipt_count, previous_day_sales_volume,
         weekly_average_receipts, weekly_average_sales,
         transaction_drop_percentage, sales_drop_percentage,
         created_at, updated_at)
      VALUES
        (:uid, :date, :rc, :sales, :ct,
         :mrc, :src, :grc,
         :msv, :ssv, :gsv,
         :prc, :psv,
         :war, :was,
         :tdp, :sdp,
         NOW(), NOW())
      ON DUPLICATE KEY UPDATE
         receipt_count = VALUES(receipt_count),
         sales_volume  = VALUES(sales_volume),
         customer_traffic = VALUES(customer_traffic),
         morning_receipt_count = VALUES(morning_receipt_count),
         swing_receipt_count   = VALUES(swing_receipt_count),
         graveyard_receipt_count = VALUES(graveyard_receipt_count),
         morning_sales_volume = VALUES(morning_sales_volume),
         swing_sales_volume   = VALUES(swing_sales_volume),
         graveyard_sales_volume = VALUES(graveyard_sales_volume),
         previous_day_receipt_count = VALUES(previous_day_receipt_count),
         previous_day_sales_volume  = VALUES(previous_day_sales_volume),
         weekly_average_receipts = VALUES(weekly_average_receipts),
         weekly_average_sales    = VALUES(weekly_average_sales),
         transaction_drop_percentage = VALUES(transaction_drop_percentage),
         sales_drop_percentage        = VALUES(sales_drop_percentage),
         updated_at = NOW()
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':uid'=>$uid, ':date'=>$date, ':rc'=>$rc, ':sales'=>$sales, ':ct'=>$ct,
      ':mrc'=>$mrc, ':src'=>$src, ':grc'=>$grc,
      ':msv'=>$msv, ':ssv'=>$ssv, ':gsv'=>$gsv,
      ':prc'=>$prc, ':psv'=>$psv,
      ':war'=>$war, ':was'=>$was,
      ':tdp'=>$tdp, ':sdp'=>$sdp
    ]);

    j_ok(['saved'=>true, 'date'=>$date]);
  } catch (Throwable $e) {
    j_err('Save failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

// ============================================================================
// ADVANCED PREDICTION ENDPOINTS
// ============================================================================

if ($action === 'predict_churn') {
  try {
    $days = (int)($_GET['days'] ?? 30);
    $days = max(7, min(90, $days));
    
    $q = $pdo->prepare("
      SELECT *
      FROM churn_data
      WHERE user_id = ?
      ORDER BY date ASC
      LIMIT ?
    ");
    $q->execute([$uid, $days]);
    $data = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    if (count($data) < 7) {
      j_err('Insufficient data. Need at least 7 days of historical data.', 422);
    }
    
    $predictor = new ChurnPredictor($data);
    $features = $predictor->extractFeatures();
    $prediction = $predictor->predict();
    $recommendations = $predictor->generateRecommendations($prediction);
    
    j_ok([
      'prediction' => $prediction,
      'recommendations' => $recommendations,
      'key_metrics' => [
        'trend_7d' => round($features['trend_7d'], 2) . '%',
        'volatility' => round($features['cv_receipts'], 3),
        'momentum' => round($features['momentum_7d'], 3),
        'conversion_rate' => round($features['avg_conversion'], 1) . '%',
        'consecutive_declines' => $features['consecutive_declines'],
        'recovery_rate' => round($features['recovery_rate'], 1) . '%'
      ],
      'data_quality' => [
        'days_analyzed' => count($data),
        'confidence' => $prediction['confidence'],
        'model' => $prediction['model']
      ]
    ]);
  } catch (Throwable $e) {
    j_err('Prediction failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'risk_assessment') {
  try {
    $q = $pdo->prepare("
      SELECT *
      FROM churn_data
      WHERE user_id = ?
      ORDER BY date ASC
      LIMIT 30
    ");
    $q->execute([$uid]);
    $data = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    if (empty($data)) {
      j_err('No data available for risk assessment', 404);
    }
    
    $predictor = new ChurnPredictor($data);
    $features = $predictor->extractFeatures();
    $prediction = $predictor->predict();
    
    // Detailed risk breakdown
    $riskBreakdown = [
      'trend' => [
        'status' => abs($features['trend_7d']) > 10 ? 'HIGH' : (abs($features['trend_7d']) > 5 ? 'MEDIUM' : 'LOW'),
        'value' => round($features['trend_7d'], 2) . '%',
        'description' => '7-day trend direction'
      ],
      'volatility' => [
        'status' => $features['cv_receipts'] > 0.4 ? 'HIGH' : ($features['cv_receipts'] > 0.25 ? 'MEDIUM' : 'LOW'),
        'value' => round($features['cv_receipts'], 3),
        'description' => 'Transaction stability'
      ],
      'momentum' => [
        'status' => $features['momentum_7d'] < -0.15 ? 'HIGH' : ($features['momentum_7d'] < 0 ? 'MEDIUM' : 'LOW'),
        'value' => round($features['momentum_7d'], 3),
        'description' => 'Recent momentum'
      ],
      'conversion' => [
        'status' => $features['avg_conversion'] < 30 ? 'HIGH' : ($features['avg_conversion'] < 40 ? 'MEDIUM' : 'LOW'),
        'value' => round($features['avg_conversion'], 1) . '%',
        'description' => 'Traffic conversion efficiency'
      ],
      'recovery' => [
        'status' => $features['recovery_rate'] < 30 ? 'HIGH' : ($features['recovery_rate'] < 50 ? 'MEDIUM' : 'LOW'),
        'value' => round($features['recovery_rate'], 1) . '%',
        'description' => 'Ability to bounce back from declines'
      ]
    ];
    
    j_ok([
      'overall_risk' => $prediction['risk_level'],
      'churn_probability' => $prediction['churn_probability'],
      'risk_score' => $prediction['risk_score'],
      'confidence' => $prediction['confidence'],
      'risk_breakdown' => $riskBreakdown,
      'model_scores' => $prediction['model_scores'],
      'risk_factors' => $prediction['risk_factors'],
      'days_analyzed' => count($data)
    ]);
  } catch (Throwable $e) {
    j_err('Risk assessment failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'feature_analysis') {
  try {
    $q = $pdo->prepare("
      SELECT *
      FROM churn_data
      WHERE user_id = ?
      ORDER BY date ASC
      LIMIT 30
    ");
    $q->execute([$uid]);
    $data = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    if (empty($data)) {
      j_err('No data available', 404);
    }
    
    $predictor = new ChurnPredictor($data);
    $features = $predictor->extractFeatures();
    
    j_ok([
      'features' => $features,
      'feature_count' => count($features),
      'data_points' => count($data)
    ]);
  } catch (Throwable $e) {
    j_err('Feature analysis failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

// ============================================================================
// EXISTING ENDPOINTS (PRESERVED)
// ============================================================================

if ($action === 'latest') {
  try {
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? ORDER BY date DESC LIMIT 1");
    $q->execute([$uid]);
    j_ok(['item' => $q->fetch(PDO::FETCH_ASSOC) ?: []]);
  } catch (Throwable $e) {
    j_err('Load failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'recent') {
  try {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? ORDER BY date DESC LIMIT ?");
    $q->execute([$uid, $limit]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    j_ok(['data' => $rows, 'count' => count($rows), 'period' => $limit . ' days']);
  } catch (Throwable $e) {
    j_err('Load recent data failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'traffic_14days') {
  try {
    $q = $pdo->prepare("
      SELECT date, customer_traffic, receipt_count, sales_volume,
        (morning_receipt_count + swing_receipt_count + graveyard_receipt_count) as total_shift_receipts
      FROM churn_data 
      WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
      ORDER BY date DESC LIMIT 14
    ");
    $q->execute([$uid]);
    $data = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    $totalTraffic = $totalReceipts = $totalSales = $peakTraffic = 0;
    foreach ($data as $row) {
      $traffic = (int)($row['customer_traffic'] ?? 0);
      $totalTraffic += $traffic;
      $totalReceipts += (int)($row['receipt_count'] ?? 0);
      $totalSales += (float)($row['sales_volume'] ?? 0);
      if ($traffic > $peakTraffic) $peakTraffic = $traffic;
    }
    
    $trendPct = 0;
    if (count($data) >= 14) {
      $recentAvg = array_sum(array_column(array_slice($data, 0, 7), 'customer_traffic')) / 7;
      $previousAvg = array_sum(array_column(array_slice($data, 7, 7), 'customer_traffic')) / 7;
      $trendPct = $previousAvg > 0 ? (($recentAvg - $previousAvg) / $previousAvg) * 100 : 0;
    }
    
    j_ok([
      'data' => $data,
      'count' => count($data),
      'summary' => [
        'total_traffic' => $totalTraffic,
        'total_receipts' => $totalReceipts,
        'total_sales' => $totalSales,
        'peak_traffic' => $peakTraffic,
        'avg_daily_traffic' => count($data) > 0 ? round($totalTraffic / count($data), 1) : 0,
        'trend_percentage' => round($trendPct, 2)
      ]
    ]);
  } catch (Throwable $e) {
    j_err('Failed to load 14-day traffic data', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'traffic_today') {
  try {
    $today = date('Y-m-d');
    $q = $pdo->prepare("
      SELECT * FROM churn_data WHERE user_id = ? AND date = ? LIMIT 1
    ");
    $q->execute([$uid, $today]);
    $todayData = $q->fetch(PDO::FETCH_ASSOC);
    
    if (!$todayData) {
      j_ok(['data' => null, 'message' => 'No data available for today']);
      exit;
    }
    
    $morning = (int)($todayData['morning_receipt_count'] ?? 0);
    $swing = (int)($todayData['swing_receipt_count'] ?? 0);
    $graveyard = (int)($todayData['graveyard_receipt_count'] ?? 0);
    $totalTraffic = (int)($todayData['customer_traffic'] ?? 0);
    
    j_ok([
      'data' => $todayData,
      'labels' => ['Morning', 'Swing', 'Graveyard', 'Other'],
      'values' => [$morning, $swing, $graveyard, max(0, $totalTraffic - ($morning + $swing + $graveyard))],
      'total_today' => $totalTraffic
    ]);
  } catch (Throwable $e) {
    j_err('Failed to load today\'s traffic data', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'analytics') {
  try {
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 30)));
    $q = $pdo->prepare("SELECT * FROM churn_data WHERE user_id = ? ORDER BY date DESC LIMIT ?");
    $q->execute([$uid, $limit]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    if (empty($rows)) {
      j_ok(['data' => [], 'insights' => ['avgDailyReceipts' => 0]]);
      exit;
    }
    
    $totalReceipts = $totalSales = $totalTraffic = 0;
    foreach ($rows as $row) {
      $totalReceipts += (int)($row['receipt_count'] ?? 0);
      $totalSales += (float)($row['sales_volume'] ?? 0);
      $totalTraffic += (int)($row['customer_traffic'] ?? 0);
    }
    
    $days = count($rows);
    j_ok([
      'data' => $rows,
      'insights' => [
        'avgDailyReceipts' => round($totalReceipts / $days, 2),
        'avgTransactionValue' => $totalReceipts > 0 ? round($totalSales / $totalReceipts, 2) : 0,
        'avgDailySales' => round($totalSales / $days, 2),
        'conversionRate' => $totalTraffic > 0 ? round(($totalReceipts / $totalTraffic) * 100, 2) : 0,
        'totalDays' => $days
      ]
    ]);
  } catch (Throwable $e) {
    j_err('Analytics failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

j_err('Unknown action', 400);
?>