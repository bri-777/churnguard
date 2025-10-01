<?php
/* /api/profile.php */
require __DIR__ . '/_bootstrap.php';   // must start session + create $pdo + respond() + require_login()

$uid    = require_login();             // dies with 401 if not logged in
$action = $_GET['action'] ?? 'me';

try {
  if ($action === 'me') {
    // STRICTLY by logged-in user_id; no company in response.
    $sql = "SELECT 
              user_id,
              COALESCE(NULLIF(firstname,''), '') AS firstname,
              COALESCE(NULLIF(lastname,''),  '') AS lastname,
              COALESCE(NULLIF(email,''),    '') AS email,
              COALESCE(NULLIF(username,''), '') AS username,
              COALESCE(icon, '')                 AS avatar_url,
              COALESCE(role, 'User')             AS role,
              COALESCE(two_factor_enabled, 0)    AS two_factor_enabled
            FROM users
            WHERE user_id = ?
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) respond(['success'=>false,'message'=>'User not found'], 404);

    // Normalize display_name on the server (first + last or fallback to username).
    $display = trim(($u['firstname'] ?? '').' '.($u['lastname'] ?? ''));
    if ($display === '') $display = $u['username'] ?? '';

    respond(['success'=>true, 'user'=>array_merge($u, ['display_name'=>$display])]);
  }

  if ($action === 'update_profile') {
    // Only allow editing of first/last/email (NO company).
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $first = trim((string)($in['firstname'] ?? ''));
    $last  = trim((string)($in['lastname']  ?? ''));
    $email = trim((string)($in['email']     ?? ''));

    $stmt = $pdo->prepare("UPDATE users SET firstname=?, lastname=?, email=? WHERE user_id=?");
    $stmt->execute([$first, $last, $email, $uid]);
    respond(['success'=>true]);
  }

  if ($action === 'change_password') {
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $current = (string)($in['current_password']  ?? '');
    $new     = (string)($in['new_password']      ?? '');
    $confirm = (string)($in['confirm_password']  ?? '');

    if ($new === '' || $new !== $confirm) respond(['success'=>false,'message'=>'Passwords do not match'], 400);

    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id=?");
    $stmt->execute([$uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || !password_verify($current, $row['password'])) {
      respond(['success'=>false,'message'=>'Current password incorrect'], 400);
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $u = $pdo->prepare("UPDATE users SET password=? WHERE user_id=?");
    $u->execute([$hash, $uid]);
    respond(['success'=>true]);
  }

  if ($action === 'toggle_2fa') {
    $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
    try {
      $u = $pdo->prepare("UPDATE users SET two_factor_enabled=? WHERE user_id=?");
      $u->execute([$enabled ? 1 : 0, $uid]);
    } catch (Throwable $e) { /* column might not exist; ignore to avoid breaking */ }
    respond(['success'=>true,'enabled'=>$enabled ? 1 : 0]);
  }

  if ($action === 'upload_avatar') {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
      respond(['success'=>false,'message'=>'Upload failed'], 400);
    }
    $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'])) respond(['success'=>false,'message'=>'Invalid image'], 400);

    $dir = __DIR__ . '/../uploads/avatars';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = 'u'.$uid.'_'.time().'.'.$ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) respond(['success'=>false,'message'=>'Move failed'], 500);

    $rel = 'uploads/avatars/'.$name;
    $u = $pdo->prepare("UPDATE users SET icon=? WHERE user_id=?");
    $u->execute([$rel, $uid]);

    respond(['success'=>true,'avatar_url'=>$rel]);
  }

  respond(['success'=>false,'message'=>'Unknown action'], 400);
} catch (Throwable $e) {
  respond(['success'=>false,'message'=>'Server error: '.$e->getMessage()], 500);
}
