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
 * Call OpenAI API for intelligent coffee shop recommendations
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
    $peakShift = 'afternoon';
    $peakSales = $context['swing_sales'];
  }
  if ($context['graveyard_sales'] > $peakSales) {
    $peakShift = 'evening';
    $peakSales = $context['graveyard_sales'];
  }

  $avgReceiptValue = $context['avg_basket'];
  $trafficHealth = $context['traffic_trend'] >= 0 ? 'increasing' : 'declining';
  $salesMomentum = $context['sales_drop_pct'] <= 0 ? 'growing' : 'declining';

  $prompt = <<<PROMPT
You are an expert coffee shop operations consultant specializing in Philippine coffee culture and café management (independent cafés, specialty coffee shops, third-wave coffee establishments). Analyze AGGREGATE shop performance data and provide strategic recommendations to REDUCE CUSTOMER CHURN and INCREASE LOYALTY.

COFFEE SHOP PERFORMANCE OVERVIEW:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Overall Health & Churn Indicators:
- Customer Churn Risk: {$context['risk_level']} ({$context['risk_percentage']}%)
- Customer Traffic: {$trafficHealth} ({$context['traffic_trend']}% vs yesterday)
- Sales Momentum: {$salesMomentum} ({$context['sales_drop_pct']}% change)

Daily Performance Metrics:
- Total Sales: ₱{$context['sales_volume']}
- Total Orders: {$context['receipt_count']} transactions
- Customer Footfall: {$context['customer_traffic']} visitors
- Average Ticket Size: ₱{$avgReceiptValue}
- Weekly Avg Ticket: ₱{$context['weekly_avg_basket']} (Δ {$context['basket_delta_pct']}%)

Daypart Performance (Peak: {$peakShift}):
- Morning Rush (6AM-11AM): {$context['morning_receipts']} orders = ₱{$context['morning_sales']}
- Afternoon (11AM-5PM): {$context['swing_receipts']} orders = ₱{$context['swing_sales']}
- Evening (5PM-10PM): {$context['graveyard_receipts']} orders = ₱{$context['graveyard_sales']}
- Daypart Imbalance: {$context['shift_imbalance']}%

Trend Analysis:
- Transaction Volume Change: {$context['txn_drop_pct']}%
- Sales Growth/Decline: {$context['sales_drop_pct']}%
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

YOUR PRIMARY OBJECTIVE: REDUCE CUSTOMER CHURN & BUILD LOYALTY

Generate 5 strategic recommendations with DUAL FOCUS:
1. **Retain at-risk customers** (especially those contributing to churn risk)
2. **Deepen loyalty** among existing regular customers

RECOMMENDATION FOCUS AREAS:

✓ LOYALTY PROGRAMS & REWARDS (stamp cards, membership perks, surprise delights)
✓ CUSTOMER EXPERIENCE ENHANCEMENT (service speed, ambiance, personalization)
✓ MENU OPTIMIZATION (seasonal drinks, food pairings, value offerings)
✓ ENGAGEMENT & COMMUNITY (events, workshops, social media)
✓ OPERATIONAL EXCELLENCE (queue management, consistency, quality control)
✓ RETENTION PROMOTIONS (comeback offers, frequency incentives, referral programs)

✗ DO NOT recommend individual customer targeting by name
✗ DO NOT suggest complex CRM systems beyond basic loyalty tracking
✗ DO NOT propose tactics that compromise coffee quality or brand integrity

CHURN RISK RESPONSE STRATEGY:
- **High Risk (>67%)**: URGENT retention tactics - aggressive loyalty rewards, service recovery, win-back campaigns
- **Medium Risk (34-67%)**: PREVENTIVE measures - strengthen loyalty programs, enhance experience, address service gaps
- **Low Risk (<34%)**: GROWTH & DEEPENING - elevate experience, introduce premium offerings, build community

Philippine Coffee Shop Context:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Market Dynamics:
- Coffee culture is RAPIDLY GROWING (especially among millennials/Gen Z)
- Price-conscious but willing to pay for QUALITY and EXPERIENCE
- Strong competition from international chains (Starbucks, Coffee Bean) and local players (Bo's Coffee, CBTL)
- Social media influence is MASSIVE - Instagram-worthy drinks/spaces drive traffic
- Work-from-café culture is strong (students, freelancers, remote workers)

Customer Behavior Patterns:
- Morning: Quick grab-and-go, value conscious, needs caffeine fix FAST
- Midday-Afternoon: Longer stays, work/study sessions, higher spend per visit
- Evening: Social gatherings, dates, relaxation, ambiance-focused

Key Purchasing Drivers:
- WiFi quality and power outlets availability (CRITICAL for work-from-café crowd)
- Consistency in taste and service
- Comfort and ambiance (AC, music, lighting, seating)
- Value for money (not just cheap, but worth the price)
- Unique/Instagram-worthy offerings

Loyalty Factors (What Makes Customers Return):
- Friendly, familiar baristas who remember orders
- Consistent drink quality
- Comfortable "third place" atmosphere
- Rewards/recognition for regular visits
- Exclusive perks for members
- Community feeling and belonging

Peak Traffic Windows:
- 7-9AM (breakfast rush, commuters)
- 10AM-12PM (brunch crowd, freelancers settling in)
- 2-4PM (afternoon coffee break, students)
- 6-8PM (after work, social meetups)

Filipino Coffee Preferences:
- Iced drinks dominate (tropical climate)
- Sweet flavors popular (caramel, vanilla, hazelnut)
- Milk tea crossover appeal
- Local flavors gaining traction (ube, pandan, calamansi)
- Value meals with pastries/sandwiches

Output EXACTLY 5 recommendations in this JSON format:
[
  {
    "priority": "High|Medium|Low",
    "title": "Short actionable title focused on retention/loyalty (max 60 chars)",
    "description": "Detailed implementation steps (4-5 sentences). Be SPECIFIC about: WHAT loyalty/retention tactic, HOW it reduces churn, WHERE/WHEN to implement, WHAT makes it compelling for Filipino coffee drinkers, and HOW it builds long-term loyalty.",
    "impact": "Quantified expected outcome focusing on CHURN REDUCTION and LOYALTY metrics (e.g., '-15-20% churn rate within 30 days', '+25-30% repeat visit frequency', '₱8K-12K from retained customers')",
    "eta": "Implementation timeline (e.g., '3-5 days', '1 week', '2 weeks')",
    "cost": "Low|Medium|High",
    "effectiveness": 75-95,
    "reasoning": "2-3 sentence explanation linking this recommendation to the SPECIFIC churn risk data and customer behavior patterns shown above. Explain WHY this will reduce churn and increase loyalty.",
    "category": "Loyalty Program|Experience|Menu|Community|Operations|Retention"
  }
]

QUALITY REQUIREMENTS FOR RECOMMENDATIONS:
1. **Churn-focused**: Every recommendation must DIRECTLY address customer retention or loyalty building
2. **Actionable**: Shop owner can implement within the stated timeline
3. **Data-driven**: Clear connection to the metrics showing churn risk
4. **Context-appropriate**: Suited to Philippine coffee shop culture and customer expectations
5. **Loyalty-building**: Goes beyond one-time tactics to create lasting customer relationships
6. **Effectiveness scoring**: Higher scores (90+) for proven retention tactics, 80-89 for strong loyalty builders, 75-79 for supporting measures
7. **Cost-conscious**: Filipinos appreciate value - recommendations should offer strong ROI

EXAMPLES OF GOOD RECOMMENDATIONS:
✓ "Launch 'Coffee Lover's Card' - 9th drink free, special birthday treat, early access to new drinks"
✓ "Barista Recognition Training - teach staff to remember regular customers' names and usual orders"
✓ "Afternoon Happy Hour (2-4PM) - 20% off for loyalty members to drive off-peak visits"
✓ "Monthly Coffee Tasting Event - free for members, builds community and product knowledge"

EXAMPLES OF POOR RECOMMENDATIONS:
✗ "Send personalized emails to John Doe and Maria Santos" (too specific to individuals)
✗ "Install expensive enterprise CRM software" (too complex, not actionable)
✗ "Lower prices on all drinks by 50%" (unsustainable, devalues brand)
✗ "Open 5 new branches" (not addressing current churn issue)

Return ONLY valid JSON array. No markdown, no explanations outside JSON.
PROMPT;

  $payload = [
    'model' => $model,
    'messages' => [
      ['role' => 'system', 'content' => 'You are a Philippine coffee shop retention specialist. Your primary goal is to reduce customer churn and build loyalty through strategic, data-driven recommendations. Focus on aggregate customer patterns and proven retention tactics. Respond only with valid JSON arrays.'],
      ['role' => 'user', 'content' => $prompt]
    ],
    'temperature' => 0.7,
    'max_tokens' => 3000
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
 * Improved fallback recommendations (coffee shop churn reduction focus)
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
        "Churn Risk: {$riskPct}%",
        "Category: {$rec['category']}",
        "Retention Impact: {$rec['effectiveness']}%",
        $rec['impact'] ?? '',
        $rec['cost'] ?? ''
      ];
      $rec['ai_generated'] = true;
    }
  } catch (Exception $e) {
    // Improved fallback recommendations (coffee shop churn reduction focus)
    error_log("AI recommendation failed: " . $e->getMessage());
    
    if ($riskPct >= 67) {
      // HIGH RISK - Urgent retention interventions
      $recommendations[] = rec(
        'High', 
        'Launch Emergency Loyalty Rescue Program',
        'Implement immediate "We Miss You" campaign: For customers who haven\'t visited in 7+ days, offer "Welcome Back" card with free upgrade on next visit (grande → venti). Train baristas to hand these personally with warm greeting. Track redemption rates daily. Combine with digital push via SMS/social media if possible.',
        '-20-25% churn rate within 2 weeks, ₱15K-20K recovered revenue',
        '2-3 days',
        'Low',
        92,
        'High churn risk ('.$riskPct.'%) demands immediate win-back tactics. Filipino coffee drinkers value personal recognition and "sulit" (value) - free upgrade creates emotional connection while being cost-effective.',
        'Retention'
      );
      
      $recommendations[] = rec(
        'High',
        'Express Queue + Order-Ahead System',
        'Create dedicated express lane for regulars during peak hours. Start simple: Regular customers show loyalty card, skip to front during morning rush (7-9AM). Phase 2: Add mobile order-ahead via Viber/Messenger. Reduces wait anxiety which is #1 churn driver for morning customers.',
        '-15-20% morning churn, +25-30% repeat frequency',
        '3-5 days',
        'Low',
        88,
        'Daypart imbalance of '.$shiftImbalance.'% shows capacity issues. Filipino professionals hate wasting time - fast service for regulars builds fierce loyalty and word-of-mouth.',
        'Operations'
      );
      
      $recommendations[] = rec(
        'High',
        'Barista Memory Training & Regular Recognition',
        'Institute "Know Your Regulars" program: Baristas memorize top 20 regular customers\' names and usual orders within 1 week. Create quick reference cards with photos (with permission). Greet by name, have order ready before asking. Small gestures = massive loyalty impact.',
        '-18-22% churn among regulars, +₱8K-12K weekly retention',
        '5-7 days',
        'Low',
        90,
        'Declining traffic ('.$trend.'%) suggests relationship breakdown. Filipinos crave "suki" relationships - being remembered creates emotional bond that transcends price competition.',
        'Experience'
      );
    } elseif ($riskPct >= 34) {
      // MEDIUM RISK - Preventive loyalty building
      $recommendations[] = rec(
        'Medium',
        'Tiered Loyalty Program with Monthly Perks',
        'Launch 3-tier Coffee Club: Bronze (5 visits/month) gets 10% off, Silver (10 visits) gets free pastry weekly + 15% off, Gold (15 visits) gets one free drink + 20% off + exclusive new drink previews. Make tiers visible via card stamps or app. Reset monthly to drive frequency.',
        '+30-40% visit frequency, -12-15% churn rate, ₱10K-15K monthly lift',
        '1 week',
        'Medium',
        85,
        'Medium churn risk with '.$basketDelta.'% basket change indicates loyalty erosion. Tiered system taps into Filipino "goal-oriented" mindset and status recognition while driving consistent visits.',
        'Loyalty Program'
      );
      
      $recommendations[] = rec(
        'Medium',
        'Afternoon Work-From-Café Package',
        'Create 2-4PM "Productivity Bundle": ₱199 for any drink + pastry + 3-hour WiFi guarantee + reserved power outlet. Target freelancers, students, remote workers. Promote as "your afternoon office". Limit to 15 seats to maintain availability. Require loyalty card sign-up.',
        '+20-25% afternoon traffic, -10-15% off-peak churn, ₱6K-9K daily',
        '5-7 days',
        'Low',
        82,
        'Afternoon utilization gap shows untapped potential. Work-from-café culture is huge in PH - creating reliable "third space" builds daily habit and recurring revenue.',
        'Menu'
      );
      
      $recommendations[] = rec(
        'Medium',
        'Monthly Coffee Appreciation Event for Members',
        'Host monthly "Coffee Circle" (last Saturday, 4-6PM): Free for loyalty members, ₱150 for non-members. Feature latte art workshop, new drink sampling, meet-the-roaster session. Limit to 25 people. Create Instagram moments. Build community beyond transactions.',
        '+15-20% member engagement, -8-10% churn, 40-50 new sign-ups/event',
        '2 weeks',
        'Medium',
        80,
        'Building community reduces churn by creating social bonds. Filipinos love events, learning, and sharing on social media - this hits all three while deepening brand connection.',
        'Community'
      );
    } else {
      // LOW RISK - Loyalty deepening and premiumization
      $recommendations[] = rec(
        'Low',
        'VIP Inner Circle Premium Membership',
        'Launch exclusive ₱499/month "Kapihan Insider" subscription: Unlimited 15% discount, one free specialty drink weekly, priority seating, exclusive seasonal drinks 1 week early, birthday gift, plus-one guest privileges. Position as premium status symbol. Limit to 100 members.',
        '+₱20K-30K monthly recurring revenue, 85-90% retention rate',
        '2 weeks',
        'Medium',
        78,
        'Low churn risk allows premium tier introduction. Filipino aspirational consumers will pay for elevated status and exclusive access - subscription model creates predictable revenue and deepest loyalty.',
        'Loyalty Program'
      );
      
      $recommendations[] = rec(
        'Low',
        'Seasonal Limited Edition Series (Monthly)',
        'Launch monthly exclusive drink featuring Filipino ingredients: Month 1: Ube Cloud Latte, Month 2: Calamansi Honey Cold Brew, Month 3: Pandan Cream Frappe. Create FOMO with "available this month only" messaging. Loyalty members get 20% off + first access. Make drinks Instagram-worthy.',
        '+₱8K-12K monthly from specialty sales, +5-8% traffic increase',
        '1 week',
        'Medium',
        75,
        'Low churn creates opportunity for innovation. Seasonal exclusivity drives repeat visits ("got to try before it\'s gone") while Filipino flavor profiles differentiate from chain competitors.',
        'Menu'
      );
      
      $recommendations[] = rec(
        'Low',
        'Ambiance & "Third Place" Experience Upgrade',
        'Invest in comfort: Add 4-6 plush lounge chairs, upgrade WiFi to 100Mbps fiber, install USB/wireless charging stations at all tables, curate Spotify playlist (lo-fi, acoustic), improve AC zones. Create designated "quiet work zone" and "social zone". Make café the preferred hangout spot.',
        '+8-12% dwell time, +₱5K-8K weekly from extended stays',
        '1-2 weeks',
        'Medium',
        77,
        'Stable metrics allow experience investment. Filipino customers value "sulit sa oras" (time well spent) - superior ambiance justifies higher prices and transforms casual visitors into daily regulars.',
        'Experience'
      );
    }
    
    // Universal recommendation based on data patterns
    if ($basketDelta < -5) {
      $recommendations[] = rec(
        'Medium',
        'Drink+Pastry Pairing Recommendations',
        'Train baristas to suggest optimal food pairings with every coffee order: "That Americano pairs perfectly with our ensaymada!" Create pairing cards at counter. Offer combo discount (₱20-30 off) when purchased together. Rotate featured pairings weekly to maintain interest.',
        '+₱4K-7K daily from upsells, +15-20% basket size recovery',
        '3-5 days',
        'Low',
        83,
        'Declining basket size ('.$basketDelta.'%) suggests missed upsell opportunities. Strategic pairing suggestions feel helpful (not pushy) and increase ticket while introducing customers to more menu items.',
        'Experience'
      );
    }
    
    if ($shiftImbalance > 30) {
      $recommendations[] = rec(
        'Medium',
        'Daypart-Specific Loyalty Multipliers',
        'Implement "Smart Rewards" system: Morning visits (6-11AM) earn 1x points, Afternoon (11AM-5PM) earn 2x points, Evening (5PM-close) earn 3x points. Incentivizes visits during slower periods while maintaining full-price revenue. Communicate via table tents and social media.',
        '+18-25% off-peak traffic, -₱3K-5K operational waste reduction',
        '1 week',
        'Low',
        81,
        'High daypart imbalance ('.$shiftImbalance.'%) shows optimization opportunity. Variable point system redistributes traffic without devaluing product while making customers feel smart for visiting off-peak.',
        'Loyalty Program'
      );
    }
    
    // Add metrics to fallback recommendations
    foreach ($recommendations as &$rec) {
      $rec['metrics'] = [
        "Churn Risk: {$riskPct}%",
        "Category: {$rec['category']}",
        "Retention Impact: {$rec['effectiveness']}%",
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
      'Daily Customer Feedback Ritual',
      'Spend 15 minutes each morning reading customer comments (Google reviews, social media, direct feedback). Identify patterns in complaints and praise. Respond to all negative reviews within 24 hours with genuine concern and solution. Track recurring issues weekly.',
      'Improved retention through responsiveness, trend identification',
      'Daily habit',
      'Low',
      72,
      'Consistent monitoring enables proactive churn prevention - addressing issues before customers leave permanently',
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