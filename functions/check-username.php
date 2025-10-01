<?php
// /churnguard-pro/functions/check-username.php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../connection/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');

if (strlen($username) < 3) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username must be at least 3 characters']);
    exit;
}

try {
    // Use existing PDO from config.php, or fallback to a factory if that’s how yours is set up
    if (isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
    } elseif (function_exists('getDBConnection')) {
        $db = getDBConnection();
    } else {
        throw new RuntimeException('Database connection not available.');
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $count = (int)$stmt->fetchColumn();

    if ($count > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Username is already taken']);
    } else {
        echo json_encode(['status' => 'success', 'message' => 'Username is available']);
    }
} catch (Throwable $e) {
    error_log('check-username error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
