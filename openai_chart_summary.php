<?php
declare(strict_types=1);

// ==================== CONFIGURATION ====================
date_default_timezone_set('Asia/Manila');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/errors.log');

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $envVars = parse_ini_file(__DIR__ . '/.env');
    foreach ($envVars as $key => $value) {
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

// ==================== CORS & HEADERS ====================
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    $allowedOrigins = [
        'http://localhost',
        'http://127.0.0.1',
        'http://localhost:3000',
        'http://127.0.0.1:3000'
    ];
    
    $isAllowed = false;
    foreach ($allowedOrigins as $allowed) {
        if (strpos($origin, $allowed) === 0) {
            $isAllowed = true;
            break;
        }
    }
    
    if ($isAllowed) {
        header("Access-Control-Allow-Origin: $origin");
    }
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// ==================== SESSION MANAGEMENT ====================
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== LOGGING FUNCTION ====================
function logDebug(string $message, array $context = []): void {
    $logMessage = date('[Y-m-d H:i:s] ') . $message;
    if (!empty($context)) {
        $logMessage .= ' | Context: ' . json_encode($context);
    }
    error_log($logMessage);
}

// ==================== RESPONSE FUNCTIONS ====================
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    exit;
}

function jsonSuccess(array $data = []): void {
    jsonResponse(array_merge(['status' => 'success'], $data));
}

function jsonError(string $message, int $code = 400): void {
    jsonResponse([
        'status' => 'error',
        'message' => $message
    ], $code);
}

// ==================== AUTHENTICATION CHECK ====================
logDebug("=== OpenAI Chart Summary Request ===", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'session_id' => session_id(),
    'has_user_id' => isset($_SESSION['user_id'])
]);

if (!isset($_SESSION['user_id'])) {
    logDebug("Authentication failed - no user_id in session");
    jsonError('Authentication required. Please log in.', 401);
}

// ==================== VALIDATE REQUEST METHOD ====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

// ==================== GET OPENAI API KEY ====================
$openaiApiKey = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? null);

if (empty($openaiApiKey)) {
    logDebug("OpenAI API key not found in environment");
    jsonError('OpenAI API key not configured', 503);
}

// ==================== PARSE REQUEST BODY ====================
$rawInput = file_get_contents('php://input');
$requestData = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonError('Invalid JSON data', 422);
}

// ==================== VALIDATE REQUIRED FIELDS ====================
$chartType = $requestData['chartType'] ?? '';
$chartData = $requestData['chartData'] ?? [];

if (empty($chartType)) {
    jsonError('Chart type is required', 422);
}

if (empty($chartData) || !is_array($chartData)) {
    jsonError('Chart data is required and must be an array', 422);
}

logDebug("Processing chart summary request", [
    'chart_type' => $chartType,
    'data_points' => count($chartData)
]);

// ==================== BUILD PROMPT FOR OPENAI ====================
$prompt = buildPromptForChart($chartType, $chartData);

// ==================== CALL OPENAI API ====================
try {
    $summary = callOpenAI($openaiApiKey, $prompt);
    
    logDebug("OpenAI summary generated successfully", [
        'chart_type' => $chartType,
        'summary_length' => strlen($summary)
    ]);
    
    jsonSuccess([
        'summary' => $summary,
        'chartType' => $chartType,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    logDebug("OpenAI API Error", [
        'error' => $e->getMessage(),
        'chart_type' => $chartType
    ]);
    jsonError('Failed to generate summary: ' . $e->getMessage(), 500);
}

// ==================== BUILD PROMPT FUNCTION ====================
function buildPromptForChart(string $chartType, array $chartData): string {
    $dataJson = json_encode($chartData, JSON_PRETTY_PRINT);
    
    $prompts = [
        'retention' => "Analyze this customer retention data and provide a concise, professional summary (3-4 sentences) highlighting key trends, retention rate patterns, and actionable insights:\n\nData:\n{$dataJson}\n\nFocus on: current retention rate, trend direction, and any significant changes.",
        
        'behavior' => "Analyze this customer behavior and transaction pattern data and provide a concise, professional summary (3-4 sentences) highlighting key insights about customer purchasing patterns:\n\nData:\n{$dataJson}\n\nFocus on: transaction frequency, average transaction values, and behavioral trends.",
        
        'revenue' => "Analyze this revenue impact data and provide a concise, professional summary (3-4 sentences) highlighting revenue trends and financial insights:\n\nData:\n{$dataJson}\n\nFocus on: revenue trends, growth patterns, and potential revenue risks.",
        
        'trends' => "Analyze this 30-day risk trend data and provide a concise, professional summary (3-4 sentences) highlighting risk levels and concerning patterns:\n\nData:\n{$dataJson}\n\nFocus on: risk level changes, trend direction, and areas requiring attention.",
        
        'sales_trend' => "Analyze this sales trend data and provide a concise, professional summary (3-4 sentences) highlighting sales performance and patterns:\n\nData:\n{$dataJson}\n\nFocus on: sales growth/decline, peak periods, and overall performance.",
        
        'comparison' => "Analyze this comparison data and provide a concise, professional summary (3-4 sentences) highlighting key differences and insights:\n\nData:\n{$dataJson}\n\nFocus on: performance comparisons, significant changes, and trends.",
    ];
    
    return $prompts[$chartType] ?? 
        "Analyze this data and provide a concise, professional summary (3-4 sentences) highlighting key insights and trends:\n\nData:\n{$dataJson}";
}

// ==================== OPENAI API CALL FUNCTION ====================
function callOpenAI(string $apiKey, string $prompt): string {
    $url = 'https://api.openai.com/v1/chat/completions';
    
    $data = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a professional business analyst specializing in data interpretation for sales and customer analytics dashboards. Provide clear, concise, and actionable insights. Keep summaries brief (3-4 sentences) and focused on key trends and actionable recommendations.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 250,
        'top_p' => 1,
        'frequency_penalty' => 0,
        'presence_penalty' => 0
    ];
    
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception("cURL error: $curlError");
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'Unknown API error';
        throw new Exception("OpenAI API error (HTTP $httpCode): $errorMsg");
    }
    
    $responseData = json_decode($response, true);
    
    if (!isset($responseData['choices'][0]['message']['content'])) {
        throw new Exception('Invalid response format from OpenAI');
    }
    
    return trim($responseData['choices'][0]['message']['content']);
}