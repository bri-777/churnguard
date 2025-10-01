<?php
// api/login_history.php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'off');

$pdo = $pdo ?? null;

// Try your bootstrap (for $pdo, require_login, etc.)
$bootstrap = __DIR__ . '/_bootstrap.php';
if (is_file($bootstrap)) require_once $bootstrap;

/* JSON helpers */
if (!function_exists('json_ok')) {
  function json_ok(array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data + ['ok' => true, 'success' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('json_error')) {
  function json_error(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'success' => false, 'error' => $message, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

/* Resolve user id */
if (function_exists('require_login')) {
  $uid = (int) require_login();
} else {
  @session_start();
  $uid = (int)($_SESSION['user_id'] ?? $_SESSION['uid'] ?? 0);
  if ($uid <= 0) json_error('Not authenticated', 401);
}

/* Get PDO if not provided */
if (!$pdo instanceof PDO) {
  $dsn  = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=churnguard;charset=utf8mb4';
  $user = getenv('DB_USER') ?: 'root';
  $pass = getenv('DB_PASS') ?: '';
  try {
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
  } catch (Throwable $e) {
    json_error('DB connection failed: ' . $e->getMessage(), 500);
  }
}

/* ----------------- Helpers ----------------- */
function db_object_type(PDO $pdo, string $name): ?string {
  try {
    $stmt = $pdo->prepare("SELECT TABLE_NAME, TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :n");
    $stmt->execute([':n' => $name]);
    $row = $stmt->fetch();
    return $row['TABLE_TYPE'] ?? null; // 'BASE TABLE' or 'VIEW'
  } catch (Throwable $e) {
    return null;
  }
}
function has_view_login_history(PDO $pdo): bool {
  return (db_object_type($pdo, 'login_history') === 'VIEW');
}
function has_table_auth_log(PDO $pdo): bool {
  return (db_object_type($pdo, 'auth_log') === 'BASE TABLE');
}
function client_ip(): string {
  foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $h) {
    if (!empty($_SERVER[$h])) {
      $ip = $_SERVER[$h];
      if ($h === 'HTTP_X_FORWARDED_FOR') $ip = trim(explode(',', $ip)[0]);
      return substr($ip, 0, 63);
    }
  }
  return '';
}
function device_from_ua(): string {
  return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
}

/**
 * Build the SELECT for list/stream.
 * - Prefer the VIEW `login_history` (from your SQL)
 * - Fallback to base table `auth_log` with formatting
 * Always alias time to `accessed_at` and ip to `ip`.
 */
function build_select_sql(bool $useView, bool $ascending, bool $withSince, bool $withBefore, int $limit): array {
  $order = $ascending ? 'ASC' : 'DESC';
  $where = " WHERE user_id = :uid";
  if ($withSince)  $where .= " AND id > :since_id";
  if ($withBefore) $where .= " AND id < :before_id";

  if ($useView) {
    $sql = "SELECT id, user_id,
                   `datetime` AS accessed_at,
                   location, device, `ip`, `status`
            FROM login_history" . $where . "
            ORDER BY id $order
            LIMIT " . (int)$limit;
  } else {
    // Fallback to base table auth_log (format to match view)
    $sql = "SELECT id, user_id,
                   DATE_FORMAT(event_time, '%Y-%m-%d %H:%i:%s') AS accessed_at,
                   COALESCE(location,'') AS location,
                   COALESCE(device,'')   AS device,
                   COALESCE(ip_address,'') AS `ip`,
                   CASE WHEN status = 1 THEN 'Success' ELSE 'Failed' END AS `status`
            FROM auth_log" . $where . "
            ORDER BY id $order
            LIMIT " . (int)$limit;
  }
  return [$sql];
}

/* ----------------- Actions ----------------- */
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

try {
  $viewExists = has_view_login_history($pdo);
  $authExists = has_table_auth_log($pdo);

  if ($action === 'record') {
    if (!$authExists) {
      json_error("Base table 'auth_log' not found. Import your SQL dump first.", 500);
    }
    // Insert into base table only (view is read-only)
    $stmt = $pdo->prepare(
      "INSERT INTO auth_log (user_id, event_time, location, device, ip_address, status)
       VALUES (:uid, NOW(), :loc, :dev, :ip, :status)"
    );
    $stmt->execute([
      ':uid'    => $uid,
      ':loc'    => '',                  // add geo if you have it
      ':dev'    => device_from_ua(),
      ':ip'     => client_ip(),
      ':status' => 1,                   // 1 = Success
    ]);
    json_ok(['inserted_id' => (int)$pdo->lastInsertId()]);
  }

  if ($action === 'list') {
    if (!$viewExists && !$authExists) {
      json_error("Neither view 'login_history' nor table 'auth_log' exists. Import your SQL dump.", 500);
    }

    $limit     = (int)($_GET['limit'] ?? 50);
    if ($limit < 1) $limit = 1;
    if ($limit > 200) $limit = 200;
    $before_id = (int)($_GET['before_id'] ?? 0);
    $since_id  = (int)($_GET['since_id']  ?? 0);

    [$sql] = build_select_sql($viewExists, /*ascending*/false, $since_id>0, $before_id>0, $limit);
    $stmt  = $pdo->prepare($sql);
    $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
    if ($since_id > 0)  $stmt->bindValue(':since_id',  $since_id,  PDO::PARAM_INT);
    if ($before_id > 0) $stmt->bindValue(':before_id', $before_id, PDO::PARAM_INT);
    $stmt->execute();

    $rows  = $stmt->fetchAll() ?: [];
    // Normalize rows to consistent keys
    $items = array_map(function(array $r) {
      return [
        'id'          => (int)$r['id'],
        'accessed_at' => (string)$r['accessed_at'],
        'location'    => (string)($r['location'] ?? ''),
        'device'      => (string)($r['device'] ?? ''),
        'ip'          => (string)($r['ip'] ?? $r['ip_address'] ?? ''),
        'status'      => (string)$r['status'],
      ];
    }, $rows);

    $next_before_id = !empty($rows) ? (int)end($rows)['id'] : 0;
    $latest_id      = !empty($rows) ? (int)$rows[0]['id']    : (int)$since_id;

    json_ok([
      'items'          => $items,
      'next_before_id' => $next_before_id,
      'count'          => count($items),
      'latest_id'      => $latest_id
    ]);
  }

  if ($action === 'stream') {
    if (!$viewExists && !$authExists) {
      // Keep SSE contract (emit a quick 'bye')
      header('Content-Type: text/event-stream');
      echo "event: batch\n"; echo "data: {\"items\":[],\"latest_id\":0}\n\n";
      echo "event: bye\n";   echo "data: {}\n\n";
      exit;
    }

    @ignore_user_abort(true);
    @set_time_limit(0);
    if (session_status() === PHP_SESSION_ACTIVE) @session_write_close();

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    echo "retry: 5000\n\n";
    while (ob_get_level() > 0) @ob_end_flush();
    @flush();

    $since_id = (int)($_GET['since_id'] ?? 0);
    $start    = time();
    $timeout  = 55;

    while (!connection_aborted() && (time() - $start) < $timeout) {
      [$sql] = build_select_sql($viewExists, /*ascending*/true, $since_id>0, /*withBefore*/false, 100);
      $stmt  = $pdo->prepare($sql);
      $stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
      if ($since_id > 0) $stmt->bindValue(':since_id', $since_id, PDO::PARAM_INT);
      $stmt->execute();
      $rows = $stmt->fetchAll() ?: [];

      if ($rows) {
        $since_id = (int)$rows[count($rows)-1]['id'];
        $payload = array_map(function(array $r) {
          return [
            'id'          => (int)$r['id'],
            'accessed_at' => (string)$r['accessed_at'],
            'location'    => (string)($r['location'] ?? ''),
            'device'      => (string)($r['device'] ?? ''),
            'ip'          => (string)($r['ip'] ?? $r['ip_address'] ?? ''),
            'status'      => (string)$r['status'],
          ];
        }, $rows);

        echo "event: batch\n";
        echo 'data: ' . json_encode(['items' => $payload, 'latest_id' => $since_id], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n\n";
        @flush();
      } else {
        echo "event: ping\n";
        echo "data: {}\n\n";
        @flush();
        usleep(800000);
      }
    }

    echo "event: bye\n";
    echo "data: {}\n\n";
    @flush();
    exit;
  }

  json_error('Unknown action', 400);

} catch (Throwable $e) {
  json_error('Server error: ' . $e->getMessage(), 500);
}
