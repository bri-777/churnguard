<?php
// api/dashboard.php — Enhanced accuracy for retention rate & churn risk based on user predictions
require __DIR__ . '/_bootstrap.php';
require_login();

$uid = (int)$_SESSION['user_id'];

/* ---------- helpers ---------- */
function clamp_pct_or_null($v) {
  if ($v === null) return null;
  $n = (float)$v;
  if (!is_finite($n)) return null;
  if ($n < 0) $n = 0;
  if ($n > 100) $n = 100;
  return $n;
}

function safe_query(PDO $pdo, string $sql, array $params = []) {
  try {
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s;
  } catch (Throwable $e) {
    return null;
  }
}

function single_val($stmt, string $key) {
  if (!$stmt) return null;
  $r = $stmt->fetch(PDO::FETCH_ASSOC);
  return $r[$key] ?? null;
}

function fmt_range($startYmd, $endYmd) {
  $s = date_create($startYmd);
  $e = date_create($endYmd);
  if (!$s || !$e) return '';
  $sameMonth = $s->format('Y-m') === $e->format('Y-m');
  if ($sameMonth) {
    return $s->format('M j') . '–' . $e->format('j, Y');
  }
  return $s->format('M j, Y') . ' – ' . $e->format('M j, Y');
}

/**
 * Enhanced retention rate calculation based on user prediction accuracy
 */
function calculate_accurate_retention_rate(PDO $pdo, int $uid, string $current_date): array {
  // Method 1: Use latest prediction with business context
  $latest_prediction = safe_query(
    $pdo,
    "SELECT 
       cp.risk_percentage,
       cp.risk_level,
       cp.for_date,
       cp.created_at,
       cd.receipt_count,
       cd.sales_volume,
       cd.customer_traffic,
       cd.transaction_drop_percentage,
       cd.sales_drop_percentage
     FROM churn_predictions cp
     LEFT JOIN churn_data cd ON cp.user_id = cd.user_id AND cp.for_date = cd.date
     WHERE cp.user_id = ?
     ORDER BY cp.for_date DESC, cp.created_at DESC
     LIMIT 1",
    [$uid]
  );

  if ($latest_prediction) {
    $pred_data = $latest_prediction->fetch(PDO::FETCH_ASSOC);
    if ($pred_data && $pred_data['risk_percentage'] !== null) {
      $risk_pct = (float)$pred_data['risk_percentage'];
      
      // Enhanced retention calculation with business context
      $base_retention = 100.0 - $risk_pct;
      
      // Apply business performance adjustments
      $performance_modifier = 0.0;
      
      // Transaction performance impact
      if ($pred_data['transaction_drop_percentage'] !== null) {
        $tx_drop = (float)$pred_data['transaction_drop_percentage'];
        if ($tx_drop > 30) {
          $performance_modifier -= 5.0; // Severe transaction decline impacts retention
        } elseif ($tx_drop > 15) {
          $performance_modifier -= 2.0;
        } elseif ($tx_drop < 5) {
          $performance_modifier += 1.0; // Stable transactions boost retention
        }
      }
      
      // Sales performance impact
      if ($pred_data['sales_drop_percentage'] !== null) {
        $sales_drop = (float)$pred_data['sales_drop_percentage'];
        if ($sales_drop > 25) {
          $performance_modifier -= 3.0;
        } elseif ($sales_drop > 10) {
          $performance_modifier -= 1.0;
        }
      }
      
      // Activity level impact
      $activity_score = 0;
      if ($pred_data['receipt_count'] !== null) $activity_score += min(20, (int)$pred_data['receipt_count']);
      if ($pred_data['customer_traffic'] !== null) $activity_score += min(30, (int)$pred_data['customer_traffic']);
      
      if ($activity_score > 40) {
        $performance_modifier += 2.0; // High activity improves retention
      } elseif ($activity_score < 10) {
        $performance_modifier -= 3.0; // Low activity hurts retention
      }
      
      // Risk level categorical adjustment
      switch ($pred_data['risk_level']) {
        case 'High':
          $performance_modifier -= 2.0;
          break;
        case 'Low':
          $performance_modifier += 1.5;
          break;
      }
      
      $adjusted_retention = clamp_pct_or_null($base_retention + $performance_modifier);
      
      return [
        'rate' => $adjusted_retention,
        'source' => "Enhanced prediction-based (risk: {$risk_pct}%, adjusted: " . round($performance_modifier, 1) . "%)",
        'confidence' => 0.9,
        'for_date' => $pred_data['for_date']
      ];
    }
  }

  // Method 2: 7-day weighted average from predictions
  $week_predictions = safe_query(
    $pdo,
    "SELECT 
       cp.risk_percentage,
       cp.for_date,
       cd.receipt_count,
       cd.customer_traffic,
       DATEDIFF(?, cp.for_date) as days_ago
     FROM churn_predictions cp
     LEFT JOIN churn_data cd ON cp.user_id = cd.user_id AND cp.for_date = cd.date
     WHERE cp.user_id = ?
       AND cp.for_date >= DATE_SUB(?, INTERVAL 7 DAY)
       AND cp.risk_percentage IS NOT NULL
     ORDER BY cp.for_date DESC",
    [$current_date, $uid, $current_date]
  );

  if ($week_predictions) {
    $week_data = $week_predictions->fetchAll(PDO::FETCH_ASSOC);
    if (count($week_data) >= 3) { // Need at least 3 days for reliability
      $weighted_risk = 0.0;
      $total_weight = 0.0;
      
      foreach ($week_data as $day) {
        $days_ago = (int)$day['days_ago'];
        $weight = max(0.1, 1.0 - ($days_ago * 0.15)); // More recent = higher weight
        
        // Activity-based weight adjustment
        $activity_weight = 1.0;
        if ($day['receipt_count'] !== null && $day['customer_traffic'] !== null) {
          $activity = (int)$day['receipt_count'] + (int)$day['customer_traffic'];
          $activity_weight = min(2.0, max(0.5, $activity / 25.0));
        }
        
        $final_weight = $weight * $activity_weight;
        $weighted_risk += (float)$day['risk_percentage'] * $final_weight;
        $total_weight += $final_weight;
      }
      
      $avg_risk = $total_weight > 0 ? $weighted_risk / $total_weight : 0;
      $retention = clamp_pct_or_null(100.0 - $avg_risk);
      
      return [
        'rate' => $retention,
        'source' => "7-day weighted prediction average (" . count($week_data) . " days)",
        'confidence' => min(0.85, 0.5 + (count($week_data) * 0.05)),
        'for_date' => $current_date
      ];
    }
  }

  // Method 3: Latest available prediction (fallback)
  $any_prediction = safe_query(
    $pdo,
    "SELECT risk_percentage, for_date
     FROM churn_predictions
     WHERE user_id = ?
       AND risk_percentage IS NOT NULL
     ORDER BY for_date DESC, created_at DESC
     LIMIT 1",
    [$uid]
  );

  if ($any_prediction) {
    $fallback_data = $any_prediction->fetch(PDO::FETCH_ASSOC);
    if ($fallback_data) {
      $retention = clamp_pct_or_null(100.0 - (float)$fallback_data['risk_percentage']);
      return [
        'rate' => $retention,
        'source' => "Latest available prediction (for_date: {$fallback_data['for_date']})",
        'confidence' => 0.6,
        'for_date' => $fallback_data['for_date']
      ];
    }
  }

  return [
    'rate' => null,
    'source' => "No prediction data available",
    'confidence' => 0.0,
    'for_date' => null
  ];
}

/**
 * Enhanced churn risk calculation with prediction accuracy
 */
function calculate_accurate_churn_risk(PDO $pdo, int $uid, string $current_date): array {
  // Method 1: Exact date match (highest priority)
  $exact_prediction = safe_query(
    $pdo,
    "SELECT 
       cp.risk_percentage,
       cp.risk_level,
       cp.factors,
       cp.description,
       cd.receipt_count,
       cd.sales_volume,
       cd.customer_traffic
     FROM churn_predictions cp
     LEFT JOIN churn_data cd ON cp.user_id = cd.user_id AND cp.for_date = cd.date
     WHERE cp.user_id = ? AND cp.for_date = ?
     ORDER BY cp.created_at DESC
     LIMIT 1",
    [$uid, $current_date]
  );

  if ($exact_prediction) {
    $exact_data = $exact_prediction->fetch(PDO::FETCH_ASSOC);
    if ($exact_data && $exact_data['risk_percentage'] !== null) {
      $risk = clamp_pct_or_null($exact_data['risk_percentage']);
      
      // Confidence based on data completeness
      $confidence = 0.95;
      if ($exact_data['receipt_count'] == 0 && $exact_data['sales_volume'] == 0) {
        $confidence = 0.4; // Low confidence for no-data predictions
      }
      
      return [
        'risk' => $risk,
        'level' => $exact_data['risk_level'],
        'source' => "Current day prediction (exact match)",
        'confidence' => $confidence,
        'factors' => $exact_data['factors'] ? json_decode($exact_data['factors'], true) : [],
        'description' => $exact_data['description']
      ];
    }
  }

  // Method 2: Most recent prediction with trend analysis
  $recent_predictions = safe_query(
    $pdo,
    "SELECT 
       risk_percentage,
       risk_level,
       for_date,
       factors,
       description,
       DATEDIFF(?, for_date) as days_ago
     FROM churn_predictions
     WHERE user_id = ?
       AND risk_percentage IS NOT NULL
       AND for_date >= DATE_SUB(?, INTERVAL 3 DAY)
     ORDER BY for_date DESC, created_at DESC
     LIMIT 3",
    [$current_date, $uid, $current_date]
  );

  if ($recent_predictions) {
    $recent_data = $recent_predictions->fetchAll(PDO::FETCH_ASSOC);
    if (count($recent_data) > 0) {
      $latest = $recent_data[0];
      $risk = clamp_pct_or_null($latest['risk_percentage']);
      
      // Trend adjustment if we have multiple points
      if (count($recent_data) >= 2) {
        $trend_adjustment = 0.0;
        for ($i = 1; $i < count($recent_data); $i++) {
          $risk_diff = $risk - (float)$recent_data[$i]['risk_percentage'];
          $weight = 1.0 / ($i + 1); // Decreasing weight for older data
          $trend_adjustment += $risk_diff * $weight * 0.1; // Small trend influence
        }
        $risk = clamp_pct_or_null($risk + $trend_adjustment);
      }
      
      $confidence = max(0.7, 0.9 - ((int)$latest['days_ago'] * 0.1));
      
      return [
        'risk' => $risk,
        'level' => $latest['risk_level'],
        'source' => "Recent prediction with trend analysis (days_ago: {$latest['days_ago']})",
        'confidence' => $confidence,
        'factors' => $latest['factors'] ? json_decode($latest['factors'], true) : [],
        'description' => $latest['description']
      ];
    }
  }

  // Method 3: Any available prediction (last resort)
  $any_prediction = safe_query(
    $pdo,
    "SELECT risk_percentage, risk_level, for_date, factors, description
     FROM churn_predictions
     WHERE user_id = ?
       AND risk_percentage IS NOT NULL
     ORDER BY for_date DESC, created_at DESC
     LIMIT 1",
    [$uid]
  );

  if ($any_prediction) {
    $any_data = $any_prediction->fetch(PDO::FETCH_ASSOC);
    if ($any_data) {
      return [
        'risk' => clamp_pct_or_null($any_data['risk_percentage']),
        'level' => $any_data['risk_level'],
        'source' => "Oldest available prediction (for_date: {$any_data['for_date']})",
        'confidence' => 0.5,
        'factors' => $any_data['factors'] ? json_decode($any_data['factors'], true) : [],
        'description' => $any_data['description']
      ];
    }
  }

  return [
    'risk' => null,
    'level' => null,
    'source' => "No prediction data available",
    'confidence' => 0.0,
    'factors' => [],
    'description' => null
  ];
}

try {
  /* ---------- 1) Freshest two days from churn_data (unchanged) ---------- */
  $s = safe_query(
    $pdo,
    "SELECT
       date,
       COALESCE(sales_volume, 0)              AS sales_volume,
       COALESCE(customer_traffic, 0)          AS customer_traffic,
       transaction_drop_percentage            AS tx_drop_pct,
       updated_at
     FROM churn_data
     WHERE user_id = ?
     ORDER BY date DESC, updated_at DESC
     LIMIT 2",
    [$uid]
  );
  $latest = $prev = null;
  if ($s) {
    $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $latest = $rows[0] ?? null;
    $prev   = $rows[1] ?? null;
  }

  // dashboard's working date
  $current_date = $latest['date'] ?? date('Y-m-d');

  $todays_sales     = $latest ? (float)$latest['sales_volume'] : null;
  $todays_customers = $latest ? (int)$latest['customer_traffic'] : null;

  $revenue_change   = null;
  $customers_change = null;
  if ($latest && $prev) {
    $y_sales = (float)$prev['sales_volume'];
    $y_cust  = (int)$prev['customer_traffic'];
    $revenue_change   = ($y_sales > 0) ? (($todays_sales - $y_sales) / $y_sales) * 100.0 : null;
    $customers_change = ($y_cust  > 0) ? (($todays_customers - $y_cust) / $y_cust) * 100.0 : null;
  }

  /* ---------- 2) Enhanced retention rate calculation ---------- */
  $retention_result = calculate_accurate_retention_rate($pdo, $uid, $current_date);
  $retention_rate = $retention_result['rate'];
  $retention_source = $retention_result['source'];
  $retention_confidence = $retention_result['confidence'];

  /* ---------- 3) Enhanced churn risk calculation ---------- */
  $churn_result = calculate_accurate_churn_risk($pdo, $uid, $current_date);
  $risk_current = $churn_result['risk'];
  $risk_source = $churn_result['source'];
  $risk_confidence = $churn_result['confidence'];
  $risk_factors = $churn_result['factors'];
  $risk_description = $churn_result['description'];

  /* ---------- 4) Enhanced 7-day deltas with prediction accuracy ---------- */
  $d = $current_date;
  $win_curr_start = date('Y-m-d', strtotime($d . ' -6 day'));
  $win_prev_start = date('Y-m-d', strtotime($d . ' -13 day'));
  $win_prev_end   = date('Y-m-d', strtotime($d . ' -7 day'));

  // Enhanced retention change calculation
  $ret_curr = null; $ret_prev = null; $ret_change_tip = null;

  // Current window - use prediction-based calculation
  $curr_retention_result = calculate_accurate_retention_rate($pdo, $uid, $current_date);
  $ret_curr = $curr_retention_result['rate'];

  // Previous window - average of predictions
  $prev_retention_avg = safe_query(
    $pdo,
    "SELECT AVG(100.0 - risk_percentage) as avg_retention
     FROM churn_predictions
     WHERE user_id = ?
       AND for_date BETWEEN ? AND ?
       AND risk_percentage IS NOT NULL",
    [$uid, $win_prev_start, $win_prev_end]
  );
  $ret_prev = single_val($prev_retention_avg, 'avg_retention');
  if ($ret_prev !== null) {
    $ret_prev = clamp_pct_or_null($ret_prev);
  }

  $retention_change = null;
  if ($ret_curr !== null && $ret_prev !== null) {
    $retention_change = round($ret_curr - $ret_prev, 2);
    $ret_change_tip = "Current: {$curr_retention_result['source']} vs Prior: " . 
                      fmt_range($win_prev_start, $win_prev_end) . 
                      " (confidence: " . round($retention_confidence * 100, 0) . "%)";
  } elseif ($ret_curr !== null) {
    $ret_change_tip = "Current: {$curr_retention_result['source']} (confidence: " . 
                      round($retention_confidence * 100, 0) . "%)";
  }

  // Enhanced risk change calculation
  $risk_prev_avg = null; $risk_change_tip = null;
  $prisk = safe_query(
    $pdo,
    "SELECT AVG(risk_percentage) AS ar
     FROM churn_predictions
     WHERE user_id = ?
       AND for_date BETWEEN ? AND ?
       AND risk_percentage IS NOT NULL",
    [$uid, $win_prev_start, $win_prev_end]
  );
  $ar_prev = single_val($prisk, 'ar');
  if ($ar_prev !== null) {
    $risk_prev_avg = clamp_pct_or_null((float)$ar_prev);
    $risk_change_tip = "Current: {$churn_result['source']} vs Prior: " . 
                       fmt_range($win_prev_start, $win_prev_end) . 
                       " (confidence: " . round($risk_confidence * 100, 0) . "%)";
  } elseif ($risk_current !== null) {
    $risk_change_tip = "Current: {$churn_result['source']} (confidence: " . 
                       round($risk_confidence * 100, 0) . "%)";
  }

  $risk_change = null;
  if ($risk_current !== null && $risk_prev_avg !== null) {
    $risk_change = round($risk_current - $risk_prev_avg, 2);
  }

  /* ---------- 5) Enhanced Response ---------- */
  json_ok([
    'todays_sales'     => $todays_sales,
    'todays_customers' => $todays_customers,
    'retention_rate'   => $retention_rate,
    'churn_risk'       => $risk_current,
    'revenue_change'   => $revenue_change,
    'customers_change' => $customers_change,
    'retention_change' => $retention_change,
    'risk_change'      => $risk_change,

    'reference_date'   => $current_date,

    // Enhanced tooltips with confidence indicators
    'tooltips' => [
      'retention_change' => $ret_change_tip,
      'risk_change'      => $risk_change_tip,
    ],

    // Enhanced provenance with confidence metrics
    'provenance' => [
      'retention_rate_source' => $retention_source,
      'churn_risk_source'     => $risk_source,
      'retention_confidence'  => round($retention_confidence * 100, 0),
      'risk_confidence'       => round($risk_confidence * 100, 0),
    ],

    // Additional prediction context
    'prediction_context' => [
      'risk_level' => $churn_result['level'],
      'risk_factors_count' => count($risk_factors),
      'has_current_day_prediction' => strpos($risk_source, 'exact match') !== false,
      'data_quality' => $retention_confidence >= 0.8 && $risk_confidence >= 0.8 ? 'high' : 
                       ($retention_confidence >= 0.6 && $risk_confidence >= 0.6 ? 'medium' : 'low')
    ],
  ]);
} catch (Throwable $e) {
  json_error('Dashboard error', 500, ['detail' => $e->getMessage()]);
}