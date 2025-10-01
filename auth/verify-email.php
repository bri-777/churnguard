<?php
// /churnguard-pro/auth/verify-email.php
declare(strict_types=1);

require_once __DIR__ . '/../connection/config.php';
session_start();

require_once __DIR__ . '/../PHPMailer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* Mirror SMTP constants (keep in one place if you prefer) */
const SMTP_HOST          = 'smtp.gmail.com';
const SMTP_USER          = 'ysl.aether.bank@gmail.com';
const SMTP_PASS          = 'bsnn jagm stvw isqk';
const SMTP_FROM_EMAIL    = 'ysl.aether.bank@gmail.com';
const SMTP_FROM_NAME     = 'CHURNGUARD';
const SMTP_ALLOW_SELF_SIGNED = true;
const DEV_ECHO_OTP       = false;

$notification = ['message' => '', 'type' => ''];
$email = $_SESSION['signup_email'] ?? '';

if (!$email) {
  session_unset();
  session_destroy();
  header("Location: ../pages/landing.php");
  exit();
}

function json_response(array $payload) {
  if (function_exists('ob_get_length') && ob_get_length()) { ob_clean(); }
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}

function send_verification_email(string $toEmail, string $otp, ?string &$errorOut = null): bool {
  $attempts = [
    ['secure'=>PHPMailer::ENCRYPTION_STARTTLS,'port'=>587],
    ['secure'=>PHPMailer::ENCRYPTION_SMTPS,   'port'=>465],
  ];
  foreach ($attempts as $i => $opt) {
    $mail = new PHPMailer(true);
    try {
      $mail->isSMTP();
      $mail->Host       = SMTP_HOST;
      $mail->SMTPAuth   = true;
      $mail->Username   = SMTP_USER;
      $mail->Password   = SMTP_PASS;
      $mail->SMTPSecure = $opt['secure'];
      $mail->Port       = $opt['port'];
      $mail->CharSet    = 'UTF-8';

      if (function_exists('gethostbyname')) {
        $ipv4 = gethostbyname(SMTP_HOST);
        if ($ipv4 && $ipv4 !== SMTP_HOST) $mail->Host = $ipv4;
      }
      if (SMTP_ALLOW_SELF_SIGNED) {
        $mail->SMTPOptions = ['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]];
      }
      $mail->SMTPDebug = 0;
      $mail->Debugoutput = function($str){ error_log('PHPMailer: '.trim($str)); };

      $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
      $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
      $mail->addAddress($toEmail);

      $mail->isHTML(true);
     $mail->Subject = 'Verify your ChurnGuard account';
$mail->Body    = "
  <!doctype html>
  <html lang='en'>
  <head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width,initial-scale=1'>
    <title>ChurnGuard Verification</title>
  </head>
  <body style='margin:0;padding:0;background:#f6f7fb;'>
    <div style='display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;'>
      Your ChurnGuard verification code is {$otp}. It expires in 15 minutes.
    </div>

    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%'>
      <tr>
        <td align='center' style='padding:24px 12px;'>
          <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='600' style='max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e7e8ee;'>
            <!-- Header -->
            <tr>
              <td style='padding:20px 24px;background:#0b0c0f;'>
                <div style='font-family:Inter,Segoe UI,Arial,sans-serif;font-size:18px;font-weight:700;letter-spacing:.2px;color:#ffffff;'>
                  ChurnGuard
                </div>
                <div style='font-family:Inter,Segoe UI,Arial,sans-serif;font-size:12px;color:#cfd3db;margin-top:2px;'>
                  Real-time churn prediction for PH convenience retail
                </div>
              </td>
            </tr>

            <!-- Body -->
            <tr>
              <td style='padding:24px;'>
                <h2 style='margin:0 0 8px 0;font-family:Inter,Segoe UI,Arial,sans-serif;font-size:20px;line-height:1.3;color:#11131a;'>
                  Welcome to ChurnGuard
                </h2>
                <p style='margin:0 0 16px 0;font-family:Inter,Segoe UI,Arial,sans-serif;font-size:14px;line-height:1.6;color:#3b3f4a;'>
                  Use the One-Time Password below to verify your email. For your security, never share this code with anyone.
                </p>

                <!-- OTP block -->
                <div style='margin:12px 0 18px 0;padding:14px 18px;border:1px solid #ffe2cf;background:#fff7f1;border-radius:10px;'>
                  <div style='font-family:ui-monospace,Menlo,Consolas,Monaco,monospace;font-size:28px;letter-spacing:6px;font-weight:700;color:#ff6a00;text-align:center;'>
                    {$otp}
                  </div>
                </div>

                <ul style='margin:0 0 16px 20px;padding:0;font-family:Inter,Segoe UI,Arial,sans-serif;font-size:13px;line-height:1.6;color:#3b3f4a;'>
                  <li>Code expires in 15 minutes</li>
                  <li>Enter it on the verification page only</li>
                  <li>ChurnGuard will never ask for your OTP via call, SMS, or chat</li>
                </ul>

                <p style='margin:16px 0 0 0;font-family:Inter,Segoe UI,Arial,sans-serif;font-size:13px;line-height:1.6;color:#3b3f4a;'>
                  Need help? Email
                  <a href='mailto:ysl.aether.bank@gmail.com' style='color:#ff6a00;text-decoration:underline;'>ysl.aether.bank@gmail.com</a>
                  or call <a href='tel:09120091223' style='color:#ff6a00;text-decoration:underline;'>09120091223</a>.
                </p>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td style='padding:16px 24px;border-top:1px solid #eef0f5;background:#fafbff;'>
                <p style='margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;font-size:12px;color:#6b7280;line-height:1.5;'>
                  If you did not request this, you can ignore this message.
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
  </html>
";
$mail->AltBody = "Your ChurnGuard verification code is: {$otp}. It expires in 15 minutes. Do not share this code with anyone. Need help? Email ysl.aether.bank@gmail.com or call 09120091223.";


      if ($mail->send()) return true;
      $errorOut = 'Unknown send failure';
    } catch (Exception $e) {
      $errorOut = $e->getMessage();
      error_log('Verify mail send error (try '.($i+1).'): '.$e->getMessage());
    } catch (Throwable $t) {
      $errorOut = $t->getMessage();
      error_log('Verify mail throwable (try '.($i+1).'): '.$t->getMessage());
    }
  }
  return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['action']) && $_POST['action'] === 'resend') {
    try {
      $q = $pdo->prepare("SELECT user_id, isVerified, otp_last_sent_at FROM users WHERE email = ? LIMIT 1");
      $q->execute([$email]);
      $u = $q->fetch(PDO::FETCH_ASSOC);

      if (!$u) {
        $msg = 'Account not found.';
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? json_response(['success'=>false,'message'=>$msg]) : ($notification=['message'=>$msg,'type'=>'error']);
      }
      if ((int)$u['isVerified'] === 1) {
        $msg = 'This account is already verified.';
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? json_response(['success'=>false,'message'=>$msg]) : ($notification=['message'=>$msg,'type'=>'error']);
      }

      $last = $u['otp_last_sent_at'] ? strtotime($u['otp_last_sent_at']) : 0;
      $now  = time();
      if ($now - $last < 60) {
        $msg = "Please wait ".(60 - ($now - $last))."s before requesting another code.";
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? json_response(['success'=>false,'message'=>$msg]) : ($notification=['message'=>$msg,'type'=>'error']);
      }

      $otp = sprintf("%06d", random_int(100000, 999999));
      $otp_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

      $stmt = $pdo->prepare("
        UPDATE users
           SET otp_code=:otp, otp_purpose='EMAIL_VERIFICATION', otp_expires_at=:exp,
               otp_created_at=NOW(), otp_is_used=0, otp_last_sent_at=NOW(),
               otp_attempts=COALESCE(otp_attempts,0)+1
         WHERE email=:email AND (isVerified=0 OR isVerified IS NULL)
      ");
      $stmt->execute([':otp'=>$otp, ':exp'=>$otp_expires, ':email'=>$email]);

      $mailErr = null;
      if (!send_verification_email($email, $otp, $mailErr)) {
        $msg = 'Failed to resend OTP. Please try again.';
        error_log('Resend OTP failed: '.$mailErr);
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? json_response(['success'=>false,'message'=>$msg]) : ($notification=['message'=>$msg,'type'=>'error']);
      }

      if (DEV_ECHO_OTP) $_SESSION['__DEBUG_LAST_OTP'] = $otp;
      return isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? json_response(['success'=>true,'message'=>'OTP resent successfully']) : ($notification=['message'=>'OTP resent successfully','type'=>'success']);

    } catch (Throwable $e) {
      error_log('Verify-email resend DB error: ' . $e->getMessage());
      $msg = 'Database error. Please try again.';
      return isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? json_response(['success'=>false,'message'=>$msg]) : ($notification=['message'=>$msg,'type'=>'error']);
    }
  } else {
    // VERIFY OTP
    $otp = trim($_POST['otp'] ?? '');
    if ($otp === '') {
      $notification = ['message' => 'Please enter the OTP', 'type' => 'error'];
    } elseif (!preg_match('/^\d{6}$/', $otp)) {
      $notification = ['message' => 'OTP must be a 6-digit number', 'type' => 'error'];
    } else {
      try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
          UPDATE users
             SET isVerified=1, isActive=1, otp_is_used=1,
                 otp_code=NULL, otp_expires_at=NULL, otp_purpose=NULL
           WHERE email=:email
             AND otp_code=:otp
             AND otp_purpose='EMAIL_VERIFICATION'
             AND otp_is_used=0
             AND otp_expires_at >= NOW()
        ");
        $stmt->execute([':email'=>$email, ':otp'=>$otp]);
        if ($stmt->rowCount() === 1) {
          $pdo->commit();
          unset($_SESSION['signup_email'], $_SESSION['signup_user_id'], $_SESSION['__DEBUG_LAST_OTP']);
          $notification = ['message'=>'Email verified successfully! Redirecting to login','type'=>'success'];
          // header("Location: login.php"); exit; // uncomment when ready
        } else {
          $pdo->rollBack();
          $notification = ['message'=>'Invalid or expired OTP', 'type'=>'error'];
        }
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Verify-email verify DB error: '.$e->getMessage());
        $notification = ['message'=>'Database error. Please try again.', 'type'=>'error'];
      }
    }
  }
}

// In your verify-email view, you can show dev OTP safely when DEV_ECHO_OTP is true:
if (DEV_ECHO_OTP && !empty($_SESSION['__DEBUG_LAST_OTP'])) {
  echo '<div style="margin:10px 0;padding:8px;border:1px dashed #999;color:#444;">DEV OTP: <strong>'.htmlspecialchars($_SESSION['__DEBUG_LAST_OTP']).'</strong></div>';
}

// Render the rest of your verify form and surface $notification.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Email</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --black: #1a1a1a;
      --white: #ffffff;
      --gray: #6b7280;
      --light-gray: #e5e7eb;
      --dark-gray: #4b5563;
      --shadow-sm: 0 2px 4px rgba(112, 28, 28, 0.05);
      --shadow-md: 0 6px 12px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 12px 24px rgba(0, 0, 0, 0.15);
      --transition: all 0.3s ease;
      --gradient: linear-gradient(135deg, #2c2c2c, #1a1a1a);
      --primary-color: var(--black);
      --primary-gradient: var(--gradient);
      --text-color: var(--black);
      --text-light: var(--gray);
      --bg-color: var(--white);
      --card-bg: rgba(255, 255, 255, 0.95);
      --border-color: var(--light-gray);
      --error-color: #dc2626;
      --success-color: #10b981; /* Green for success */
      --input-bg: #f9fafb;
      --input-bg-focus: #ffffff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', -apple-system, sans-serif;
    }

    body {
      background-color: var(--bg-color);
      color: var(--text-color);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow-x: hidden;
      position: relative;
    }

    .bg-circle {
      position: absolute;
      border-radius: 50%;
      z-index: -1;
    }

    .bg-circle-1 {
      width: 300px;
      height: 300px;
      background: linear-gradient(135deg, rgba(44, 44, 44, 0.15), rgba(26, 26, 26, 0.15));
      top: -100px;
      right: -100px;
    }

    .bg-circle-2 {
      width: 200px;
      height: 200px;
      background: linear-gradient(135deg, rgba(44, 44, 44, 0.12), rgba(26, 26, 26, 0.12));
      bottom: 0px;
      left: 10%;
    }

    .bg-circle-3 {
      width: 120px;
      height: 120px;
      background: linear-gradient(135deg, rgba(44, 44, 44, 0.1), rgba(26, 26, 26, 0.1));
      top: 20%;
      left: -50px;
    }

    .bg-circle-4 {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, rgba(44, 44, 44, 0.1), rgba(26, 26, 26, 0.1));
      bottom: 30%;
      right: 5%;
    }

    .button {
      background-color: var(--black);
      color: var(--white);
      padding: 14px 28px;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-align: center;
      display: inline-block;
      text-decoration: none;
    }

    .button:hover {
      background: var(--gradient);
      box-shadow: var(--shadow-lg);
      transform: translateY(-2px);
    }

    .button.secondary {
      background: var(--white);
      color: var(--black);
      border: 1px solid var(--black);
      text-decoration: none;
    }

    .button.secondary:hover {
      background: var(--light-gray);
      box-shadow: var(--shadow-md);
    }

    header {
      background-color: var(--white);
      padding: 20px 40px;
      position: sticky;
      top: 0;
      z-index: 100;
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }

    header.scrolled {
      padding: 15px 30px;
      box-shadow: var(--shadow-md);
    }

    nav {
      display: flex;
      align-items: center;
      max-width: 1280px;
      margin: 0 auto;
      justify-content: space-between;
    }

    nav h1 {
      font-size: 1.8rem;
      font-weight: 700;
      font-family: 'Poppins', sans-serif;
      background: var(--black);
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    nav ul {
      display: flex;
      list-style: none;
      align-items: center;
    }

    nav ul li {
      margin-left: 35px;
    }

    nav ul li a {
      color: var(--black);
      text-decoration: none;
      font-size: 15px;
      font-weight: 600;
      text-transform: uppercase;
      position: relative;
      transition: var(--transition);
    }

    nav ul li a:not(.button):hover {
      color: var(--dark-gray);
    }

    nav ul li a:not(.button)::before {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background-color: var(--black);
      transition: width 0.3s ease;
    }

    nav ul li a:not(.button):hover::before {
      width: 100%;
    }

    .menu-toggle {
      display: none;
      cursor: pointer;
      z-index: 1000;
      position: relative;
    }

    .bar {
      width: 28px;
      height: 3px;
      background-color: var(--dark-gray);
      margin: 6px 0;
      transition: var(--transition);
    }

    .menu-toggle.active .bar:nth-child(1) {
      transform: rotate(-45deg) translate(-6px, 6px);
    }

    .menu-toggle.active .bar:nth-child(2) {
      opacity: 0;
    }

    .menu-toggle.active .bar:nth-child(3) {
      transform: rotate(45deg) translate(-6px, -6px);
    }

    .verify-container {
      width: 100%;
      max-width: 500px;
      background: var(--card-bg);
      border-radius: 24px;
      box-shadow: var(--shadow-lg);
      overflow: hidden;
      position: relative;
      z-index: 1;
      margin: 50px auto;
      padding: 40px;
    }

    .form-title {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: 15px;
      color: var(--text-color);
      text-align: center;
    }

    .form-description {
      font-size: 16px;
      color: var(--text-light);
      margin-bottom: 25px;
      text-align: center;
      line-height: 1.5;
    }

    #notification {
      display: <?php echo $notification['message'] ? 'block' : 'none'; ?>;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 20px;
      text-align: center;
      font-size: 16px;
      font-weight: 500;
      color: var(--white);
      background-color: <?php echo $notification['type'] === 'error' ? 'var(--error-color)' : 'var(--success-color)'; ?>;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
      margin-left: auto;
      margin-right: auto;
    }

    .input-group {
      margin-bottom: 18px;
      position: relative;
    }

    .input-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
      color: var(--text-color);
      font-size: 15px;
      text-align: center;
    }

    .otp-input-wrapper {
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      background: rgba(0, 0, 0, 0.02);
      padding: 12px;
      border-radius: 12px;
      border: 1px solid var(--border-color);
    }

    .otp-inputs {
      display: flex;
      gap: 8px;
      justify-content: center;
      align-items: center;
    }

    .otp-inputs input {
      width: 55px;
      height: 55px;
      padding: 10px;
      font-size: 20px;
      font-weight: 600;
      border: 2px solid var(--border-color);
      border-radius: 8px;
      outline: none;
      transition: var(--transition);
      background-color: var(--input-bg);
      text-align: center;
      letter-spacing: 0;
    }

    .otp-inputs input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 4px rgba(26, 26, 26, 0.1);
      background-color: var(--input-bg-focus);
    }

    .btn-verify {
      width: 100%;
      padding: 12px;
      background: var(--primary-gradient);
      color: var(--white);
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
      position: relative;
      overflow: hidden;
      z-index: 1;
    }

    .btn-verify::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: var(--transition);
      z-index: -1;
    }

    .btn-verify:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.35);
    }

    .btn-verify:hover::before {
      left: 100%;
      transition: 0.7s;
    }

    .btn-verify:active {
      transform: translateY(0);
    }

    .btn-verify.loading {
      opacity: 0.8;
      pointer-events: none;
    }

    .btn-verify .spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: var(--white);
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }

    .btn-verify.loading .spinner {
      display: block;
    }

    .btn-verify.loading span {
      display: none;
    }

    .resend-link {
      text-align: center;
      margin-top: 20px;
      font-size: 15px;
      color: var(--text-light);
    }

    .resend-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .resend-link a:hover {
      color: var(--dark-gray);
      text-decoration: underline;
    }

    .footer {
      background: var(--gradient);
      color: var(--white);
      padding: 80px 20px;
    }

    .footer-container {
      max-width: 1280px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 3fr 1fr 1fr;
      gap: 40px;
      margin-bottom: 40px;
    }

    .brand-section {
      display: flex;
      flex-direction: column;
      gap: 25px;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 15px;
      font-size: 27px;
      font-weight: 800;
      color: var(--white);
    }

    .logo h1 {
      font-size: 1.8rem;
      font-weight: 700;
      font-family: 'Poppins', sans-serif;
      background: var(--white);
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .logo svg {
      width: 50px;
      height: 50px;
      border-radius: 10px;
      background-color: var(--white);
      padding: 10px;
      fill: var(--black);
    }

    .brand-description {
      font-size: 18px;
      color: var(--light-gray);
      max-width: 320px;
      font-weight: 400;
      line-height: 1.5;
    }

    .footer-column h4 {
      font-size: 18px;
      font-weight: 700;
      color: var(--white);
      margin-bottom: 25px;
      position: relative;
      padding-bottom: 12px;
    }

    .footer-column h4::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 50px;
      height: 3px;
      background-color: var(--white);
      border-radius: 2px;
    }

    .footer-links {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .footer-links a {
      color: var(--light-gray);
      text-decoration: none;
      font-size: 16px;
      transition: var(--transition);
      font-weight: 400;
    }

    .footer-links a:hover {
      color: var(--white);
      transform: translateX(5px);
    }

    .social-links {
      display: flex;
      gap: 15px;
      margin-bottom: 25px;
    }

    .social-link {
      width: 44px;
      height: 44px;
      background-color: var(--dark-gray);
      border-radius: 8px;
      display: flex;
      justify-content: center;
      align-items: center;
      color: var(--white);
      transition: var(--transition);
      font-size: 18px;
      text-decoration: none;
    }

    .social-link:hover {
      background-color: var(--white);
      color: var(--black);
      transform: translateY(-4px);
    }

    .footer-bottom {
      border-top: 1px solid var(--light-gray);
      padding-top: 25px;
      text-align: center;
    }

    .copyright {
      font-size: 16px;
      color: var(--light-gray);
      font-weight: 400;
    }

    .copyright a {
      color: var(--white);
      text-decoration: none;
    }

    .copyright a:hover {
      text-decoration: underline;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    @keyframes ripple {
      to { transform: scale(4); opacity: 0; }
    }

    .ripple {
      position: absolute;
      width: 100px;
      height: 100px;
      background-color: rgba(255, 255, 255, 0.7);
      border-radius: 50%;
      transform: scale(0);
      animation: ripple 0.6s linear;
      pointer-events: none;
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }

    .error-shake {
      animation: shake 0.5s cubic-bezier(.36, .07, .19, .97) both;
    }

    @media (max-width: 768px) {
      header {
        padding: 15px 20px;
      }

      nav ul {
        position: fixed;
        top: 0;
        right: -100%;
        width: 70%;
        height: 100vh;
        background: var(--primary-gradient);
        flex-direction: column;
        justify-content: center;
        align-items: center;
        transition: right 0.5s ease;
        z-index: 999;
      }

      nav ul.active {
        right: 0;
      }

      nav ul li {
        margin: 25px 0;
      }

      nav ul li a {
        color: var(--white);
        font-size: 18px;
      }

      .menu-toggle {
        display: block;
      }

      .verify-container {
        max-width: 90%;
        margin: 20px auto;
        padding: 30px;
      }

      .otp-inputs input {
        width: 45px;
        height: 45px;
        font-size: 18px;
      }

      .otp-input-wrapper {
        padding: 10px;
      }

      .otp-inputs {
        gap: 6px;
      }

      .footer {
        padding: 50px 20px;
      }

      .footer-container {
        grid-template-columns: 1fr;
        text-align: center;
      }

      .footer-column h4::after {
        left: 50%;
        transform: translateX(-50%);
      }

      .footer-links {
        align-items: center;
      }

      .logo {
        flex-direction: column;
        text-align: center;
      }
    }

    @media (max-width: 576px) {
      .verify-container {
        padding: 20px;
      }

      .form-title {
        font-size: 20px;
      }

      .form-description {
        font-size: 14px;
      }

      .input-group label {
        font-size: 14px;
      }

      .otp-inputs input {
        width: 40px;
        height: 40px;
        font-size: 16px;
        padding: 8px;
      }

      .otp-input-wrapper {
        padding: 8px;
      }

      .otp-inputs {
        gap: 5px;
      }

      .btn-verify {
        padding: 12px;
        font-size: 15px;
      }

      .resend-link {
        font-size: 14px;
      }

      .footer {
        padding: 40px 15px;
      }

      .copyright {
        font-size: 14px;
      }
    }

    @media (max-width: 480px) {
      .verify-container {
        max-width: 95%;
        border-radius: 16px;
        padding: 15px;
      }

      .form-title {
        font-size: 18px;
        margin-bottom: 10px;
      }

      .form-description {
        font-size: 13px;
        margin-bottom: 15px;
      }

      .input-group {
        margin-bottom: 12px;
      }

      .input-group label {
        font-size: 13px;
        margin-bottom: 6px;
      }

      .otp-inputs input {
        width: 35px;
        height: 35px;
        font-size: 14px;
        padding: 6px;
      }

      .otp-input-wrapper {
        padding: 6px;
      }

      .otp-inputs {
        gap: 4px;
      }

      .btn-verify {
        padding: 10px;
        font-size: 14px;
      }

      .resend-link {
        font-size: 13px;
        margin-top: 15px;
      }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <nav>
	<br><br>
      <h1 style="font-family: 'Inter', 'Poppins', Arial, sans-serif;
           font-size: 2.4em;
           font-weight: 700;
           text-align: center;
           margin: 40px 0 5px 0;
           color: #222;
           letter-spacing: 1px;">
  <span style="color:#ff6600;">ChurnGuard</span> 
  <span style="font-weight: 400; color:#444;">Pro</span>
</h1>

<p style="text-align: center; 
          font-family: 'Inter', Arial, sans-serif;
          font-size: 0.9em; 
          color: #777; 
          margin-top: 0;">
  
</p><br><br><br><br>

      <div class="menu-toggle">
        <div class="bar"></div>
        <div class="bar"></div>
        <div class="bar"></div>
      </div>
  <ul style="list-style:none; margin:0; padding:14px 40px; display:flex; justify-content:flex-end; align-items:center; background:#fff; font-family:Arial, sans-serif; font-size:16px;">

  <li style="margin:0 20px;">
    <a href="landing.php" style="text-decoration:none; color:#000; padding:10px 12px; display:inline-block;">Home</a>
  </li>

  <li style="margin:0 20px;">
    <a href="landing.php" style="text-decoration:none; color:#000; padding:10px 12px; display:inline-block;">About</a>
  </li>

  <li style="margin:0 20px;">
    <a href="landing.php" style="text-decoration:none; color:#000; padding:10px 12px; display:inline-block;">Contact</a>
  </li>

  <li class="dropdown" style="position:relative; margin:0 20px;">
    <a href="#" id="userMenu" style="color:#000; font-size:18px; padding:8px 12px; display:inline-block;">
      <i class="fas fa-user"></i>
    </a>
    <div class="dropdown-content" style="display:none; position:absolute; right:0; top:45px; background:#fff; border-radius:8px; min-width:160px; box-shadow:0 6px 15px rgba(0,0,0,0.15); overflow:hidden;">
      <a href="login.php" style="display:block; padding:12px 15px; color:#000; text-decoration:none;">Login</a>
      <a href="signup.php" style="display:block; padding:12px 15px; color:#000; text-decoration:none;">Sign Up</a>
    </div>
  </li>
</ul>

<script>
  // Toggle dropdown on click
  document.querySelectorAll('.dropdown > a').forEach(function(button){
    button.addEventListener('click', function(e){
      e.preventDefault();
      let dropdown = this.nextElementSibling;

      // Close other dropdowns first
      document.querySelectorAll('.dropdown-content').forEach(function(dc){
        if(dc !== dropdown) dc.style.display = 'none';
      });

      // Toggle clicked dropdown
      dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
    });
  });

  // Close dropdown if click outside
  document.addEventListener('click', function(e){
    if(!e.target.closest('.dropdown')){
      document.querySelectorAll('.dropdown-content').forEach(function(dc){
        dc.style.display = 'none';
      });
    }
  });
</script>


    </nav>
 
  </header>

  <!-- Verify Email Section -->
  <div class="verify-container">
    <h2 class="form-title">Verify Your Email</h2>
    <p class="form-description">
      We've sent a 6-digit OTP to <strong><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></strong>. 
      Please enter it below to verify your account.
    </p>
    <div id="notification"><?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?></div>
    <form id="verifyForm" method="post" action="">
      <div class="input-group">
        <label for="otp1">Enter OTP</label>
        <div class="otp-input-wrapper">
          <div class="otp-inputs">
            <input type="text" id="otp1" name="otp1" maxlength="1" aria-label="OTP digit 1" required>
            <input type="text" id="otp2" name="otp2" maxlength="1" aria-label="OTP digit 2" required>
            <input type="text" id="otp3" name="otp3" maxlength="1" aria-label="OTP digit 3" required>
            <input type="text" id="otp4" name="otp4" maxlength="1" aria-label="OTP digit 4" required>
            <input type="text" id="otp5" name="otp5" maxlength="1" aria-label="OTP digit 5" required>
            <input type="text" id="otp6" name="otp6" maxlength="1" aria-label="OTP digit 6" required>
          </div>
          <input type="hidden" id="otp" name="otp">
        </div>
      </div>
      <button type="submit" class="btn-verify">
        <span>Verify Email</span>
        <div class="spinner"></div>
      </button>
      <div class="resend-link">
        Didn't receive the OTP? <a href="#" id="resendOtp">Resend OTP</a>
      </div>
    </form>
  </div>

  <!-- Footer -->
<footer class="footer">
  <div class="footer-container">
    <div class="brand-section">
      <div class="logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 3v18h18v-18h-18zm9 16c-3.86 0-7-3.14-7-7s3.14-7 7-7 7 3.14 7 7-3.14 7-7 7z"/>
          <path d="M8.5 8.5l7 7m0-7l-7 7"/>
        </svg>
        <h1>ChurnGuard Analytics</h1>
      </div>
      <p class="brand-description">Empowering businesses with AI-driven customer retention insights since 2024. Transform your churn prediction strategy with XGBoost technology.</p>
      <div class="social-links">
        <a href="#" class="social-link" aria-label="LinkedIn">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
          </svg>
        </a>
        <a href="#" class="social-link" aria-label="GitHub">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
          </svg>
        </a>
        <a href="#" class="social-link" aria-label="Twitter">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
          </svg>
        </a>
      </div>
    </div>
    
    <div class="footer-column">
      <h4>Platform</h4>
      <div class="footer-links">
        <a href="#home">Dashboard</a>
        <a href="#about">About</a>
        <a href="#analytics">Analytics</a>
        <a href="#contact">Demo</a>
      </div>
    </div>
    
    <div class="footer-column">
      <h4>Solutions</h4>
      <div class="footer-links">
        <a href="#">XGBoost Models</a>
        <a href="#">Real-time Analytics</a>
        <a href="#">API Integration</a>
        <a href="#">Custom Reports</a>
      </div>
    </div>
    
    <div class="footer-column">
      <h4>Resources</h4>
      <div class="footer-links">
        <a href="#">Documentation</a>
        <a href="#">API Reference</a>
        <a href="#">Case Studies</a>
        <a href="#">Support</a>
      </div>
    </div>
  </div>
  
  <div class="footer-bottom">
    <div class="footer-bottom-content">
      <div class="copyright">
        © 2024 <a href="#">ChurnGuard Analytics</a>. All Rights Reserved.
      </div>
      <div class="legal-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Data Security</a>
      </div>
    </div>
  </div>
</footer>

<style>
.footer {
  background: #0f172a;
  color: #e2e8f0;
  padding: 60px 0 0 0;
  margin-top: 80px;
}

.footer-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 50px;
  margin-bottom: 40px;
}

.brand-section {
  max-width: 350px;
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.logo svg {
  width: 32px;
  height: 32px;
  color: #3b82f6;
}

.logo h1 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #ffffff;
  margin: 0;
}

.brand-description {
  color: #94a3b8;
  line-height: 1.6;
  margin-bottom: 25px;
  font-size: 0.95rem;
}

.social-links {
  display: flex;
  gap: 12px;
}

.social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  background: #1e293b;
  border: 1px solid #334155;
  border-radius: 8px;
  color: #94a3b8;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-link:hover {
  background: #3b82f6;
  border-color: #3b82f6;
  color: #ffffff;
  transform: translateY(-2px);
}

.social-link svg {
  width: 18px;
  height: 18px;
}

.footer-column h4 {
  color: #ffffff;
  font-size: 1.1rem;
  font-weight: 600;
  margin-bottom: 20px;
}

.footer-links {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.footer-links a {
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.95rem;
  transition: color 0.2s ease;
}

.footer-links a:hover {
  color: #3b82f6;
}

.footer-bottom {
  border-top: 1px solid #1e293b;
  padding: 25px 0;
}

.footer-bottom-content {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 20px;
}

.copyright {
  color: #64748b;
  font-size: 0.9rem;
}

.copyright a {
  color: #3b82f6;
  text-decoration: none;
}

.copyright a:hover {
  text-decoration: underline;
}

.legal-links {
  display: flex;
  gap: 25px;
}

.legal-links a {
  color: #64748b;
  text-decoration: none;
  font-size: 0.9rem;
  transition: color 0.2s ease;
}

.legal-links a:hover {
  color: #3b82f6;
}

@media (max-width: 968px) {
  .footer-container {
    grid-template-columns: 1fr 1fr;
    gap: 40px;
  }
  
  .brand-section {
    max-width: none;
  }
}

@media (max-width: 640px) {
  .footer-container {
    grid-template-columns: 1fr;
    gap: 35px;
  }
  
  .footer {
    padding: 40px 0 0 0;
  }
  
  .footer-bottom-content {
    flex-direction: column;
    text-align: center;
    gap: 15px;
  }
  
  .legal-links {
    gap: 20px;
  }
}

@media (max-width: 480px) {
  .social-links {
    justify-content: center;
  }
  
  .legal-links {
    flex-wrap: wrap;
    justify-content: center;
    gap: 15px;
  }
}
</style>

  <script>
    // Mobile menu toggle
    const toggleMenu = () => {
      const menu = document.querySelector('nav ul');
      const toggle = document.querySelector('.menu-toggle');
      menu.classList.toggle('active');
      toggle.classList.toggle('active');
    };
    document.querySelector('.menu-toggle').addEventListener('click', toggleMenu);

    // Header Scroll Effect
    window.addEventListener('scroll', function() {
      const header = document.querySelector('header');
      header.classList.toggle('scrolled', window.scrollY > 0);
    });

    // Smooth Scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth'
          });
        }
        const menu = document.querySelector('nav ul');
        const toggle = document.querySelector('.menu-toggle');
        menu.classList.remove('active');
        toggle.classList.remove('active');
      });
    });

    // OTP input handling
    const otpInputs = document.querySelectorAll('.otp-inputs input:not([type=hidden])');
    const hiddenOtpInput = document.getElementById('otp');
    const form = document.getElementById('verifyForm');
    const notification = document.getElementById('notification');
    const submitButton = document.querySelector('.btn-verify');

    otpInputs.forEach((input, index) => {
      input.addEventListener('input', function(e) {
        const value = e.target.value;

        // Only allow digits
        if (!/^\d$/.test(value) && value !== '') {
          e.target.value = '';
          return;
        }

        // Update hidden OTP input
        updateHiddenOTP();

        // Move to next input if a digit is entered
        if (value && index < otpInputs.length - 1) {
          otpInputs[index + 1].focus();
        }

        // Auto-submit if last input is filled
        if (index === otpInputs.length - 1 && value && hiddenOtpInput.value.length === 6) {
          submitButton.classList.add('loading');
          form.submit();
        }
      });

      input.addEventListener('keydown', function(e) {
        const value = e.target.value;

        // Move to previous input on backspace if empty
        if (e.key === 'Backspace' && !value && index > 0) {
          otpInputs[index - 1].focus();
        }

        // Handle paste event
        if (e.key === 'v' && e.ctrlKey) {
          navigator.clipboard.readText().then(text => {
            if (/^\d{6}$/.test(text)) {
              text.split('').forEach((digit, i) => {
                if (i < otpInputs.length) {
                  otpInputs[i].value = digit;
                }
              });
              otpInputs[otpInputs.length - 1].focus();
              updateHiddenOTP();
              // Auto-submit after paste
              if (hiddenOtpInput.value.length === 6) {
                submitButton.classList.add('loading');
                form.submit();
              }
            }
          });
        }
      });

      // Prevent non-numeric input
      input.addEventListener('keypress', function(e) {
        if (!/^\d$/.test(e.key)) {
          e.preventDefault();
        }
      });
    });

    function updateHiddenOTP() {
      const otp = Array.from(otpInputs).map(input => input.value).join('');
      hiddenOtpInput.value = otp;
    }

    // Form submission with client-side validation
    form.addEventListener('submit', function(e) {
      const otp = hiddenOtpInput.value;

      if (!otp) {
        e.preventDefault();
        notification.textContent = 'Please enter the OTP';
        notification.style.backgroundColor = 'var(--error-color)';
        notification.style.display = 'block';
        otpInputs.forEach(input => input.classList.add('error-shake'));
        setTimeout(() => {
          otpInputs.forEach(input => input.classList.remove('error-shake'));
          notification.style.display = 'none';
        }, 1000);
        return;
      }

      if (!/^\d{6}$/.test(otp)) {
        e.preventDefault();
        notification.textContent = 'OTP must be a 6-digit number';
        notification.style.backgroundColor = 'var(--error-color)';
        notification.style.display = 'block';
        otpInputs.forEach(input => input.classList.add('error-shake'));
        setTimeout(() => {
          otpInputs.forEach(input => input.classList.remove('error-shake'));
          notification.style.display = 'none';
        }, 1000);
        return;
      }

      // Show loading state
      submitButton.classList.add('loading');
    });

    // Resend OTP via AJAX
    document.getElementById('resendOtp').addEventListener('click', function(e) {
      e.preventDefault();
      const resendLink = this;
      resendLink.style.pointerEvents = 'none';
      resendLink.textContent = 'Sending...';

      const formData = new FormData();
      formData.append('action', 'resend');
      formData.append('email', '<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>');

      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(data => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(data, 'text/html');
        const newNotification = doc.getElementById('notification');
        notification.textContent = newNotification.textContent;
        notification.style.backgroundColor = newNotification.style.backgroundColor;
        notification.style.display = 'block';
        setTimeout(() => { notification.style.display = 'none'; }, 5000);
        resendLink.style.pointerEvents = 'auto';
        resendLink.textContent = 'Resend OTP';
        // Clear OTP inputs after resend
        otpInputs.forEach(input => input.value = '');
        hiddenOtpInput.value = '';
        otpInputs[0].focus();
      })
      .catch(error => {
        console.error('Resend OTP error:', error);
        notification.textContent = 'Failed to resend OTP. Please try again.';
        notification.style.backgroundColor = 'var(--error-color)';
        notification.style.display = 'block';
        setTimeout(() => { notification.style.display = 'none'; }, 5000);
        resendLink.style.pointerEvents = 'auto';
        resendLink.textContent = 'Resend OTP';
      });
    });

    // Add ripple effect to buttons
    document.querySelector('.btn-verify').addEventListener('click', function(e) {
      const ripple = document.createElement('span');
      ripple.classList.add('ripple');
      this.appendChild(ripple);

      const x = e.clientX - e.target.getBoundingClientRect().left;
      const y = e.clientY - e.target.getBoundingClientRect().top;

      ripple.style.left = `${x}px`;
      ripple.style.top = `${y}px`;

      setTimeout(() => {
        ripple.remove();
      }, 600);
    });

    // Auto-focus first OTP input and handle success redirect
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('otp1').focus();
      const notification = document.getElementById('notification');
      if (notification.textContent === 'Email verified successfully! Redirecting to login') {
        setTimeout(function() {
          window.location.href = 'login.php';
        }, 3000); // 3-second delay
      }
    });
  </script>
</body>
</html>