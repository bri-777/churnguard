<?php
/* api/_bootstrap.php */
declare(strict_types=1);

/*
  Shared bootstrap for all API endpoints.
  - Starts session (same-site defaults)
  - Loads PDO from connection/config.php
  - Normalizes PDO attributes
  - Provides JSON helpers: respond(), ok(), error()
  - Auth helpers: require_login(), current_user_id(), current_user()
  - Input helpers: get_json_body()
  - Soft CSRF support (header respected if present)
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
  // Harden session a bit (safe defaults; ignores on old PHP)
  @session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
}

/* ---------- Errors & headers ---------- */
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// Always return JSON + disable caches so the frontend always sees fresh state
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

/* ---------- DB ---------- */
require_once __DIR__ . '/../connection/config.php'; // must create $pdo (PDO)

if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Database not initialized ($pdo missing).'
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// Normalize PDO
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

/* ---------- JSON responders (names your code already uses) ---------- */
function respond(array $data = [], int $code = 200): void {
  http_response_code($code);
  if (ob_get_level()) { @ob_clean(); }
  // If 'success' not explicitly provided, default success=true for 2xx, false otherwise
  if (!array_key_exists('success', $data)) {
    $data = ['success' => ($code >= 200 && $code < 300)] + $data;
  }
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function ok(array $data = [], int $code = 200): void {
  respond(['success' => true] + $data, $code);
}

function error_json(string $message, int $code = 400, array $extra = []): void {
  respond(['success' => false, 'message' => $message] + $extra, $code);
}

/* Back-compat names (your older code may call these) */
function json_ok(array $data = [], int $code = 200): void { ok($data, $code); }
function json_error(string $message, int $code = 500, array $extra = []): void { error_json($message, $code, $extra); }

/* ---------- Auth helpers ---------- */
function current_user_id(): ?int {
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_login(): int {
  $uid = current_user_id();
  if (!$uid) error_json('Not authenticated', 401);
  return $uid;
}

/**
 * Load a minimal user row for the logged-in user.
 * Returns [] if missing (shouldn’t happen after require_login()).
 */
function current_user(PDO $pdo): array {
  $uid = current_user_id();
  if (!$uid) return [];
  $s = $pdo->prepare("SELECT user_id, firstname, lastname, email, username, icon AS avatar_url,
                             role, two_factor_enabled
                      FROM users
                      WHERE user_id = ?
                      LIMIT 1");
  $s->execute([$uid]);
  return $s->fetch() ?: [];
}

/* ---------- CSRF (soft) ---------- */
/*
  Soft enforcement:
  - If X-CSRF-Token header is present, must match the session token.
  - If header absent, allow request (same-origin session) so existing frontends keep working.
  Generate a token for frontends that want to use it.
*/
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function require_csrf_if_sent(): void {
  $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
  if ($sent === '') return; // not sent -> allow (soft mode)
  $sess = $_SESSION['csrf_token'] ?? '';
  if (!$sess || !hash_equals($sess, $sent)) {
    error_json('Invalid CSRF token', 403);
  }
}

/* For state-changing methods, respect CSRF if client sends header */
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
  require_csrf_if_sent();
}

/* ---------- Input helpers ---------- */
function get_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === '' || $raw === false) return [];
  $data = json_decode($raw, true);
  if (!is_array($data)) error_json('Invalid JSON body', 400);
  return $data;
}

/* Small param helpers (safe-ish casting) */
function qstr(array $src, string $key, ?string $default = null): ?string {
  if (!array_key_exists($key, $src)) return $default;
  $v = $src[$key];
  if ($v === null) return null;
  $v = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  return trim($v);
}
function qint(array $src, string $key, ?int $default = null): ?int {
  if (!array_key_exists($key, $src)) return $default;
  if ($src[$key] === null || $src[$key] === '') return $default;
  return (int)$src[$key];
}

/* ---------- Handy router for ?action=... endpoints (optional) ----------
   Usage in endpoint:
     $action = action();
     switch ($action) { ... }
*/
function action(?string $fallback = 'index'): string {
  $a = isset($_GET['action']) ? (string)$_GET['action'] : ($fallback ?? 'index');
  return $a === '' ? ($fallback ?? 'index') : $a;
}

/* ---------- Final safety: JSON decode failures shouldn’t leak warnings ---------- */
set_exception_handler(function(Throwable $e) {
  // Don’t leak stack traces in production responses
  error_json('Server error: ' . $e->getMessage(), 500);
});
