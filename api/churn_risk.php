<?php
// api/churn_risk.php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$uid = require_login();

// ===== CRITICAL: Set MySQL timezone to Manila =====
try {
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Exception $e) {
    // Continue if timezone setting fails
}

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

/**
 * Map percent (0..100) to level with refined thresholds for business context.
 */
function risk_level_from_pct(float $pct): string {
  if ($pct < 25.0) return 'Low';
  if ($pct < 65.0) return 'Medium';
  return 'High';
}

/**
 * Pull rolling averages from past rows if weekly_* fields are missing.
 */
function compute_rollups(PDO $pdo, int $uid, string $refDate): array {
  $stmt = $pdo->prepare("
    SELECT receipt_count rc, sales_volume sales, customer_traffic ct,
           morning_receipt_count mrc, swing_receipt_count src, graveyard_receipt_count grc,
           morning_sales_volume msv, swing_sales_volume ssv, graveyard_sales_volume gsv
    FROM churn_data
    WHERE user_id = :uid AND date < :d
    ORDER BY date DESC
    LIMIT 14
  ");
  $stmt->execute([':uid'=>$uid, ':d'=>$refDate]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $n = 0; $sumRc=0; $sumSales=0; $sumCt=0;
  $sumMrc=0; $sumSrc=0; $sumGrc=0; $sumMsv=0; $sumSsv=0; $sumGsv=0;
  
  foreach ($rows as $r) {
    $sumRc   += (int)($r['rc'] ?? 0);
    $sumSales+= (float)($r['sales'] ?? 0);
    $sumCt   += (int)($r['ct'] ?? 0);
    $sumMrc  += (int)($r['mrc'] ?? 0);
    $sumSrc  += (int)($r['src'] ?? 0);
    $sumGrc  += (int)($r['grc'] ?? 0);
    $sumMsv  += (float)($r['msv'] ?? 0);
    $sumSsv  += (float)($r['ssv'] ?? 0);
    $sumGsv  += (float)($r['gsv'] ?? 0);
    $n++;
  }
  
  return [
    'avgRc'    => $n ? $sumRc / $n : 0.0,
    'avgSales' => $n ? $sumSales / $n : 0.0,
    'avgCt'    => $n ? $sumCt / $n : 0.0,
    'avgMrc'   => $n ? $sumMrc / $n : 0.0,
    'avgSrc'   => $n ? $sumSrc / $n : 0.0,
    'avgGrc'   => $n ? $sumGrc / $n : 0.0,
    'avgMsv'   => $n ? $sumMsv / $n : 0.0,
    'avgSsv'   => $n ? $sumSsv / $n : 0.0,
    'avgGsv'   => $n ? $sumGsv / $n : 0.0,
    'days'     => $n
  ];
}

/**
 * Get current Manila date for consistent day boundaries.
 */
function get_manila_date(): string {
  $tz = new DateTimeZone('Asia/Manila');
  $now = new DateTime('now', $tz);
  return $now->format('Y-m-d');
}

/**
 * Get current Manila datetime for database operations.
 */
function get_manila_datetime(): string {
  $tz = new DateTimeZone('Asia/Manila');
  $now = new DateTime('now', $tz);
  return $now->format('Y-m-d H:i:s');
}

/**
 * Enhanced business intelligence factor analysis
 */
function analyze_business_factors(array $data, array $rollups): array {
  $factors = [];
  $criticalFactors = [];
  $warningFactors = [];
  $positiveFactors = [];
  $insightFactors = [];
  
  // Extract data
  $rc = $data['rc'];
  $sales = $data['sales'];
  $ct = $data['ct'];
  $mrc = $data['mrc'];
  $src = $data['src'];
  $grc = $data['grc'];
  $msv = $data['msv'];
  $ssv = $data['ssv'];
  $gsv = $data['gsv'];
  $tdp = $data['tdp'];
  $sdp = $data['sdp'];
  $t_drop = $data['t_drop'];
  $imbalance = $data['imbalance'];
  $war = $data['war'];
  $was = $data['was'];
  
  // 1. CRITICAL PERFORMANCE INDICATORS
  if ($tdp >= 40) {
    $criticalFactors[] = "🚨 Severe transaction collapse: -" . round($tdp, 1) . "%";
  } elseif ($tdp >= 25) {
    $criticalFactors[] = "🔴 Critical transaction decline: -" . round($tdp, 1) . "%";
  } elseif ($tdp >= 15) {
    $warningFactors[] = "🟡 Major transaction drop: -" . round($tdp, 1) . "%";
  } elseif ($tdp > 8) {
    $warningFactors[] = "⚠️ Transaction decline: -" . round($tdp, 1) . "%";
  }

  if ($sdp >= 50) {
    $criticalFactors[] = "🚨 Revenue crisis: -" . round($sdp, 1) . "%";
  } elseif ($sdp >= 30) {
    $criticalFactors[] = "🔴 Severe sales decline: -" . round($sdp, 1) . "%";
  } elseif ($sdp >= 20) {
    $warningFactors[] = "🟡 Major sales drop: -" . round($sdp, 1) . "%";
  } elseif ($sdp > 10) {
    $warningFactors[] = "📉 Sales decline: -" . round($sdp, 1) . "%";
  }

  if ($t_drop >= 50) {
    $criticalFactors[] = "🚨 Customer exodus: -" . round($t_drop, 1) . "%";
  } elseif ($t_drop >= 35) {
    $criticalFactors[] = "🔴 Critical traffic loss: -" . round($t_drop, 1) . "%";
  } elseif ($t_drop >= 20) {
    $warningFactors[] = "🟡 High traffic decline: -" . round($t_drop, 1) . "%";
  } elseif ($t_drop > 12) {
    $warningFactors[] = "👥 Traffic reduction: -" . round($t_drop, 1) . "%";
  }

  // 2. SHIFT PERFORMANCE ANALYSIS
  $totalShiftReceipts = $mrc + $src + $grc;
  $totalShiftSales = $msv + $ssv + $gsv;
  
  if ($totalShiftReceipts > 0) {
    $morningPct = ($mrc / $totalShiftReceipts) * 100;
    $swingPct = ($src / $totalShiftReceipts) * 100;
    $graveyardPct = ($grc / $totalShiftReceipts) * 100;
    
    // Morning shift analysis (6AM-2PM typically)
    if ($morningPct < 15 && $totalShiftReceipts >= 20) {
      $warningFactors[] = "🌅 Morning rush underperforming: " . round($morningPct, 1) . "%";
    } elseif ($morningPct > 50 && $totalShiftReceipts >= 20) {
      $positiveFactors[] = "🌅 Strong morning performance: " . round($morningPct, 1) . "%";
    }
    
    // Swing shift analysis (2PM-10PM typically)
    if ($swingPct < 25 && $totalShiftReceipts >= 20) {
      $warningFactors[] = "🌆 Peak hours weak: " . round($swingPct, 1) . "%";
    } elseif ($swingPct > 55 && $totalShiftReceipts >= 20) {
      $positiveFactors[] = "🌆 Excellent peak performance: " . round($swingPct, 1) . "%";
    }
    
    // Graveyard shift analysis (10PM-6AM typically)
    if ($graveyardPct > 50 && $totalShiftReceipts >= 20) {
      $warningFactors[] = "🌙 Over-dependent on night shift: " . round($graveyardPct, 1) . "%";
    } elseif ($graveyardPct > 30 && $graveyardPct <= 50 && $totalShiftReceipts >= 20) {
      $positiveFactors[] = "🌙 Strong late-night business: " . round($graveyardPct, 1) . "%";
    }
    
    // Shift balance analysis
    if ($imbalance > 60) {
      $criticalFactors[] = "🔴 Extreme operational imbalance: " . round($imbalance, 1) . "%";
    } elseif ($imbalance > 40) {
      $warningFactors[] = "⚖️ High shift imbalance: " . round($imbalance, 1) . "%";
    } elseif ($imbalance < 20 && $totalShiftReceipts >= 30) {
      $positiveFactors[] = "⚖️ Well-balanced operations: " . round($imbalance, 1) . "% variance";
    }
  }

  // 3. BUSINESS EFFICIENCY METRICS
  if ($rc > 0 && $sales > 0) {
    $avgTicket = $sales / $rc;
    
    if ($avgTicket < 30) {
      $warningFactors[] = "💰 Very low ticket value: ₱" . round($avgTicket, 0);
    } elseif ($avgTicket < 50) {
      $warningFactors[] = "💰 Low average ticket: ₱" . round($avgTicket, 0);
    } elseif ($avgTicket > 300) {
      $positiveFactors[] = "💎 Premium transaction value: ₱" . round($avgTicket, 0);
    } elseif ($avgTicket > 150) {
      $positiveFactors[] = "💰 High-value transactions: ₱" . round($avgTicket, 0);
    }
    
    // Revenue per shift analysis
    if ($totalShiftSales > 0) {
      $morningRevPct = $totalShiftSales > 0 ? ($msv / $totalShiftSales) * 100 : 0;
      $swingRevPct = $totalShiftSales > 0 ? ($ssv / $totalShiftSales) * 100 : 0;
      $graveyardRevPct = $totalShiftSales > 0 ? ($gsv / $totalShiftSales) * 100 : 0;
      
      if ($swingRevPct < 35 && $totalShiftSales >= 1000) {
        $warningFactors[] = "💸 Peak hours revenue weak: " . round($swingRevPct, 1) . "%";
      }
    }
  }

  // 4. CUSTOMER BEHAVIOR ANALYSIS
  if ($ct > 0 && $rc > 0) {
    $conversionRate = ($rc / $ct) * 100;
    
    if ($conversionRate < 25) {
      $warningFactors[] = "📊 Poor conversion: " . round($conversionRate, 1) . "% buy";
    } elseif ($conversionRate < 40) {
      $warningFactors[] = "📊 Low conversion rate: " . round($conversionRate, 1) . "%";
    } elseif ($conversionRate > 80) {
      $positiveFactors[] = "🎯 Excellent conversion: " . round($conversionRate, 1) . "%";
    } elseif ($conversionRate > 65) {
      $positiveFactors[] = "🎯 High conversion rate: " . round($conversionRate, 1) . "%";
    }
  } elseif ($ct > 0 && $rc == 0) {
    $criticalFactors[] = "🚨 Zero sales conversion from " . $ct . " visitors";
  }

  // 5. TREND ANALYSIS
  if ($war > 0) {
    $weeklyTrendPct = (($rc - $war) / $war) * 100;
    if ($weeklyTrendPct > 15) {
      $positiveFactors[] = "📈 Strong weekly growth: +" . round($weeklyTrendPct, 1) . "%";
    } elseif ($weeklyTrendPct > 5) {
      $positiveFactors[] = "📈 Above weekly average: +" . round($weeklyTrendPct, 1) . "%";
    } elseif ($weeklyTrendPct < -25) {
      $criticalFactors[] = "📉 Severe weekly decline: " . round($weeklyTrendPct, 1) . "%";
    }
  }

  if ($was > 0) {
    $salesTrendPct = (($sales - $was) / $was) * 100;
    if ($salesTrendPct > 20) {
      $positiveFactors[] = "💹 Exceptional sales growth: +" . round($salesTrendPct, 1) . "%";
    } elseif ($salesTrendPct > 8) {
      $positiveFactors[] = "💹 Sales above average: +" . round($salesTrendPct, 1) . "%";
    }
  }

  // 6. OPERATIONAL INSIGHTS
  if ($rc >= 100) {
    $insightFactors[] = "🏪 High-volume operation: " . $rc . " transactions";
  } elseif ($rc >= 50) {
    $insightFactors[] = "🏪 Moderate activity: " . $rc . " transactions";
  } elseif ($rc > 0 && $rc < 10) {
    $warningFactors[] = "📉 Very low activity: " . $rc . " transactions";
  }

  if ($sales >= 10000) {
    $insightFactors[] = "💰 Strong daily revenue: ₱" . number_format($sales, 0);
  } elseif ($sales >= 5000) {
    $insightFactors[] = "💰 Good daily sales: ₱" . number_format($sales, 0);
  }

  // 7. HISTORICAL CONTEXT
  if ($rollups['days'] >= 7) {
    $historicalTrend = '';
    if ($rc > ($rollups['avgRc'] * 1.1)) {
      $historicalTrend = 'improving';
    } elseif ($rc < ($rollups['avgRc'] * 0.9)) {
      $historicalTrend = 'declining';
    } else {
      $historicalTrend = 'stable';
    }
    
    if ($historicalTrend === 'declining' && $rollups['avgRc'] > 0) {
      $declinePct = (($rollups['avgRc'] - $rc) / $rollups['avgRc']) * 100;
      if ($declinePct > 30) {
        $criticalFactors[] = "📊 Historical trend: severely declining";
      } elseif ($declinePct > 15) {
        $warningFactors[] = "📊 Historical trend: declining";
      }
    } elseif ($historicalTrend === 'improving') {
      $positiveFactors[] = "📊 Historical trend: improving";
    }
  }

  // Combine factors by priority
  $allFactors = array_merge($criticalFactors, $warningFactors);
  
  // Add positive factors if there aren't too many negative ones
  if (count($allFactors) <= 3) {
    $allFactors = array_merge($allFactors, array_slice($positiveFactors, 0, 3));
  }
  
  // Add insights if there's room
  if (count($allFactors) <= 4) {
    $allFactors = array_merge($allFactors, array_slice($insightFactors, 0, 2));
  }

  // Default messages for edge cases
  if (!$allFactors) {
    if ($rc == 0 && $sales == 0 && $ct == 0) {
      $allFactors[] = "📊 No transaction data recorded";
      $allFactors[] = "⏳ Add business data for insights";
    } else {
      $allFactors[] = "✅ Baseline performance detected";
      $allFactors[] = "📊 Monitor for trends";
    }
  }

  // Limit factors to prevent UI overflow
  if (count($allFactors) > 7) {
    $allFactors = array_slice($allFactors, 0, 7);
  }

  return [
    'factors' => $allFactors,
    'critical_count' => count($criticalFactors),
    'warning_count' => count($warningFactors),
    'positive_count' => count($positiveFactors)
  ];
}

/**
 * Minimal XGBoost JSON predictor (binary:logistic, gbtree) with hardened parsing.
 */
final class XGBPredictor {
  private array $trees = [];
  private float $base_score = 0.5;
  private string $objective = 'binary:logistic';
  private ?array $feature_names = null;

  public static function loadFrom(string $path): self {
    if (!is_file($path)) {
      throw new RuntimeException("XGBoost model not found at {$path}");
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
      throw new RuntimeException("Failed to read model file {$path}");
    }
    $json = json_decode($raw, true);
    if (!is_array($json)) {
      throw new RuntimeException("Invalid JSON in model file {$path}");
    }

    $learner = $json['learner'] ?? null;
    if (!is_array($learner)) {
      $trees = $json['trees'] ?? null;
      if (!is_array($trees)) {
        throw new RuntimeException("Unexpected model layout: 'learner' missing and no top-level 'trees'.");
      }
      $p = new self();
      $p->trees = $trees;
      $p->objective   = (string)($json['objective']['name'] ?? 'binary:logistic');
      $p->base_score  = self::toFloat($json['learner_model_param']['base_score'] ?? 0.5);
      return $p;
    }

    $objective = (string)($learner['objective']['name'] ?? 'binary:logistic');
    $base_score = self::toFloat($learner['learner_model_param']['base_score'] ?? 0.5);

    $trees = $learner['gradient_booster']['model']['trees'] ?? null;
    if (!is_array($trees)) {
      $trees = $json['trees'] ?? null;
    }
    if (!is_array($trees)) {
      throw new RuntimeException("Could not find trees[] in model JSON");
    }

    $p = new self();
    $p->trees = $trees;
    $p->base_score = $base_score;
    $p->objective = $objective;

    if (isset($learner['feature_names']) && is_array($learner['feature_names'])) {
      $p->feature_names = $learner['feature_names'];
    } elseif (isset($json['feature_names']) && is_array($json['feature_names'])) {
      $p->feature_names = $json['feature_names'];
    } else {
      $p->feature_names = null;
    }

    return $p;
  }

  public function predict_proba(array $feat): float {
    if ($this->objective !== 'binary:logistic') {
      throw new RuntimeException("Unsupported objective: {$this->objective} (only binary:logistic supported)");
    }

    if ($this->feature_names !== null) {
      foreach ($this->feature_names as $name) {
        if (preg_match('/^f\d+$/', (string)$name)) continue;
        if (!array_key_exists($name, $feat)) {
          throw new RuntimeException("Feature '{$name}' required by model is missing in input features.");
        }
      }
    }

    $margin = self::prob_to_logit($this->base_score);
    foreach ($this->trees as $tree) {
      $margin += $this->score_tree($tree, $feat);
    }
    return self::sigmoid($margin);
  }

  private function score_tree(array $tree, array $feat): float {
    return $this->follow($tree, $feat);
  }

  private function follow(array $node, array $feat): float {
    if (array_key_exists('leaf', $node)) {
      return self::toFloat($node['leaf']);
    }

    $split        = $node['split'] ?? null;
    $th           = self::toFloat($node['split_condition'] ?? 0);
    $yesId        = $node['yes'] ?? null;
    $noId         = $node['no'] ?? null;
    $missingId    = $node['missing'] ?? null;
    $defaultLeft  = $node['default_left'] ?? null;

    $children = $node['children'] ?? [];
    $byId = [];
    foreach ($children as $c) {
      if (isset($c['nodeid'])) $byId[$c['nodeid']] = $c;
    }

    $x = $this->get_feature($feat, $split);
    $nextId = null;

    if ($x === null) {
      if ($missingId !== null) {
        $nextId = $missingId;
      } elseif ($defaultLeft !== null) {
        $nextId = $defaultLeft ? $yesId : $noId;
      } else {
        $nextId = $yesId;
      }
    } else {
      $nextId = ($x < $th) ? $yesId : $noId;
    }

    if ($nextId === null || !isset($byId[$nextId])) {
      $first = $children[0] ?? null;
      if ($first === null) return 0.0;
      return $this->follow($first, $feat);
    }
    return $this->follow($byId[$nextId], $feat);
  }

  private function get_feature(array $feat, ?string $name): ?float {
    if ($name === null) return null;

    if (array_key_exists($name, $feat)) {
      $v = $feat[$name];
      if ($v === null) return null;
      return self::toFloat($v);
    }

    if (preg_match('/^f(\d+)$/', $name, $m)) {
      $idx = (int)$m[1];
      if (isset($feat['_vector']) && is_array($feat['_vector']) && array_key_exists($idx, $feat['_vector'])) {
        return self::toFloat($feat['_vector'][$idx]);
      }
      return null;
    }

    return null;
  }

  private static function prob_to_logit(float $p): float {
    $p = max(min($p, 1.0 - 1e-12), 1e-12);
    return log($p / (1.0 - $p));
  }

  private static function sigmoid(float $z): float {
    if ($z >= 0) {
      $ez = exp(-$z);
      return 1.0 / (1.0 + $ez);
    } else {
      $ez = exp($z);
      return $ez / (1.0 + $ez);
    }
  }

  private static function toFloat($v): float {
    if (is_string($v)) return (float)$v;
    return (float)$v;
  }
}

// -------- Controller --------
$action = $_GET['action'] ?? 'latest';

if ($action === 'latest') {
  try {
    $q = $pdo->prepare("
      SELECT id, user_id, risk_score, risk_level, level, description, factors, risk_percentage, for_date, created_at
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
        'has'             => true,
        'id'              => (int)$row['id'],
        'for_date'        => $row['for_date'],
        'risk_percentage' => (float)$row['risk_percentage'],
        'risk_score'      => (float)$row['risk_score'],
        'risk_level'      => (string)($row['risk_level'] ?: $row['level']),
        'level'           => (string)($row['risk_level'] ?: $row['level']),
        'description'     => (string)($row['description'] ?? ''),
        'factors'         => $factors,
      ]);
    }
  } catch (Throwable $e) {
    j_err('Load prediction failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

if ($action === 'run') {
  try {
    // ===== FIX: Use Manila date consistently =====
    $manilaDate = get_manila_date();
    $manilaDateTime = get_manila_datetime();

    // ===== FIX: Query specifically for TODAY's data, not just latest =====
    $q = $pdo->prepare("
      SELECT * FROM churn_data 
      WHERE user_id = ? AND date = ?
      ORDER BY created_at DESC 
      LIMIT 1
    ");
    $q->execute([$uid, $manilaDate]);
    $cd = $q->fetch(PDO::FETCH_ASSOC);

    // If no data for today, create default entry with UPSERT
    if (!$cd) {
      $defaultInsert = $pdo->prepare("
        INSERT INTO churn_data 
          (user_id, date, receipt_count, sales_volume, customer_traffic,
           morning_receipt_count, swing_receipt_count, graveyard_receipt_count,
           morning_sales_volume, swing_sales_volume, graveyard_sales_volume,
           previous_day_receipt_count, previous_day_sales_volume,
           weekly_average_receipts, weekly_average_sales,
           transaction_drop_percentage, sales_drop_percentage, created_at)
        VALUES 
          (?, ?, 0, 0.00, 0, 0, 0, 0, 0.00, 0.00, 0.00, 0, 0.00, 0.00, 0.00, 0.00, 0.00, ?)
        ON DUPLICATE KEY UPDATE
          updated_at = CURRENT_TIMESTAMP
      ");
      $defaultInsert->execute([$uid, $manilaDate, $manilaDateTime]);

      $q->execute([$uid, $manilaDate]);
      $cd = $q->fetch(PDO::FETCH_ASSOC);
    }

    // ===== FIX: Always use today's Manila date for prediction =====
    $forDate = $manilaDate;

    // Extract and validate inputs
    $rc    = max(0,   (int)($cd['receipt_count'] ?? 0));
    $sales = max(0.0, (float)($cd['sales_volume'] ?? 0));
    $ct    = max(0,   (int)($cd['customer_traffic'] ?? 0));

    $mrc = max(0, (int)($cd['morning_receipt_count'] ?? 0));
    $src = max(0, (int)($cd['swing_receipt_count'] ?? 0));
    $grc = max(0, (int)($cd['graveyard_receipt_count'] ?? 0));

    $msv = max(0.0, (float)($cd['morning_sales_volume'] ?? 0));
    $ssv = max(0.0, (float)($cd['swing_sales_volume'] ?? 0));
    $gsv = max(0.0, (float)($cd['graveyard_sales_volume'] ?? 0));

    $war = max(0.0, (float)($cd['weekly_average_receipts'] ?? 0));
    $was = max(0.0, (float)($cd['weekly_average_sales'] ?? 0));

    $tdp = max(0.0, (float)($cd['transaction_drop_percentage'] ?? 0));
    $sdp = max(0.0, (float)($cd['sales_drop_percentage'] ?? 0));

    // Enhanced rollup calculations
    $roll = compute_rollups($pdo, $uid, $forDate);
    if ($war <= 0 && $roll['avgRc'] > 0)   $war = $roll['avgRc'];
    if ($was <= 0 && $roll['avgSales'] > 0)$was = $roll['avgSales'];
    $avgCt = max(0.0, $roll['avgCt']);

    // Enhanced drop calculations
    if ($war > 0 && $rc >= 0 && $tdp <= 0) {
      $tdp = max(0.0, min(100.0, ($war - $rc) / $war * 100.0));
    }
    if ($was > 0 && $sales >= 0 && $sdp <= 0) {
      $sdp = max(0.0, min(100.0, ($was - $sales) / $was * 100.0));
    }

    // Enhanced traffic analysis
    $t_drop = 0.0;
    if ($avgCt > 0 && $ct >= 0) {
      $t_drop = max(0.0, min(100.0, ($avgCt - $ct) / $avgCt * 100.0));
    }

    // Enhanced shift imbalance calculation
    $shifts = [$mrc, $src, $grc];
    $totalShifts = array_sum($shifts);
    $mean = $totalShifts > 0 ? $totalShifts / count($shifts) : 0.0;
    $imbalance = 0.0;
    if ($mean > 0 && $totalShifts > 0) {
      $sq = 0.0;
      foreach ($shifts as $v) $sq += ($v - $mean) * ($v - $mean);
      $std = sqrt($sq / count($shifts));
      $imbalance = min(100.0, ($std / $mean) * 100.0);
    }

    // Prepare data for analysis
    $analysisData = [
      'rc' => $rc, 'sales' => $sales, 'ct' => $ct,
      'mrc' => $mrc, 'src' => $src, 'grc' => $grc,
      'msv' => $msv, 'ssv' => $ssv, 'gsv' => $gsv,
      'tdp' => $tdp, 'sdp' => $sdp,  't_drop' => $t_drop, 'imbalance' => $imbalance,
      'war' => $war, 'was' => $was
    ];

    // Enhanced business factor analysis
    $factorAnalysis = analyze_business_factors($analysisData, $roll);

    // ===== XGBoost inference =====
    $feat = [
      'rc'        => (float)$rc,
      'sales'     => (float)$sales,
      'ct'        => (float)$ct,
      'tdp'       => (float)$tdp,
      'sdp'       => (float)$sdp,
      't_drop'    => (float)$t_drop,
      'imbalance' => (float)$imbalance,
    ];

    $usedFallback = false;
    $modelConfidence = 1.0;

    if ($rc == 0 && $sales == 0 && $ct == 0) {
      // New user with no data
      $prob = 0.05;
      $modelConfidence = 0.1;
    } else {
      try {
        $modelPath = __DIR__ . '/models/churn_xgb.json';
        $pred = XGBPredictor::loadFrom($modelPath);
        $prob = $pred->predict_proba($feat);
        $modelConfidence = 0.95;
      } catch (Throwable $mx) {
        // Enhanced fallback heuristic
        $usedFallback = true;
        $modelConfidence = 0.6;
        
        // Multi-factor risk scoring
        $riskScore = 0.0;
        
        // Transaction risk (40% weight)
        if ($tdp > 0) $riskScore += ($tdp / 100.0) * 0.40;
        
        // Sales risk (35% weight)
        if ($sdp > 0) $riskScore += ($sdp / 100.0) * 0.35;
        
        // Traffic risk (15% weight)
        if ($t_drop > 0) $riskScore += ($t_drop / 100.0) * 0.15;
        
        // Operational risk (10% weight)
        if ($imbalance > 0) $riskScore += min(1.0, $imbalance / 50.0) * 0.10;
        
        // Critical factor multipliers
        if ($factorAnalysis['critical_count'] > 0) {
          $riskScore *= (1.0 + ($factorAnalysis['critical_count'] * 0.2));
        }
        
        // Low activity penalty
        if ($rc < 5 && $sales < 500) {
          $riskScore += 0.15;
        }
        
        // Zero conversion penalty
        if ($ct > 0 && $rc == 0) {
          $riskScore += 0.25;
        }
        
        $prob = max(0.0, min(1.0, $riskScore));
      }
    }

    $riskPct = round($prob * 100.0, 2);
    $level = risk_level_from_pct($riskPct);

    // Enhanced description generation
    $criticalCount = $factorAnalysis['critical_count'];
    $warningCount = $factorAnalysis['warning_count'];
    $positiveCount = $factorAnalysis['positive_count'];

    $desc = match ($level) {
      'High' => $criticalCount > 2
        ? 'URGENT: Multiple critical issues detected. Immediate intervention required to prevent customer loss.'
        : ($criticalCount > 0
          ? 'High churn risk with critical performance issues. Implement retention strategies immediately.'
          : 'High churn risk identified. Review operations and engage customer retention tactics now.'),
      'Medium' => $warningCount > 2
        ? 'Moderate risk with multiple warning indicators. Monitor closely and prepare intervention strategies.'
        : ($warningCount > 0
          ? 'Moderate churn risk detected. Address performance issues and watch trends closely.'
          : 'Moderate churn risk. Maintain vigilance and consider proactive customer engagement.'),
      default => ($rc == 0 && $sales == 0)
        ? 'New business profile. Add transaction data for accurate churn prediction and insights.'
        : ($positiveCount > 0
          ? 'Low churn risk with positive performance indicators. Maintain current successful strategies.'
          : 'Low churn risk detected. Continue monitoring metrics for early warning signs.')
    };

    // Enhanced confidence-based description
    if ($modelConfidence < 0.5) {
      $desc .= ' (Limited data - add more transaction history for improved accuracy)';
    } elseif ($usedFallback && $modelConfidence < 0.8) {
      $desc .= ' (Using heuristic analysis - model optimization recommended)';
    }

    // ===== FIX: DELETE today's previous predictions, then INSERT fresh one =====
    // Step 1: Delete ALL previous predictions for TODAY (for_date = today's Manila date)
    $deletePrev = $pdo->prepare("
      DELETE FROM churn_predictions 
      WHERE user_id = ? AND for_date = ?
    ");
    $deletePrev->execute([$uid, $manilaDate]);

    // Step 2: Insert fresh prediction for TODAY
    $insert = $pdo->prepare("
      INSERT INTO churn_predictions
        (user_id, date, risk_score, risk_level, factors, description, created_at, level, risk_percentage, for_date)
      VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $insert->execute([
      $uid,
      $manilaDate,
      round($riskPct / 100.0, 4),
      $level,
      json_encode($factorAnalysis['factors'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
      $desc,
      $manilaDateTime,
      $level,
      round($riskPct, 3),
      $forDate
    ]);

    j_ok([
      'saved'           => true,
      'has'             => true,
      'for_date'        => $forDate,
      'risk_percentage' => $riskPct,
      'risk_score'      => round($riskPct / 100.0, 4),
      'risk_level'      => $level,
      'level'           => $level,
      'description'     => $desc,
      'factors'         => $factorAnalysis['factors'],
      'is_new_user'     => ($rc == 0 && $sales == 0),
      'data_available'  => ($rc > 0 || $sales > 0 || $ct > 0),
      'model_confidence' => $modelConfidence,
      'analysis_quality' => $roll['days'] >= 7 ? 'high' : ($roll['days'] >= 3 ? 'medium' : 'low')
    ]);
  } catch (Throwable $e) {
    j_err('Prediction run failed', 500, ['detail'=>$e->getMessage()]);
  }
  exit;
}

j_err('Unknown action', 400);