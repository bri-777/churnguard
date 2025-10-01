<?php
// Shared helpers for every endpoint.
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// Use your existing PDO from connection/config.php
require_once __DIR__ . '/../connection/config.php'; // provides $pdo

// Force JSON always
header('Content-Type: application/json; charset=utf-8');

// ----- Helper wrappers -----
function db(): PDO {
  global $pdo;
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  return $pdo;
}
function user_id(): int {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}
function require_login(): void {
  if (!user_id()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
  }
}
function json_ok(array $data=[]): void {
  echo json_encode(['success'=>true] + $data);
  exit;
}
function json_fail(string $msg, int $code=400, array $extra=[]): void {
  http_response_code($code);
  echo json_encode(['success'=>false,'error'=>$msg] + $extra);
  exit;
}
function read_json(): array {
  $raw = file_get_contents('php://input');
  if ($raw === '' || $raw === false) return [];
  $j = json_decode($raw, true);
  if (!is_array($j)) json_fail('Invalid JSON body', 400, ['body'=>$raw]);
  return $j;
}
function ensure_tables(): void {
  $db = db();
  // churn_data: 1 row per day of inputs per user
  $db->exec("
    CREATE TABLE IF NOT EXISTS churn_data (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      date DATE NOT NULL,
      receipt_count INT DEFAULT 0,
      sales_volume DECIMAL(14,2) DEFAULT 0,
      customer_traffic INT DEFAULT 0,
      morning_receipt_count INT DEFAULT 0,
      swing_receipt_count INT DEFAULT 0,
      graveyard_receipt_count INT DEFAULT 0,
      morning_sales_volume DECIMAL(14,2) DEFAULT 0,
      swing_sales_volume DECIMAL(14,2) DEFAULT 0,
      graveyard_sales_volume DECIMAL(14,2) DEFAULT 0,
      previous_day_receipt_count INT DEFAULT 0,
      previous_day_sales_volume DECIMAL(14,2) DEFAULT 0,
      weekly_average_receipts DECIMAL(10,2) DEFAULT 0,
      weekly_average_sales DECIMAL(14,2) DEFAULT 0,
      transaction_drop_percentage DECIMAL(6,2) DEFAULT 0,
      sales_drop_percentage DECIMAL(6,2) DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_user_day (user_id, date)
    )
  ");
  // churn_predictions: store latest model output
  $db->exec("
    CREATE TABLE IF NOT EXISTS churn_predictions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      date DATE NOT NULL,
      risk_percent DECIMAL(6,2) NOT NULL,
      level VARCHAR(20) NOT NULL,
      description TEXT,
      factors TEXT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_user_date (user_id, date)
    )
  ");
  // login history if you want to show something immediately
  $db->exec("
    CREATE TABLE IF NOT EXISTS login_history (
      id INT AUTO_INCREMENT PRIMARY KEY,
      user_id INT NOT NULL,
      accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      location VARCHAR(100) DEFAULT '',
      device VARCHAR(100) DEFAULT '',
      ip_address VARCHAR(64) DEFAULT '',
      status VARCHAR(20) DEFAULT 'success'
    )
  ");
}
ensure_tables();
