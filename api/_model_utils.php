<?php
// api/_model_utils.php
declare(strict_types=1);

function xgb_model_dir(): string {
  return __DIR__ . '/models';
}
function xgb_model_path(): string {
  return xgb_model_dir() . '/churn_xgb.json';
}

/** Ensure models/ exists (creates with 0755) */
function ensure_model_dir(): void {
  $dir = xgb_model_dir();
  if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
      throw new RuntimeException("Failed to create model directory: {$dir}");
    }
  }
}

/** Quick sanity check: is it JSON and looks like an XGBoost dump? */
function validate_xgb_json(string $jsonText): void {
  $j = json_decode($jsonText, true);
  if (!is_array($j)) {
    throw new RuntimeException('Uploaded file is not valid JSON.');
  }
  // Accept common layouts
  $isNew  = isset($j['learner']['gradient_booster']['model']['trees']);
  $isOld  = isset($j['trees']) || isset($j['learner']);
  if (!$isNew && !$isOld) {
    throw new RuntimeException('JSON does not look like an XGBoost model dump.');
  }
}

/** Rotates old model to .bak (timestamp) before writing a new one */
function write_xgb_model_atomically(string $jsonText): void {
  ensure_model_dir();
  $path = xgb_model_path();

  // Backup existing
  if (is_file($path)) {
    $ts = date('Ymd_His');
    $bak = xgb_model_dir() . "/churn_xgb_{$ts}.json.bak";
    if (!copy($path, $bak)) {
      throw new RuntimeException('Failed to backup old model.');
    }
  }

  $tmp = $path . '.tmp';
  if (file_put_contents($tmp, $jsonText, LOCK_EX) === false) {
    throw new RuntimeException('Failed to write temp model file.');
  }
  if (!rename($tmp, $path)) {
    @unlink($tmp);
    throw new RuntimeException('Failed to move model into place.');
  }
}

/** Returns basic metadata or null if not present */
function get_model_status(): ?array {
  $p = xgb_model_path();
  if (!is_file($p)) return null;
  return [
    'path' => $p,
    'size_bytes' => filesize($p) ?: 0,
    'modified' => date('c', filemtime($p) ?: time()),
    'sha1' => sha1_file($p) ?: null,
  ];
}
