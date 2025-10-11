<?php
header('Content-Type: text/plain');

echo "=== HOSTINGER SETUP CHECK ===\n\n";

// Check .env location
$envPath = __DIR__ . '/.env';
echo "Looking for .env at: $envPath\n";

if (file_exists($envPath)) {
    echo "✅ .env file EXISTS\n\n";
    
    $content = file_get_contents($envPath);
    $lines = explode("\n", $content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;
        
        if (strpos($line, 'OPENAI_API_KEY') !== false) {
            list($k, $v) = explode('=', $line, 2);
            echo "API Key: " . substr(trim($v), 0, 15) . "...(hidden)\n";
            echo "Key length: " . strlen(trim($v)) . " chars\n";
        } else {
            echo $line . "\n";
        }
    }
} else {
    echo "❌ .env file NOT FOUND\n";
    echo "Upload .env to: $envPath\n";
}

echo "\n=== API KEY TEST ===\n\n";

// Test API key
$_ENV = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $val) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

$apiKey = $_ENV['OPENAI_API_KEY'] ?? '';

if (empty($apiKey)) {
    echo "❌ API Key is empty\n";
    exit;
}

echo "Testing OpenAI API...\n";

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [['role' => 'user', 'content' => 'Say: Working']],
        'max_tokens' => 10
    ]),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n\n";

if ($code === 200) {
    echo "✅✅✅ SUCCESS! API KEY WORKS! ✅✅✅\n";
    echo "Your recommendations will work now.\n";
} elseif ($code === 401) {
    echo "❌ INVALID API KEY\n";
    echo "Get new key: https://platform.openai.com/api-keys\n";
} elseif ($code === 429) {
    echo "❌ NO CREDITS\n";
    echo "Add billing: https://platform.openai.com/account/billing\n";
} else {
    echo "❌ ERROR: $code\n";
    echo substr($response, 0, 300) . "\n";
}

echo "\n⚠️ DELETE THIS FILE AFTER CHECKING!\n";
?>