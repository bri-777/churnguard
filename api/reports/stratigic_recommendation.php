<?php
/* api/reports/stratigic_recommendation.php */
require __DIR__ . '/../_bootstrap.php';
$uid = require_login();

/**
 * Small helper to keep % in 0..100
 */
function clamp_pct($v): float {
  $n = (float)$v;
  if ($n <= 1.0) $n *= 100.0;     // support 0..1
  if ($n < 0) $n = 0;
  if ($n > 100) $n = 100;
  return round($n, 2);
}

/**
 * Build a recommendation item
 */
function rec($priority, $title, $desc, $impact=null, $eta=null, $cost=null, $metrics=[]) {
  return [
    'priority'     => $priority,             // High | Medium | Low
    'title'        => $title,
    'description'  => $desc,
    'impact'       => $impact,               // e.g. "+12% visits"
    'eta'          => $eta,                  // e.g. "3 days"
    'cost'         => $cost,                 // e.g. "Low"
    'metrics'      => $metrics               // array of strings
  ];
}

/**
 * Human-readable level from percent
 */
function level_from_pct(float $p): string {
  if ($p >= 67) return 'High';
  if ($p >= 34) return 'Medium';
  return 'Low';
}

try {
  /* ---- Gather latest context ---- */
  // Latest churn_data (for behavior + drops)
  $qd = $pdo->prepare("SELECT *
                       FROM churn_data
                       WHERE user_id=?
                       ORDER BY date DESC
                       LIMIT 1");
  $qd->execute([$uid]);
  $cd = $qd->fetch(PDO::FETCH_ASSOC) ?: [];

  // Previous day (for trend heuristics)
  $qdp = $pdo->prepare("SELECT *
                        FROM churn_data
                        WHERE user_id=?
                        ORDER BY date DESC
                        LIMIT 2");
  $qdp->execute([$uid]);
  $two = $qdp->fetchAll(PDO::FETCH_ASSOC);
  $yday = $two[1] ?? null;

  // Latest prediction (risk %)
  $qp = $pdo->prepare("SELECT risk_percentage, risk_level, description, factors
                       FROM churn_predictions
                       WHERE user_id=?
                       ORDER BY created_at DESC
                       LIMIT 1");
  $qp->execute([$uid]);
  $pr = $qp->fetch(PDO::FETCH_ASSOC) ?: ['risk_percentage'=>0,'risk_level'=>null];

  $riskPct  = clamp_pct($pr['risk_percentage'] ?? 0);
  $riskLvl  = $pr['risk_level'] ? ucfirst(strtolower($pr['risk_level'])) : level_from_pct($riskPct);

  // Extract signals from churn_data
  $sales      = (float)($cd['sales_volume'] ?? 0);
  $receipts   = (int)  ($cd['receipt_count'] ?? 0);
  $traffic    = (int)  ($cd['customer_traffic'] ?? 0);
  $avgBasket  = $receipts > 0 ? ($sales / max(1,$receipts)) : 0.0;

  $dropSales  = (float)($cd['sales_drop_percentage'] ?? 0);
  $dropTxn    = (float)($cd['transaction_drop_percentage'] ?? 0);

  $m_cnt = (int)($cd['morning_receipt_count'] ?? 0);
  $s_cnt = (int)($cd['swing_receipt_count'] ?? 0);
  $g_cnt = (int)($cd['graveyard_receipt_count'] ?? 0);

  $weeklyAvgSales    = (float)($cd['weekly_average_sales'] ?? 0);
  $weeklyAvgReceipts = (float)($cd['weekly_average_receipts'] ?? 0);
  $weeklyAvgBasket   = $weeklyAvgReceipts > 0 ? ($weeklyAvgSales / max(1,$weeklyAvgReceipts)) : 0.0;

  // Day-over-day traffic trend
  $trend = 0.0;
  if ($yday && (int)($yday['customer_traffic'] ?? 0) > 0) {
    $trend = (($traffic - (int)$yday['customer_traffic']) / max(1,(int)$yday['customer_traffic'])) * 100.0;
  }
  $trend = round($trend, 2);

  // Shift imbalance indicator
  $maxShift = max($m_cnt, $s_cnt, $g_cnt);
  $minShift = min($m_cnt, $s_cnt, $g_cnt);
  $shiftImbalance = ($maxShift > 0) ? round((($maxShift - $minShift) / max(1,$maxShift)) * 100.0, 2) : 0.0;

  // Basket health vs weekly baseline
  $basketDelta = $weeklyAvgBasket > 0 ? round((($avgBasket - $weeklyAvgBasket) / $weeklyAvgBasket) * 100.0, 2) : 0.0;

  /* ---- Rule engine ---- */
  $recs = [];

  // Base on overall risk level
  if ($riskPct >= 67) {
    $recs[] = rec(
      'High',
      'Immediate Win-Back Campaign (SMS + App Push)',
      'Target customers with declining frequency in the last 7–14 days using time-boxed incentives. Use a personalized message and a single-tap coupon to return within 72 hours.',
      '+12–20% short-term visits',
      '3 days',
      'Low',
      [
        "Risk: {$riskPct}%",
        "Traffic trend vs yesterday: ".($trend>=0?'+':'').$trend.'%',
        "Avg basket vs weekly: ".($basketDelta>=0?'+':'').$basketDelta.'%'
      ]
    );

    $recs[] = rec(
      'High',
      'Service Recovery Offers at Peak Hours',
      'Combine a small checkout discount with queue-busting at peak times to prevent further churn driven by wait times.',
      '+5–10% retention among impatient users',
      '1 week',
      'Medium',
      [
        "Shift imbalance: {$shiftImbalance}%",
        "Peak shift receipts (M/S/G): {$m_cnt}/{$s_cnt}/{$g_cnt}"
      ]
    );
  } elseif ($riskPct >= 34) {
    $recs[] = rec(
      'Medium',
      'Loyalty Points Booster on Off-Peak',
      'Boost points (x2–x3) during slow hours to redistribute traffic and reinforce habit without margin-heavy discounts.',
      '+6–10% visits (off-peak)',
      '1 week',
      'Medium',
      [
        "Risk: {$riskPct}%",
        "Shift imbalance: {$shiftImbalance}%"
      ]
    );

    $recs[] = rec(
      'Medium',
      'Early-Warning Nudge',
      'Detect users with 20–30% drop in weekly frequency and send a “we miss you” nudge with a light incentive.',
      '+4–8% reactivation',
      '3–5 days',
      'Low',
      [
        "Weekly avg receipts: ".number_format($weeklyAvgReceipts,1),
        "Traffic trend: ".($trend>=0?'+':'').$trend.'%'
      ]
    );
  } else {
    $recs[] = rec(
      'Low',
      'A/B Test Value Bundles',
      'Bundle two high-attach items with a micro-discount. Favor combos with strong historical attachment.',
      '+2–4% basket value',
      '2 weeks',
      'Low',
      [
        "Avg basket: ₱".number_format($avgBasket,2),
        "Weekly avg basket: ₱".number_format($weeklyAvgBasket,2)
      ]
    );

    $recs[] = rec(
      'Low',
      'Light CRM Touchpoint',
      'Send a monthly “favorites restocked” push/email to keep low-risk users engaged without heavy promo costs.',
      'Retention hygiene',
      '1 week',
      'Low',
      []
    );
  }

  // Condition: sales and/or transactions dropping
  if ($dropSales > 8 || $dropTxn > 8) {
    $recs[] = rec(
      'High',
      'Basket-Builder Promo on Top 20 SKUs',
      'Add a limited-time “buy X, get Y at ₱Z” on frequently bought together items to arrest the slide.',
      '+8–12% sales',
      '1 week',
      'Medium',
      [
        "Sales drop: {$dropSales}%",
        "Txn drop: {$dropTxn}%"
      ]
    );
  } elseif ($dropSales > 5 || $dropTxn > 5) {
    $recs[] = rec(
      'Medium',
      'Price-Sensitive Offer for Lapsed 7-day Users',
      'Issue a low-margin, high-perceived value coupon to customers inactive for 7–10 days.',
      '+5–8% reactivation',
      '5 days',
      'Low',
      [
        "Sales drop: {$dropSales}%",
        "Txn drop: {$dropTxn}%"
      ]
    );
  }

  // Condition: very busy store → staffing & ops
  if ($traffic >= 300 || $shiftImbalance >= 35) {
    $recs[] = rec(
      'Medium',
      'Peak-Hour Staffing & Express Lane',
      'Add one floater at the highest-load shift and create an express lane for ≤2 items. This reduces queue abandonment (a churn driver).',
      '+15–25% service speed',
      '1 week',
      'Medium',
      [
        "Traffic (today): {$traffic}",
        "Shift imbalance: {$shiftImbalance}%"
      ]
    );
  }

  // Condition: basket under weekly baseline → attach/cross-sell
  if ($weeklyAvgBasket > 0 && $basketDelta <= -5) {
    $recs[] = rec(
      'Medium',
      'Attach-Rate Boosters at POS',
      'At checkout, auto-suggest a ₱-friendly add-on with historically high attach to the current item.',
      '+5–9% basket',
      '1–2 weeks',
      'Low',
      [
        "Avg basket: ₱".number_format($avgBasket,2),
        "Weekly avg: ₱".number_format($weeklyAvgBasket,2),
        "Delta: ".($basketDelta)."%"
      ]
    );
  }

  // Ensure uniqueness and keep top 6 by priority weight
  $seen = [];
  $unique = [];
  foreach ($recs as $r) {
    $key = strtolower($r['title']);
    if (!isset($seen[$key])) {
      $seen[$key] = true;
      $unique[] = $r;
    }
  }

  // priority sort: High > Medium > Low
  $w = ['high'=>3,'medium'=>2,'low'=>1];
  usort($unique, function($a,$b) use ($w){
    $pa = $w[strtolower($a['priority'] ?? 'low')] ?? 1;
    $pb = $w[strtolower($b['priority'] ?? 'low')] ?? 1;
    return $pb <=> $pa;
  });

  // cap to 6
  $unique = array_slice($unique, 0, 6);

  json_ok([
    'recommendations' => $unique,
    'context' => [
      'risk_percentage' => $riskPct,
      'risk_level'      => $riskLvl,
      'traffic_trend'   => $trend,
      'shift_imbalance' => $shiftImbalance,
      'avg_basket'      => round($avgBasket,2),
      'weekly_avg_basket'=> round($weeklyAvgBasket,2),
      'basket_delta_pct'=> $basketDelta,
      'sales_drop_pct'  => $dropSales,
      'txn_drop_pct'    => $dropTxn,
      'traffic_today'   => $traffic
    ]
  ]);
} catch (Throwable $e) {
  json_error('Server error', 500, ['detail'=>$e->getMessage()]);
}
