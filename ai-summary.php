<?php
// ai-summary.php — OpenAI Chart Summary Generator
// Reads API key from .env file and generates chart summaries

date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // --- AUTHENTICATION CHECK ---
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status'=>'error','error'=>'unauthorized','message'=>'User not authenticated']);
        exit;
    }
    
    // --- READ OPENAI API KEY FROM .env ---
    $envPath = __DIR__ . '/.env';
    if (!file_exists($envPath)) {
        throw new Exception('.env file not found');
    }
    
    $envContent = file_get_contents($envPath);
    $envLines = explode("\n", $envContent);
    $apiKey = null;
    
    foreach ($envLines as $line) {
        $line = trim($line);
        if (strpos($line, 'OPENAI_API_KEY=') === 0) {
            $apiKey = trim(str_replace('OPENAI_API_KEY=', '', $line));
            // Remove quotes if present
            $apiKey = trim($apiKey, '"\'');
            break;
        }
    }
    
    if (empty($apiKey)) {
        throw new Exception('OPENAI_API_KEY not found in .env file');
    }
    
    // --- GET REQUEST DATA ---
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['status'=>'error','error'=>'bad_request','message'=>'Invalid JSON data']);
        exit;
    }
    
    $chartType = $data['chartType'] ?? '';
    $chartData = $data['chartData'] ?? [];
    $metrics = $data['metrics'] ?? [];
    
    if (empty($chartType) || empty($chartData)) {
        http_response_code(400);
        echo json_encode(['status'=>'error','error'=>'bad_request','message'=>'Missing required fields']);
        exit;
    }
    
    // --- PREPARE PROMPT BASED ON CHART TYPE ---
    $prompt = generatePrompt($chartType, $chartData, $metrics);
    
    // --- CALL OPENAI API ---
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    
    $requestBody = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a business analytics expert specializing in customer retention and churn analysis. Provide concise, actionable insights in 2-3 sentences.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 150
    ];
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestBody),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Curl error: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'OpenAI API error';
        throw new Exception('OpenAI API returned status ' . $httpCode . ': ' . $errorMsg);
    }
    
    $result = json_decode($response, true);
    
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response from OpenAI API');
    }
    
    $summary = trim($result['choices'][0]['message']['content']);
    
    // --- RETURN SUCCESS ---
    echo json_encode([
        'status' => 'success',
        'summary' => $summary,
        'chartType' => $chartType,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    error_log('AI Summary Error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'error' => 'server_error',
        'message' => $e->getMessage()
    ]);
}

// --- HELPER FUNCTION TO GENERATE PROMPTS ---
function generatePrompt($chartType, $chartData, $metrics) {
    $labels = $chartData['labels'] ?? [];
    $datasets = $chartData['datasets'] ?? [];
    
    switch ($chartType) {
        case 'retention':
            $retentionData = $datasets[0]['data'] ?? [];
            $avgRetention = count($retentionData) > 0 ? array_sum($retentionData) / count($retentionData) : 0;
            $trend = getTrend($retentionData);
            
            return "Analyze this customer retention chart data:\n" .
                   "- Period: " . count($labels) . " days\n" .
                   "- Average Retention Rate: " . round($avgRetention, 1) . "%\n" .
                   "- Trend: $trend\n" .
                   "- Current Retention: " . ($metrics['currentRetention'] ?? 'N/A') . "\n" .
                   "- Churn Rate: " . ($metrics['churnRate'] ?? 'N/A') . "\n\n" .
                   "Provide a brief analysis highlighting key insights and any concerning patterns.";
        
        case 'behavior':
            $transactionData = $datasets[0]['data'] ?? [];
            $avgTransactions = count($transactionData) > 0 ? array_sum($transactionData) / count($transactionData) : 0;
            $trend = getTrend($transactionData);
            
            return "Analyze this customer transaction behavior chart:\n" .
                   "- Period: " . count($labels) . " days\n" .
                   "- Average Daily Transactions: " . round($avgTransactions, 0) . "\n" .
                   "- Trend: $trend\n" .
                   "- Avg Transaction Frequency: " . ($metrics['avgFrequency'] ?? 'N/A') . "\n" .
                   "- Avg Transaction Value: " . ($metrics['avgValue'] ?? 'N/A') . "\n\n" .
                   "Provide insights on customer engagement patterns and behavior trends.";
        
        case 'revenue':
            $revenueData = $datasets[0]['data'] ?? [];
            $totalRevenue = array_sum($revenueData);
            $avgRevenue = count($revenueData) > 0 ? $totalRevenue / count($revenueData) : 0;
            $trend = getTrend($revenueData);
            
            return "Analyze this revenue impact chart:\n" .
                   "- Period: " . count($labels) . " days\n" .
                   "- Total Revenue: ₱" . number_format($totalRevenue, 0) . "\n" .
                   "- Average Daily Revenue: ₱" . number_format($avgRevenue, 0) . "\n" .
                   "- Trend: $trend\n\n" .
                   "Provide insights on revenue patterns and potential financial impact.";
        
        case 'trends':
            $riskData = $datasets[0]['data'] ?? [];
            $avgRisk = count($riskData) > 0 ? array_sum($riskData) / count($riskData) : 0;
            $maxRisk = count($riskData) > 0 ? max($riskData) : 0;
            $trend = getTrend($riskData);
            
            return "Analyze this churn risk trend chart:\n" .
                   "- Period: " . count($labels) . " days\n" .
                   "- Average Risk Score: " . round($avgRisk, 1) . "%\n" .
                   "- Peak Risk Score: " . round($maxRisk, 1) . "%\n" .
                   "- Trend: $trend\n\n" .
                   "Provide insights on churn risk patterns and recommend actions if risk is elevated.";
        
        default:
            return "Analyze this chart and provide key insights in 2-3 sentences.";
    }
}

function getTrend($data) {
    if (count($data) < 2) return 'insufficient data';
    
    $firstHalf = array_slice($data, 0, (int)(count($data) / 2));
    $secondHalf = array_slice($data, (int)(count($data) / 2));
    
    $firstAvg = array_sum($firstHalf) / count($firstHalf);
    $secondAvg = array_sum($secondHalf) / count($secondHalf);
    
    $change = (($secondAvg - $firstAvg) / $firstAvg) * 100;
    
    if ($change > 5) return 'increasing';
    if ($change < -5) return 'decreasing';
    return 'stable';
}
?>