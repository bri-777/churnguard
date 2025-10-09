<?php
/* api/reports/strategic_recommendation.php */
require __DIR__ . '/../_bootstrap.php';
$uid = require_login();

// Load environment variables
function loadEnv($file = __DIR__ . '/../../.env') {
  if (!file_exists($file)) return;
  $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    list($key, $val) = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val);
  }
}
loadEnv();

/**
 * Call OpenAI API for intelligent store-wide recommendations
 */
function getAIRecommendations($context) {
  $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
  if (!$apiKey) {
    throw new Exception('OpenAI API key not configured');
  }

  $model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini';

  // Calculate additional insights
  $peakShift = 'morning';
  $peakSales = $context['morning_sales'];
  if ($context['swing_sales'] > $peakSales) {
    $peakShift = 'swing';
    $peakSales = $context['swing_sales'];
  }
  if ($context['graveyard_sales'] > $peakSales) {
    $peakShift = 'graveyard';
    $peakSales = $context['graveyard_sales'];
  }

  $avgReceiptValue = $context['avg_basket'];
  $trafficHealth = $context['traffic_trend'] >= 0 ? 'increasing' : 'declining';
  $salesMomentum = $context['sales_drop_pct'] <= 0 ? 'growing' : 'declining';

  $prompt = <<<PROMPT
You are an expert retail operations consultant specializing in Philippine convenience stores (sari-sari stores, 7-Eleven style shops). Analyze AGGREGATE store performance data and provide strategic recommendations.

STORE PERFORMANCE OVERVIEW:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Overall Health:
- Churn Risk: {$context['risk_level']} ({$context['risk_percentage']}%)
- Store Traffic: {$trafficHealth} ({$context['traffic_trend']}% vs yesterday)
- Sales Momentum: {$salesMomentum} ({$context['sales_drop_pct']}% change)

Daily Aggregates:
- Total Sales: ₱{$context['sales_volume']}
- Total Transactions: {$context['receipt_count']} receipts
- Customer Footfall: {$context['customer_traffic']} people
- Average Basket: ₱{$avgReceiptValue}
- Weekly Avg Basket: ₱{$context['weekly_avg_basket']} (Δ {$context['basket_delta_pct']}%)

Shift Performance (Peak: {$peakShift}):
- Morning (6AM-2PM): {$context['morning_receipts']} receipts = ₱{$context['morning_sales']}
- Swing (2PM-10PM): {$context['swing_receipts']} receipts = ₱{$context['swing_sales']}
- Graveyard (10PM-6AM): {$context['graveyard_receipts']} receipts = ₱{$context['graveyard_sales']}
- Shift Imbalance: {$context['shift_imbalance']}%

Trends:
- Transaction Volume: {$context['txn_drop_pct']}% change
- Sales Drop: {$context['sales_drop_pct']}%
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

YOUR TASK:
Generate 5 strategic, store-wide recommendations focusing on:

✓ STORE OPERATIONS (staffing, layout, checkout efficiency)
✓ MERCHANDISING (product placement, displays, signage)
✓ PROMOTIONS & PRICING (discounts, bundles, time-based offers)
✓ INVENTORY & PRODUCT MIX (what to stock more/less)
✓ CUSTOMER EXPERIENCE (ambiance, service quality, convenience)
✓ TRAFFIC OPTIMIZATION (attracting more customers during slow hours)

✗ DO NOT recommend individual customer targeting
✗ DO NOT mention customer databases or CRM
✗ DO NOT suggest personalized marketing to specific people

CONTEXT-SPECIFIC GUIDANCE:
- High Risk (>67%): Focus on URGENT operational fixes and aggressive promotions
- Medium Risk (34-67%): Balance prevention (service improvements) with growth tactics
- Low Risk (<34%): Focus on GROWTH and optimization strategies

Philippines Market Factors:
- Cash is still king (mobile wallets growing)
- Price sensitivity is high
- Payday cycles affect spending (15th & 30th)
- Sari-sari stores are main competition
- Load (mobile prepaid) is essential
- Convenience trumps variety
- Peak hours: before work (7-9AM), lunch (12-1PM), after work (5-7PM)

Output EXACTLY 5 recommendations in this JSON format:
[
  {
    "priority": "High|Medium|Low",
    "title": "Short actionable title (max 50 chars)",
    "description": "Detailed implementation steps (3-4 sentences). Be specific about WHAT to do, WHERE in store, WHEN to implement, and HOW it helps overall store performance.",
    "impact": "Quantified expected outcome (e.g., '+15-20% traffic during off-peak', '₱5K-8K daily revenue increase')",
    "eta": "Implementation timeline (e.g., '2-3 days', '1 week')",
    "cost": "Low|Medium|High",
    "effectiveness": 70-95,
    "reasoning": "1-2 sentence explanation linking this to the specific data patterns shown above",
    "category": "Operations|Merchandising|Promotions|Inventory|Experience|Traffic"
  }
]

QUALITY REQUIREMENTS:
1. Each recommendation must be independently actionable
2. Focus on aggregate patterns (traffic, sales, shifts) NOT individual customers
3. Be specific to Philippines convenience store context
4. Provide concrete numbers and timeframes
5. Explain the data-driven reasoning
6. Ensure recommendations address the risk level appropriately

Return ONLY valid JSON array. No markdown, no explanations outside JSON.
PROMPT;

  $payload = [
    'model' => $model,
    'messages' => [
      ['role' => 'system', 'content' => 'You are a Philippine retail operations expert. Provide strategic store-wide recommendations based on aggregate data. Never suggest individual customer targeting. Respond only with valid JSON arrays.'],
      ['role' => 'user', 'content' => $prompt]
    ],
    'temperature' => 0.8,
    'max_tokens' => 2500
  ];

  $ch = curl_init('https://api.openai.com/v1/chat/completions');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    throw new Exception("OpenAI API error: HTTP $httpCode");
  }

  $data = json_decode($response, true);
  if (!isset($data['choices'][0]['message']['content'])) {
    throw new Exception('Invalid OpenAI response format');
  }

  $content = trim($data['choices'][0]['message']['content']);
  $content = preg_replace('/^```json\s*|\s*```$/m', '', $content);
  
  $recommendations = json_decode($content, true);
  if (!is_array($recommendations)) {
    throw new Exception('Failed to parse AI recommendations');
  }

  return $recommendations;
}

function clamp_pct($v): float {
  $n = (float)$v;
  if ($n <= 1.0) $n *= 100.0;
  if ($n < 0) $n = 0;
  if ($n > 100) $n = 100;
  return round($n, 2);
}

function level_from_pct(float $p): string {
  if ($p >= 67) return 'High';
  if ($p >= 34) return 'Medium';
  return 'Low';
}

/**
 * Improved fallback recommendations (store-wide focus)
 */
function rec($priority, $title, $desc, $impact, $eta, $cost, $effectiveness, $reasoning, $category) {
  return [
    'priority' => $priority,
    'title' => $title,
    'description' => $desc,
    'impact' => $impact,
    'eta' => $eta,
    'cost' => $cost,
    'effectiveness' => $effectiveness,
    'reasoning' => $reasoning,
    'category' => $category,
    'metrics' => []
  ];
}

try {
  /* ---- Gather latest context ---- */
  $qd = $pdo->prepare("SELECT * FROM churn_data WHERE user_id=? ORDER BY date DESC LIMIT 1");
  $qd->execute([$uid]);
  $cd = $qd->fetch(PDO::FETCH_ASSOC) ?: [];

  $qdp = $pdo->prepare("SELECT * FROM churn_data WHERE user_id=? ORDER BY date DESC LIMIT 2");
  $qdp->execute([$uid]);
  $two = $qdp->fetchAll(PDO::FETCH_ASSOC);
  $yday = $two[1] ?? null;

  $qp = $pdo->prepare("SELECT risk_percentage, risk_level FROM churn_predictions WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
  $qp->execute([$uid]);
  $pr = $qp->fetch(PDO::FETCH_ASSOC) ?: ['risk_percentage'=>0,'risk_level'=>null];

  $riskPct = clamp_pct($pr['risk_percentage'] ?? 0);
  $riskLvl = $pr['risk_level'] ? ucfirst(strtolower($pr['risk_level'])) : level_from_pct($riskPct);

  $sales = (float)($cd['sales_volume'] ?? 0);
  $receipts = (int)($cd['receipt_count'] ?? 0);
  $traffic = (int)($cd['customer_traffic'] ?? 0);
  $avgBasket = $receipts > 0 ? ($sales / max(1,$receipts)) : 0.0;

  $dropSales = (float)($cd['sales_drop_percentage'] ?? 0);
  $dropTxn = (float)($cd['transaction_drop_percentage'] ?? 0);

  $m_cnt = (int)($cd['morning_receipt_count'] ?? 0);
  $m_sales = (float)($cd['morning_sales_volume'] ?? 0);
  $s_cnt = (int)($cd['swing_receipt_count'] ?? 0);
  $s_sales = (float)($cd['swing_sales_volume'] ?? 0);
  $g_cnt = (int)($cd['graveyard_receipt_count'] ?? 0);
  $g_sales = (float)($cd['graveyard_sales_volume'] ?? 0);

  $weeklyAvgSales = (float)($cd['weekly_average_sales'] ?? 0);
  $weeklyAvgReceipts = (float)($cd['weekly_average_receipts'] ?? 0);
  $weeklyAvgBasket = $weeklyAvgReceipts > 0 ? ($weeklyAvgSales / max(1,$weeklyAvgReceipts)) : 0.0;

  $trend = 0.0;
  if ($yday && (int)($yday['customer_traffic'] ?? 0) > 0) {
    $trend = (($traffic - (int)$yday['customer_traffic']) / max(1,(int)$yday['customer_traffic'])) * 100.0;
  }
  $trend = round($trend, 2);

  $maxShift = max($m_cnt, $s_cnt, $g_cnt);
  $minShift = min($m_cnt, $s_cnt, $g_cnt);
  $shiftImbalance = ($maxShift > 0) ? round((($maxShift - $minShift) / max(1,$maxShift)) * 100.0, 2) : 0.0;

  $basketDelta = $weeklyAvgBasket > 0 ? round((($avgBasket - $weeklyAvgBasket) / $weeklyAvgBasket) * 100.0, 2) : 0.0;

  /* ---- Build context for AI ---- */
  $aiContext = [
    'risk_percentage' => $riskPct,
    'risk_level' => $riskLvl,
    'sales_volume' => $sales,
    'receipt_count' => $receipts,
    'customer_traffic' => $traffic,
    'avg_basket' => round($avgBasket, 2),
    'weekly_avg_basket' => round($weeklyAvgBasket, 2),
    'basket_delta_pct' => $basketDelta,
    'sales_drop_pct' => $dropSales,
    'txn_drop_pct' => $dropTxn,
    'traffic_trend' => $trend,
    'shift_imbalance' => $shiftImbalance,
    'morning_receipts' => $m_cnt,
    'morning_sales' => $m_sales,
    'swing_receipts' => $s_cnt,
    'swing_sales' => $s_sales,
    'graveyard_receipts' => $g_cnt,
    'graveyard_sales' => $g_sales
  ];

  /* ---- Get AI Recommendations ---- */
  $recommendations = [];
  $aiSuccess = false;
  
  try {
    $recommendations = getAIRecommendations($aiContext);
    $aiSuccess = true;
    
    // Enrich with metrics
    foreach ($recommendations as &$rec) {
      $rec['metrics'] = [
        "Risk: {$riskPct}%",
        "Category: {$rec['category']}",
        "Effectiveness: {$rec['effectiveness']}%",
        $rec['impact'] ?? '',
        $rec['cost'] ?? ''
      ];
      $rec['ai_generated'] = true;
    }
  } catch (Exception $e) {
    // Improved fallback recommendations (store-wide focus)
    error_log("AI recommendation failed: " . $e->getMessage());
    
    if ($riskPct >= 67) {
      // HIGH RISK - Urgent operational interventions
      $recommendations[] = rec(
        'High', 
        'Flash Weekend Sale - High-Margin Items',
        'Launch a 3-day flash promotion on your top 20 best-selling items with 10-15% discount. Display prominently at entrance and checkout counter. Use bright signage "WEEKEND SALE!" to catch attention of passing foot traffic. Train staff to mention promo to every customer.',
        '₱8K-12K weekend revenue boost, +25-35% footfall',
        '2 days',
        'Low',
        88,
        'High churn risk with declining traffic requires immediate aggressive promotion to reverse momentum',
        'Promotions'
      );
      
      $recommendations[] = rec(
        'High',
        'Express Checkout Lane + Queue Management',
        'Designate one counter as express lane (5 items or less) during peak hours identified in your shift data. Add queue markers on floor and "Next Customer" signage. This reduces perceived wait time which is a major churn driver.',
        '+20-30% checkout speed, -15% cart abandonment',
        '3 days',
        'Low',
        85,
        'Shift imbalance of '.$shiftImbalance.'% indicates capacity bottlenecks causing customer frustration',
        'Operations'
      );
      
      $recommendations[] = rec(
        'High',
        'Extend High-Value Hours Staffing',
        'Add one extra staff member during your peak shift ('.$maxShift.' receipts). Position them to help with restocking, bagging, and customer queries to speed up overall flow. Peak efficiency = more customers served = less churn.',
        '₱5K-8K daily uplift, +15-20% transaction capacity',
        '5 days',
        'Medium',
        82,
        'Peak shift congestion is limiting revenue potential during high-demand windows',
        'Operations'
      );
    } elseif ($riskPct >= 34) {
      // MEDIUM RISK - Preventive measures
      $recommendations[] = rec(
        'Medium',
        'Bundle Builder Program (Meal Deals)',
        'Create 3 fixed-price bundles: Breakfast (₱65), Lunch (₱85), Snack (₱50). Place bundle cards near related products. Bundle high-margin items with slower movers. Update bundles monthly based on what sells.',
        '+8-12% basket size, ₱4K-6K daily increase',
        '1 week',
        'Low',
        78,
        'Average basket ₱'.$avgBasket.' is '.$basketDelta.'% below weekly average - bundles encourage larger purchases',
        'Merchandising'
      );
      
      $recommendations[] = rec(
        'Medium',
        'Happy Hour Pricing (Off-Peak Traffic)',
        'Introduce 2-4PM "Happy Hour" with ₱5-10 discount on drinks/snacks. Promotes during slow afternoon period to redistribute traffic. Advertise with window posters and social media.',
        '+15-25% off-peak traffic, ₱2K-4K new revenue',
        '5 days',
        'Low',
        75,
        'Traffic shows imbalance - need to attract customers during traditionally slow hours',
        'Promotions'
      );
    } else {
      // LOW RISK - Growth and optimization
      $recommendations[] = rec(
        'Low',
        'Premium Product Line Introduction',
        'Add 10-15 premium items (imported snacks, specialty drinks, organic options) at 20-30% higher margins. Target growing middle-class segment. Place at eye level on main aisle.',
        '+₱3K-5K daily, +5-8% margin improvement',
        '2 weeks',
        'Medium',
        72,
        'Low churn risk allows experimentation with higher-margin offerings',
        'Inventory'
      );
      
      $recommendations[] = rec(
        'Low',
        'Store Ambiance Upgrade',
        'Improve lighting (LED), add background music, ensure AC works well, keep floors spotless. Small touches that make customers stay longer = higher basket value. Schedule deep clean weekly.',
        '+3-5% dwell time, +2-4% basket size',
        '1 week',
        'Low',
        70,
        'Stable metrics allow focus on customer experience enhancements for gradual growth',
        'Experience'
      );
    }
    
    // Universal recommendation based on data patterns
    if ($basketDelta < -5) {
      $recommendations[] = rec(
        'Medium',
        'Checkout Counter Impulse Display Refresh',
        'Rotate impulse items at checkout weekly: Week 1: Candy/gum, Week 2: Small beverages, Week 3: Load cards, Week 4: Batteries/lighters. Strategic placement drives last-minute additions to basket.',
        '+₱2K-4K daily from impulse buys',
        '3 days',
        'Low',
        80,
        'Declining basket size suggests customers buying less per visit - impulse placement recovers margin',
        'Merchandising'
      );
    }
    
    if ($shiftImbalance > 30) {
      $recommendations[] = rec(
        'Medium',
        'Shift-Based Pricing Strategy',
        'Test price optimization by shift: Morning (premium pricing on breakfast), Swing (competitive on basics), Graveyard (convenience premium). Maximize revenue per traffic pattern.',
        '+₱3K-6K weekly revenue optimization',
        '1 week',
        'Low',
        76,
        'High shift imbalance ('.$shiftImbalance.'%) shows different customer behaviors by time - pricing should reflect this',
        'Promotions'
      );
    }
    
    // Add metrics to fallback recommendations
    foreach ($recommendations as &$rec) {
      $rec['metrics'] = [
        "Risk: {$riskPct}%",
        "Category: {$rec['category']}",
        "Effectiveness: {$rec['effectiveness']}%",
        $rec['impact'] ?? '',
        $rec['cost'] ?? ''
      ];
      $rec['ai_generated'] = false;
    }
  }

  // Ensure we have exactly 5 recommendations
  $recommendations = array_slice($recommendations, 0, 5);
  
  // If we have less than 5, add general best practices
  while (count($recommendations) < 5) {
    $recommendations[] = rec(
      'Low',
      'Daily Sales Dashboard Review',
      'Spend 10 minutes each morning reviewing yesterday\'s performance. Look for patterns in shifts, products, and traffic. Data-driven decisions consistently beat gut feelings.',
      'Better decision quality, trend spotting',
      'Daily habit',
      'Low',
      70,
      'Consistent monitoring enables proactive rather than reactive management',
      'Operations'
    );
  }

  json_ok([
    'recommendations' => $recommendations,
    'ai_powered' => $aiSuccess,
    'context' => [
      'risk_percentage' => $riskPct,
      'risk_level' => $riskLvl,
      'traffic_trend' => $trend,
      'shift_imbalance' => $shiftImbalance,
      'avg_basket' => round($avgBasket, 2),
      'weekly_avg_basket' => round($weeklyAvgBasket, 2),
      'basket_delta_pct' => $basketDelta,
      'sales_drop_pct' => $dropSales,
      'txn_drop_pct' => $dropTxn,
      'traffic_today' => $traffic
    ]
  ]);
} catch (Throwable $e) {
  json_error('Server error', 500, ['detail' => $e->getMessage()]);
}