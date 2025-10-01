<?php
// /connection/config.php
declare(strict_types=1);

// IMPORTANT: This file must define $pdo for all /api/* scripts.
date_default_timezone_set('Asia/Manila');

// Hide PHP notices/warnings so they don't corrupt JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL);

// ---- Adjust these 4 lines if needed ----
$DB_HOST = '127.0.0.1';
$DB_NAME = 'churnguard';   // this is the database you created with the SQL I gave you
$DB_USER = 'root';
$DB_PASS = '';             // XAMPP default is empty
// ----------------------------------------

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    // Return JSON so the frontend sees a proper error (not HTML)
    if (!headers_sent()) { header('Content-Type: application/json'); }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $e->getMessage()]);
    exit;
}
