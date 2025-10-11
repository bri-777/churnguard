
<?php
session_start();
$_SESSION['user_id'] = 1;

if (file_exists('.env')) {
    $envVars = parse_ini_file('.env');
    foreach ($envVars as $key => $value) {
        putenv("$key=$value");
    }
}

$apiKey = getenv('OPENAI_API_KEY');

echo "<h1>Quick Test Results</h1>";
echo "<p>API Key: " . (empty($apiKey) ? "❌ NOT FOUND" : "✅ FOUND") . "</p>";

if (!empty($apiKey)) {
    $ch = curl_init('https://api.openai.com/v1/models');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "<p>API Connection: " . ($httpCode === 200 ? "✅ WORKING" : "❌ FAILED (Code: $httpCode)") . "</p>";
}

echo "<p>PHP File: " . (file_exists('openai_chart_summary.php') ? "✅ EXISTS" : "❌ MISSING") . "</p>";
echo "<p>CSS File: " . (file_exists('chart-summary-styles.css') ? "✅ EXISTS" : "❌ MISSING") . "</p>";

if (file_exists('openai_chart_summary.php')) {
    echo "<hr><h2>Test AI Summary</h2>";
    echo "<button onclick='testSummary()' style='padding:10px 20px; background:#6366f1; color:white; border:none; border-radius:6px; cursor:pointer;'>Click to Test</button>";
    echo "<div id='result' style='margin-top:20px; padding:15px; background:#f9fafb; border-radius:8px;'></div>";
    
    echo "<script>
    async function testSummary() {
        document.getElementById('result').innerHTML = '⏳ Testing...';
        try {
            const response = await fetch('openai_chart_summary.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    chartType: 'retention',
                    chartData: [{date:'2024-01-01',retention_rate:85},{date:'2024-01-02',retention_rate:87}]
                })
            });
            const data = await response.json();
            if (data.status === 'success') {
                document.getElementById('result').innerHTML = '✅ SUCCESS!<br><br><strong>AI Summary:</strong><br>' + data.summary;
            } else {
                document.getElementById('result').innerHTML = '❌ ERROR: ' + (data.message || 'Unknown error');
            }
        } catch (e) {
            document.getElementById('result').innerHTML = '❌ FAILED: ' + e.message;
        }
    }
    </script>";
}
?>
