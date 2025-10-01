<?php
// api/model_admin.php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_model_utils.php';

$uid = require_login(); // keep your auth
// Optional: add your own is_admin($uid) guard here.

function j_ok(array $d = []) { json_ok($d); }
function j_err(string $m, int $c = 400, array $extra = []) { json_error($m, $c, $extra); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? 'status');

try {
  if ($method === 'GET' && $action === 'status') {
    $st = get_model_status();
    j_ok([
      'has_model' => (bool)$st,
      'model' => $st ?: null,
      'expected_path' => xgb_model_path(),
    ]);
  }

  if ($method === 'POST' && $action === 'upload') {
    if (!isset($_FILES['model']) || $_FILES['model']['error'] !== UPLOAD_ERR_OK) {
      throw new RuntimeException('Upload failed. Field name must be "model".');
    }
    $tmpPath = $_FILES['model']['tmp_name'];
    $bytes = file_get_contents($tmpPath);
    if ($bytes === false) throw new RuntimeException('Cannot read uploaded file.');
    // Optional size cap (e.g., 50 MB)
    if (strlen($bytes) > 50 * 1024 * 1024) {
      throw new RuntimeException('File too large. Max 50 MB.');
    }

    validate_xgb_json($bytes);
    write_xgb_model_atomically($bytes);
    $st = get_model_status();
    j_ok(['uploaded' => true, 'model' => $st]);
  }

  // Fallback
  j_err('Unsupported method/action', 405, ['method' => $method, 'action' => $action]);
} catch (Throwable $e) {
  j_err('Model admin error', 500, ['detail' => $e->getMessage()]);
}
