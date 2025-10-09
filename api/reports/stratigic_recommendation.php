<?php
/* api/reports/strategic_recommendation.php - AI ONLY (No Fallback) */
require __DIR__ . '/../_bootstrap.php';
$uid = require_login();


function loadEnv($file = __DIR__ . '/../../.env') {
  if (!file_exists($file)) {
    error_log("❌ ERROR: .env file not found at: $file");
    throw new Exception('.env file not found');
  }
  $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0) continue;
    if (strpos($line, '=') === false) continue;
    list($key, $val) = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($val);
  }
  error_log("✅ .env file loaded successfully");
}

loadEnv();

/**
 * Call OpenAI API for intelligent store-wide recommendations
 */
function getAIRecommendations($context) {
  error_log("=== AI RECOMMENDATION REQUEST START ===");
  
  $apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
  if (empty($apiKey)) {
    error_log("❌ CRITICAL: OPENAI_API_KEY is empty or not set in .env");
    throw new Exception('OpenAI API key not configured in .env file');
  }
  
  error_log("✅ API Key found: " . substr($apiKey, 0, 10) . "..." . substr($apiKey, -4));
  
  $model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini';
  error_log("📊 Using model: $model");
  error_log("📈 Risk Level: {$context['risk_level']} ({$context['risk_percentage']}%)");

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

  error_log("🚀 Sending request to OpenAI API...");
  
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

  $startTime = microtime(true);
  $response = curl_exec($ch);
  $endTime = microtime(true);
  $responseTime = round(($endTime - $startTime) * 1000, 2);
  
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  error_log("⏱️  Response received in {$responseTime}ms");
  error_log("📡 HTTP Status Code: $httpCode");

  if (!empty($curlError)) {
    error_log("❌ CURL ERROR: $curlError");
    throw new Exception("Connection error: $curlError");
  }

  if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
    
    error_log("❌ OpenAI API Error (HTTP $httpCode): $errorMsg");
    
    // Specific error messages for common issues
    if ($httpCode === 401) {
      throw new Exception("Invalid API key. Please check your .env file and get a new key from https://platform.openai.com/api-keys");
    } elseif ($httpCode === 429) {
      throw new Exception("Rate limit exceeded or no credits. Add billing at https://platform.openai.com/account/billing");
    } elseif ($httpCode === 400) {
      throw new Exception("Bad request: $errorMsg");
    } else {
      throw new Exception("OpenAI API error (HTTP $httpCode): $errorMsg");
    }
  }

  $data = json_decode($response, true);
  if (!isset($data['choices'][0]['message']['content'])) {
    error_log("❌ Invalid response format from OpenAI");
    error_log("Response: " . substr($response, 0, 500));
    throw new Exception('Invalid OpenAI response format');
  }

  $content = trim($data['choices'][0]['message']['content']);
  
  // Log token usage
  if (isset($data['usage'])) {
    $tokens = $data['usage']['total_tokens'];
    $cost = ($data['usage']['prompt_tokens'] / 1000000 * 0.15) + 
            ($data['usage']['completion_tokens'] / 1000000 * 0.60);
    error_log("💰 Tokens used: {$tokens}, Cost: $" . number_format($cost, 6));
  }
  
  // Remove markdown code blocks if present
  $content = preg_replace('/^```json\s*|\s*```$/m', '', $content);
  
  $recommendations = json_decode($content, true);
  if (!is_array($recommendations)) {
    error_log("❌ Failed to parse AI response as JSON");
    error_log("AI Response: " . substr($content, 0, 500));
    throw new Exception('Failed to parse AI recommendations - invalid JSON format');
  }

  if (count($recommendations) === 0) {
    error_log("❌ AI returned empty recommendations array");
    throw new Exception('AI returned no recommendations');
  }

  error_log("✅ SUCCESS: AI generated " . count($recommendations) . " recommendations");
  error_log("=== AI RECOMMENDATION REQUEST END ===");

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

try {
  error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
  error_log("Starting recommendation generation for user: $uid");
  
  /* ---- Gather latest context ---- */
  $qd = $pdo->prepare("SELECT * FROM churn_data WHERE user_id=? ORDER BY date DESC LIMIT 1");
  $qd->execute([$uid]);
  $cd = $qd->fetch(PDO::FETCH_ASSOC) ?: [];

  if (empty($cd)) {
    error_log("⚠️  No churn data found for user: $uid");
    json_error('No data available. Please add churn data first.', 400);
    exit;
  }

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

  /* ---- Get AI Recommendations (NO FALLBACK) ---- */
  $recommendations = getAIRecommendations($aiContext);
  
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

  // Ensure we have exactly 5 recommendations
  $recommendations = array_slice($recommendations, 0, 5);

  error_log("✅ Sending response with " . count($recommendations) . " AI recommendations");
  error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

  json_ok([
    'recommendations' => $recommendations,
    'ai_powered' => true,
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
  error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
  error_log("❌ FATAL ERROR: " . $e->getMessage());
  error_log("Stack trace: " . $e->getTraceAsString());
  error_log("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
  
  json_error('AI Recommendation Error: ' . $e->getMessage(), 500, [
    'detail' => $e->getMessage(),
    'hint' => 'Check PHP error logs for detailed information',
    'ai_powered' => false
  ]);
}