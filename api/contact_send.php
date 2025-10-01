<?php
// api/contact_send.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// ====== SMTP SETTINGS — EDIT THESE ======
const SMTP_HOST   = 'smtp.gmail.com';
const SMTP_PORT   = 587;             // 587 (TLS) or 465 (SSL)
const SMTP_SECURE = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; // or ::ENCRYPTION_SMTPS for 465
const SMTP_USER   = 'yourgmail@gmail.com';   // the Gmail account you own
const SMTP_PASS   = 'xxxx xxxx xxxx xxxx';   // Gmail App Password (NOT your login password)
const SITE_FROM   = SMTP_USER;               // must match authenticated user for Gmail
const SITE_NAME   = 'ChurnGuard Pro';        // displayed in the From header
const TO_EMAIL    = 'ysl.aether.bank@gmail.com'; // recipient you asked for
// =======================================

// Error logging (server-side)
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/contact_error.log');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// Read JSON first, fallback to form fields
$payload = null;
$raw = file_get_contents('php://input');
if ($raw) {
  $try = json_decode($raw, true);
  if (is_array($try)) $payload = $try;
}
if (!$payload) $payload = $_POST;

// Helpers
function clean_line(string $s): string { return trim(str_replace(["\r","\n"], ' ', $s)); }
function clean_text(string $s): string {
  $s = str_replace(["\r\n", "\r"], "\n", $s);
  return trim($s);
}
function capped(string $s, int $len): string { return mb_substr($s, 0, $len, 'UTF-8'); }

// Honeypot (spam)
$honeypot = isset($payload['website']) ? (string)$payload['website'] : '';
if ($honeypot !== '') {
  echo json_encode(['ok' => true, 'message' => 'Sent']);
  exit;
}

// Inputs
$name    = isset($payload['name']) ? clean_line((string)$payload['name']) : '';
$email   = isset($payload['email']) ? clean_line((string)$payload['email']) : '';
$company = isset($payload['company']) ? clean_line((string)$payload['company']) : '';
$message = isset($payload['message']) ? clean_text((string)$payload['message']) : '';

// Validate
if ($name === '' || $email === '' || $message === '') {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
  exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Invalid email address']);
  exit;
}

// Caps
$name    = capped($name, 120);
email:
$email   = capped($email, 200);
$company = capped($company, 200);
$message = capped($message, 8000);

// Compose bodies
date_default_timezone_set('Asia/Manila');
$now = date('Y-m-d H:i:s');
$subject = "[Website Contact] $name - " . ($company !== '' ? $company : 'No company');

$htmlBody = <<<HTML
<!doctype html><html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif;font-size:14px;color:#222;">
<h2 style="margin:0 0 12px 0;">New contact form submission</h2>
<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;background:#f9f9f9;">
<tr><td><strong>Name</strong></td><td>{$name}</td></tr>
<tr><td><strong>Email</strong></td><td>{$email}</td></tr>
<tr><td><strong>Company</strong></td><td>{$company}</td></tr>
<tr><td><strong>Submitted at</strong></td><td>{$now} Asia/Manila</td></tr>
</table>
<h3 style="margin:18px 0 8px 0;">Message</h3>
<div style="white-space:pre-wrap;line-height:1.5;">{$message}</div>
<p style="font-size:12px;color:#555;margin-top:18px;">This email was sent from {SITE_NAME} contact form.</p>
</body></html>
HTML;

$textBody = "New contact form submission\n\n"
  . "Name: {$name}\nEmail: {$email}\nCompany: {$company}\n"
  . "Submitted at: {$now} Asia/Manila\n\nMessage:\n{$message}\n";

// ===== PHPMailer bootstrap =====
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
$manualBase = __DIR__ . '/../vendor/phpmailer/phpmailer/src';

if (file_exists($composerAutoload)) {
  require $composerAutoload;
} else {
  // Manual include (no Composer)
  require $manualBase . '/Exception.php';
  require $manualBase . '/PHPMailer.php';
  require $manualBase . '/SMTP.php';
}

try {
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = SMTP_HOST;
  $mail->SMTPAuth   = true;
  $mail->Username   = SMTP_USER;
  $mail->Password   = SMTP_PASS;
  $mail->Port       = SMTP_PORT;
  $mail->SMTPSecure = SMTP_SECURE;

  // Gmail requires From to be the authenticated user
  $mail->setFrom(SITE_FROM, SITE_NAME);
  // Reply-To set to the sender who filled the form
  $mail->addReplyTo($email, $name);

  $mail->addAddress(TO_EMAIL); // recipient
  $mail->Subject = $subject;

  $mail->isHTML(true);
  $mail->Body    = $htmlBody;
  $mail->AltBody = $textBody;

  $mail->send();

  echo json_encode(['ok' => true, 'message' => 'Sent']);
} catch (Exception $e) {
  // Log the detailed SMTP error, return user-friendly JSON
  $detail = $e->getMessage();
  @file_put_contents(__DIR__ . '/contact_failed.log',
    '['.$now.'] '.$subject."\n".$textBody."\nSMTP ERROR: ".$detail."\n\n",
    FILE_APPEND
  );
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'Mail transport failed (SMTP).',
    'hint'  => 'Check SMTP credentials/port/security. See server log: api/contact_failed.log'
  ]);
}
