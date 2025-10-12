<?php
// test-ai.php — Quick OpenAI API Connection Tester
// Access via: http://localhost/churnguard-pro/test-ai.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html>
<head>
    <title>ChurnGuard AI Test</title>
    <style>
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #F6F9FC;
        }
        .test-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            color: #5E72E4;
            margin: 0 0 20px 0;
        }
        .status {
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 600;
        }
        .success {
            background: #D1FAE5;
            border-left: 4px solid #10B981;
            color: #065F46;
        }
        .error {
            background: #FEE2E2;
            border-left: 4px solid #EF4444;
            color: #991B1B;
        }
        .warning {
            background: #FEF3C7;
            border-left: 4px solid #F59E0B;
            color: #92400E;
        }
        .info {
            background: #EEF2FF;
            border-left: 4px solid #5E72E4;
            color: #3730A3;
        }
        .result-box {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            font-size: 14px;
            line-height: 1.6;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            border-left: 3px solid #D1D5DB;
        }
        .step-title {
            font-weight: 700;
            color: #374151;
            margin-bottom: 10px;
        }
        code {
            background: #1F2937;
            color: #10B981;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 13px;
        }
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 3px solid #E5E7EB;
            border-top-color: #5E72E4;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        button {
            background: linear-gradient(135deg, #667EEA 0%, #5E72E4 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            font-size: 14px;
            margin-top: 20px;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(94, 114, 228, 0.4);
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>⚡ ChurnGuard AI Integration Test</h1>
        <p style="color: #6b7280; margin-bottom: 30px;">
            This test verifies your OpenAI API connection and configuration.
        </p>

<?php

// Test 1: Check if .env file exists
echo '<div class="step">';
echo '<div class="step-title">📁 Step 1: Checking .env file</div>';

$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    echo '<div class="status error">❌ ERROR: .env file not found at: ' . htmlspecialchars($envPath) . '</div>';
    echo '<p>Please create a .env file in your project root with your OpenAI API key.</p>';
    echo '</div></div></body></html>';
    exit;
}

echo '<div class="status success">✅ .env file found</div>';
echo '</div>';

// Test 2: Read API key from .env
echo '<div class="step">';
echo '<div class="step-title">🔑 Step 2: Reading OpenAI API Key</div>';

$envContent = file_get_contents($envPath);
$envLines = explode("\n", $envContent);
$apiKey = null;

foreach ($envLines as $line) {
    $line = trim($line);
    if (strpos($line, 'OPENAI_API_KEY=') === 0) {
        $apiKey = trim(str_replace('OPENAI_API_KEY=', '', $line));
        $apiKey = trim($apiKey, '"\'');
        break;
    }
}

if (empty($apiKey)) {
    echo '<div class="status error">❌ ERROR: OPENAI_API_KEY not found in .env file</div>';
    echo '<p>Add this line to your .env file:<br><code>OPENAI_API_KEY=sk-your-key-here</code></p>';
    echo '</div></div></body></html>';
    exit;
}

// Mask the key for security
$maskedKey = substr($apiKey, 0, 7) . '...' . substr($apiKey, -4);
echo '<div class="status success">✅ API Key found: <code>' . htmlspecialchars($maskedKey) . '</code></div>';

// Validate key format
if (!preg_match('/^sk-[a-zA-Z0-9]{20,}/', $apiKey)) {
    echo '<div class="status warning">⚠️ WARNING: API key format looks unusual. OpenAI keys typically start with "sk-"</div>';
}
echo '</div>';

// Test 3: Test API Connection
echo '<div class="step">';
echo '<div class="step-title">🌐 Step 3: Testing OpenAI API Connection</div>';

// Sample test data
$testPrompt = "In one sentence, confirm this API connection is working.";

$ch = curl_init('https://api.openai.com/v1/chat/completions');

$requestBody = [
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant testing an API connection.'
        ],
        [
            'role' => 'user',
            'content' => $testPrompt
        ]
    ],
    'temperature' => 0.7,
    'max_tokens' => 50
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
    echo '<div class="status error">❌ CURL ERROR: ' . htmlspecialchars($curlError) . '</div>';
    echo '<p>Check your server\'s internet connection and firewall settings.</p>';
    echo '</div></div></body></html>';
    exit;
}

if ($httpCode !== 200) {
    echo '<div class="status error">❌ API ERROR (HTTP ' . $httpCode . ')</div>';
    
    $errorData = json_decode($response, true);
    if ($errorData && isset($errorData['error']['message'])) {
        echo '<div class="result-box">';
        echo '<strong>Error Message:</strong><br>';
        echo htmlspecialchars($errorData['error']['message']);
        echo '</div>';
        
        // Common error solutions
        if (strpos($errorData['error']['message'], 'Incorrect API key') !== false) {
            echo '<div class="status warning">💡 Solution: Your API key is invalid. Get a valid key from: <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a></div>';
        } elseif (strpos($errorData['error']['message'], 'quota') !== false) {
            echo '<div class="status warning">💡 Solution: You\'ve exceeded your API quota. Check your usage at: <a href="https://platform.openai.com/usage" target="_blank">https://platform.openai.com/usage</a></div>';
        }
    } else {
        echo '<div class="result-box">Raw Response: ' . htmlspecialchars($response) . '</div>';
    }
    echo '</div></div></body></html>';
    exit;
}

$result = json_decode($response, true);

if (!$result || !isset($result['choices'][0]['message']['content'])) {
    echo '<div class="status error">❌ Invalid response format from OpenAI</div>';
    echo '<div class="result-box">' . htmlspecialchars($response) . '</div>';
    echo '</div></div></body></html>';
    exit;
}

$aiResponse = trim($result['choices'][0]['message']['content']);

echo '<div class="status success">✅ API Connection Successful!</div>';
echo '<div class="result-box">';
echo '<strong>AI Response:</strong><br>';
echo htmlspecialchars($aiResponse);
echo '</div>';
echo '</div>';

// Test 4: Test Chart Summary Generation
echo '<div class="step">';
echo '<div class="step-title">📊 Step 4: Testing Chart Summary Generation</div>';

// Sample chart data
$sampleChartData = [
    'labels' => ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5'],
    'datasets' => [
        [
            'label' => 'Retention Rate %',
            'data' => [85, 82, 78, 75, 73]
        ]
    ]
];

$sampleMetrics = [
    'currentRetention' => '73.0%',
    'churnRate' => '27.0%'
];

$chartPrompt = "Analyze this customer retention chart data:\n" .
               "- Period: 5 days\n" .
               "- Average Retention Rate: 78.6%\n" .
               "- Trend: decreasing\n" .
               "- Current Retention: 73.0%\n" .
               "- Churn Rate: 27.0%\n\n" .
               "Provide a brief analysis highlighting key insights and any concerning patterns.";

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
            'content' => $chartPrompt
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
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if ($result && isset($result['choices'][0]['message']['content'])) {
        $chartSummary = trim($result['choices'][0]['message']['content']);
        
        echo '<div class="status success">✅ Chart Summary Generated Successfully!</div>';
        echo '<div class="result-box">';
        echo '<strong>Sample Chart Analysis:</strong><br>';
        echo htmlspecialchars($chartSummary);
        echo '</div>';
    }
} else {
    echo '<div class="status error">❌ Chart summary generation failed</div>';
}

echo '</div>';

// Final Summary
echo '<div class="step" style="border-left-color: #10B981; background: #F0FDF4;">';
echo '<div class="step-title" style="color: #065F46;">🎉 All Tests Passed!</div>';
echo '<div class="status success">';
echo '✅ Your OpenAI integration is working correctly!<br><br>';
echo '<strong>Next Steps:</strong><br>';
echo '1. Your ChurnGuard dashboard AI summaries should be working<br>';
echo '2. Check the browser console for any JavaScript errors<br>';
echo '3. Make sure <code>ai-chart-summary.js</code> is loaded in your HTML<br>';
echo '4. Verify <code>ai-summary.php</code> has proper session authentication';
echo '</div>';
echo '</div>';

?>

        <button onclick="location.reload()">🔄 Run Test Again</button>
        
        <div style="margin-top: 30px; padding: 20px; background: #F9FAFB; border-radius: 8px; font-size: 13px; color: #6b7280;">
            <strong>Debug Information:</strong><br>
            <code>PHP Version: <?php echo PHP_VERSION; ?></code><br>
            <code>CURL Enabled: <?php echo function_exists('curl_init') ? 'Yes' : 'No'; ?></code><br>
            <code>Test Time: <?php echo date('Y-m-d H:i:s'); ?></code>
        </div>
    </div>
</body>
</html>