<?php
// /churnguard-pro/auth/signup.php
declare(strict_types=1);

require_once __DIR__ . '/../connection/config.php';
session_start();

require_once __DIR__ . '/../PHPMailer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ---------- SMTP + Dev Settings (edit as needed) ---------- */
const SMTP_HOST          = 'smtp.gmail.com';
const SMTP_USER          = 'ysl.aether.bank@gmail.com';
const SMTP_PASS          = 'bsnn jagm stvw isqk'; // Gmail App Password
const SMTP_FROM_EMAIL    = 'ysl.aether.bank@gmail.com'; // MUST match SMTP_USER for Gmail/DMARC
const SMTP_FROM_NAME     = 'CHURNGUARD';

// For local/dev ONLY. If true, we print the OTP on verify page via session.
const DEV_ECHO_OTP       = false; // set true only when developing
// Some hosts have broken CA bundles; allow self-signed in dev:
const SMTP_ALLOW_SELF_SIGNED = true;

/* ---------- reCAPTCHA ---------- */
define('RECAPTCHA_SITE_KEY',  '6LdkHdsrAAAAABhWQHWpWIAFftSI5oRNX_QQMezg');
define('RECAPTCHA_SECRET_KEY','6LdkHdsrAAAAAF-3Zv0XK8YCb3KGes9M05G06o6w');

$notification = ['message' => '', 'type' => ''];
$firstname = $lastname = $email = $username = $address = $phone = '';

function verifyRecaptcha($recaptcha_response): bool {
  if (!$recaptcha_response) return false;
  $postFields = http_build_query([
    'secret'   => RECAPTCHA_SECRET_KEY,
    'response' => $recaptcha_response,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null
  ]);
  $ok = false;

  if (ini_get('allow_url_fopen')) {
    $ctx = stream_context_create([
      'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => $postFields,
        'timeout' => 10,
      ]
    ]);
    $resp = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    if ($resp !== false) $ok = !empty(json_decode($resp, true)['success']);
  }
  if (!$ok && function_exists('curl_init')) {
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $postFields,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if ($resp !== false) $ok = !empty(json_decode($resp, true)['success']);
  }
  return $ok;
}

/* ---------- Robust mail sender ---------- */
function send_verification_email(string $toEmail, string $otp, ?string &$errorOut = null): bool {
  $attempts = [
    ['secure' => PHPMailer::ENCRYPTION_STARTTLS, 'port' => 587],
    ['secure' => PHPMailer::ENCRYPTION_SMTPS,    'port' => 465],
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

      // Force IPv4 on some hosts (DNS64/IPv6 issues)
      if (function_exists('gethostbyname')) {
        $ipv4 = gethostbyname(SMTP_HOST);
        if ($ipv4 && $ipv4 !== SMTP_HOST) $mail->Host = $ipv4;
      }

      if (SMTP_ALLOW_SELF_SIGNED) {
        $mail->SMTPOptions = [
          'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
          ],
        ];
      }

      // Capture debug to error_log for diagnosis
      $mail->SMTPDebug  = 0; // set 2 or 3 temporarily if you need verbose logs
      $mail->Debugoutput = function ($str) {
        error_log('PHPMailer: ' . trim($str));
      };

      // From must match Gmail user to satisfy DMARC
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
                  Verify your email
                </h2>
                <p style='margin:0 0 16px 0;font-family:Inter,Segoe UI,Arial,sans-serif;font-size:14px;line-height:1.6;color:#3b3f4a;'>
                  Use the One-Time Password (OTP) below to complete your ChurnGuard signup. For your security, never share this code with anyone.
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
                  Need help? Email us at
                  <a href='mailto:ysl.aether.bank@gmail.com' style='color:#ff6a00;text-decoration:underline;'>ysl.aether.bank@gmail.com</a>
                  or call <a href='tel:09120091223' style='color:#ff6a00;text-decoration:underline;'>09120091223</a>.
                </p>
              </td>
            </tr>

            <!-- Footer -->
            <tr>
              <td style='padding:16px 24px;border-top:1px solid #eef0f5;background:#fafbff;'>
                <p style='margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;font-size:12px;color:#6b7280;line-height:1.5;'>
                  You received this email because someone tried to sign up for ChurnGuard with this address. If this wasn’t you, you can ignore this message.
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
      error_log('Signup mail send error (try '.($i+1).'): '.$e->getMessage());
      // Try next transport
    } catch (Throwable $t) {
      $errorOut = $t->getMessage();
      error_log('Signup mail throwable (try '.($i+1).'): '.$t->getMessage());
    }
  }
  return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $firstname = trim($_POST['firstname'] ?? '');
  $lastname  = trim($_POST['lastname'] ?? '');
  $email     = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
  $username  = trim($_POST['username'] ?? '');
  $address   = trim($_POST['address'] ?? '');
  $phone     = trim($_POST['phone'] ?? '');
  $password  = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm-password'] ?? '';
  $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

  if (
    $firstname === '' || $lastname === '' || $email === '' || $username === '' ||
    $password === '' || $confirm_password === '' || $address === '' || $phone === ''
  ) {
    $notification = ['message' => 'Please fill in all fields', 'type' => 'error'];
  } elseif ($recaptcha_response === '') {
    $notification = ['message' => 'Please complete the reCAPTCHA verification', 'type' => 'error'];
  } elseif (!verifyRecaptcha($recaptcha_response)) {
    $notification = ['message' => 'reCAPTCHA verification failed. Please try again.', 'type' => 'error'];
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $notification = ['message' => 'Invalid email format', 'type' => 'error'];
  } elseif ($password !== $confirm_password) {
    $notification = ['message' => 'Passwords do not match', 'type' => 'error'];
  } elseif (strlen($password) < 8) {
    $notification = ['message' => 'Password must be at least 8 characters long', 'type' => 'error'];
  } elseif (!isset($_POST['terms'])) {
    $notification = ['message' => 'You must agree to the Terms of Service and Privacy Policy', 'type' => 'error'];
  } else {
    try {
      if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection not initialized.');
      }

      // Check email
      $stmt = $pdo->prepare("SELECT user_id, isVerified FROM users WHERE email = ? LIMIT 1");
      $stmt->execute([$email]);
      $existingByEmail = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($existingByEmail && (int)$existingByEmail['isVerified'] === 1) {
        $notification = ['message' => 'Email already registered. Please log in.', 'type' => 'error'];
      } else {
        // Check username uniqueness (allow if same unverified record)
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $existingByUsername = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existingByUsername && (!$existingByEmail || (int)$existingByUsername['user_id'] !== (int)$existingByEmail['user_id'])) {
          $notification = ['message' => 'Username already taken', 'type' => 'error'];
        } else {
          $otp         = sprintf("%06d", random_int(100000, 999999));
          $otp_expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
          $hashed      = password_hash($password, PASSWORD_DEFAULT);

          if ($existingByEmail) {
            $sql = "
              UPDATE users
                 SET firstname=:fn, lastname=:ln, username=:un, address=:addr, phone=:ph,
                     password=:pwd, otp_code=:otp, otp_purpose='EMAIL_VERIFICATION',
                     otp_expires_at=:exp, otp_created_at=NOW(), otp_last_sent_at=NOW(),
                     otp_attempts=COALESCE(otp_attempts,0)+1, isActive=0, isVerified=0
               WHERE user_id=:uid AND (isVerified=0 OR isVerified IS NULL)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
              ':fn'=>$firstname, ':ln'=>$lastname, ':un'=>$username, ':addr'=>$address, ':ph'=>$phone,
              ':pwd'=>$hashed, ':otp'=>$otp, ':exp'=>$otp_expires, ':uid'=>(int)$existingByEmail['user_id']
            ]);
            $userId = (int)$existingByEmail['user_id'];
          } else {
            $sql = "
              INSERT INTO users
                (firstname, lastname, email, username, password, address, phone,
                 otp_code, otp_purpose, otp_expires_at, otp_created_at, otp_last_sent_at,
                 otp_attempts, isActive, isVerified, created_at)
              VALUES
                (:fn, :ln, :em, :un, :pwd, :addr, :ph,
                 :otp, 'EMAIL_VERIFICATION', :exp, NOW(), NOW(),
                 1, 0, 0, NOW())
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
              ':fn'=>$firstname, ':ln'=>$lastname, ':em'=>$email, ':un'=>$username,
              ':pwd'=>$hashed, ':addr'=>$address, ':ph'=>$phone,
              ':otp'=>$otp, ':exp'=>$otp_expires
            ]);
            $userId = (int)$pdo->lastInsertId();
          }

          // Try to send email (with fallback transport)
          $mailErr = null;
          $sent = send_verification_email($email, $otp, $mailErr);

          // Persist for verify step regardless; user can Resend if send failed
          $_SESSION['signup_email']   = $email;
          $_SESSION['signup_user_id'] = $userId;
          if (DEV_ECHO_OTP) $_SESSION['__DEBUG_LAST_OTP'] = $otp;

          if (!$sent) {
            error_log('OTP send failed: '.$mailErr);
            // still move to verify page; they can press "Resend"
            header("Location: verify-email.php?sent=0");
            exit;
          }

          header("Location: verify-email.php?sent=1");
          exit;
        }
      }

    } catch (Throwable $e) {
      error_log("Signup error: " . $e->getMessage());
      $notification = ['message' => 'Database error: ' . $e->getMessage(), 'type' => 'error'];
    }
  }
}

// Render your signup form and surface $notification if set.
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Churn Prediction System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- <link rel="stylesheet" href="sign.css"> -->
  <!-- Google reCAPTCHA -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
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
    --success-color: var(--dark-gray);
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

  /* Main Container */
.signup-container {
    width: 100%;
    height: 100vh;
    display: flex;
    background: linear-gradient(120deg, #eef2f7 0%, #f9fbfd 100%);
    font-family: 'Inter', 'Segoe UI', Roboto, sans-serif;
    overflow: hidden;
}

/* Left Panel */
.signup-left {
    flex: 1;
    padding: 80px;
    background: linear-gradient(145deg, #3a55d1, #2536a8);
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
}

/* Decorative background circles */
.signup-left::before,
.signup-left::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
    z-index: 0;
}
.signup-left::before {
    width: 320px;
    height: 320px;
    top: -80px;
    right: -80px;
}
.signup-left::after {
    width: 220px;
    height: 220px;
    bottom: -60px;
    left: -60px;
}

/* Icon Holder */
.brand-icon {
    width: 120px;
    height: 120px;
    background: #fff;
    border-radius: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 50px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
    position: relative;
    z-index: 1;
}
.brand-icon svg {
    width: 72px;
    height: 72px;
    stroke: #3a55d1;
}

/* Headings */
.signup-left h1 {
    font-size: 50px;
    font-weight: 800;
    margin-bottom: 22px;
    line-height: 1.2;
    letter-spacing: -0.5px;
    z-index: 1;
}
.signup-left p {
    font-size: 20px;
    line-height: 1.7;
    max-width: 480px;
    opacity: 0.95;
    z-index: 1;
}

/* Right Panel */
.signup-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 70px;
    background: #fff;
}

/* Signup Card */
.signup-card {
    width: 100%;
    max-width: 480px;
    background: #fff;
    border-radius: 24px;
    padding: 60px 50px;
    box-shadow: 0 20px 44px rgba(0, 0, 0, 0.1);
    border: 1px solid #eceef5;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.signup-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 28px 60px rgba(0, 0, 0, 0.15);
}

/* Card Title */
.signup-card h2 {
    font-size: 34px;
    font-weight: 700;
    margin-bottom: 36px;
    text-align: center;
    color: #1d1f2f;
}

/* Form */
.signup-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 22px;
}
.signup-form input,
.signup-form select {
    width: 100%;
    padding: 16px 18px;
    border: 1px solid #d4d8e3;
    border-radius: 14px;
    font-size: 16px;
    background: #fafbff;
    transition: all 0.3s ease;
}
.signup-form input:focus,
.signup-form select:focus {
    border-color: #3a55d1;
    background: #fff;
    box-shadow: 0 0 0 5px rgba(58, 85, 209, 0.15);
    outline: none;
}

/* Full Width Rows */
.signup-form .full-width {
    grid-column: span 2;
}

/* Button */
.signup-card button {
    width: 100%;
    padding: 17px;
    margin-top: 34px;
    background: linear-gradient(135deg, #3a55d1, #2536a8);
    color: #fff;
    border: none;
    border-radius: 16px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}
.signup-card button:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 34px rgba(58, 85, 209, 0.35);
}

/* Footer Links */
.form-footer {
    margin-top: 26px;
    text-align: center;
    font-size: 15px;
    color: #666;
}
.form-footer a {
    color: #3a55d1;
    text-decoration: none;
    font-weight: 500;
}
.form-footer a:hover {
    text-decoration: underline;
}

/* Responsive */
@media(max-width: 992px) {
    .signup-container {
        flex-direction: column;
    }
    .signup-left {
        display: none;
    }
    .signup-right {
        padding: 40px 24px;
    }
    .signup-card {
        max-width: 100%;
        padding: 50px 35px;
    }
    .signup-form {
        grid-template-columns: 1fr;
    }
}

  #notification {
    display: <?php echo $notification['message'] ? 'block' : 'none'; ?>;
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 14px;
    font-weight: 500;
    color: var(--white);
    background-color: <?php echo $notification['type'] === 'error' ? 'var(--error-color)' : 'var(--success-color)'; ?>;
    animation: fadeIn 0.4s;
  }

  .form-columns {
  display: flex;
  gap: 24px; /* a bit more breathing space */
  flex-wrap: wrap; /* responsive for smaller screens */
}

.form-column {
  flex: 1;
  min-width: 260px; /* ensures columns don’t shrink too much */
}

.input-group {
  margin-bottom: 20px; /* slightly more for better spacing */
  position: relative;
}

.input-group label {
  display: block;
  margin-bottom: 6px;
  font-weight: 600;
  color: var(--text-color);
  font-size: 14.5px;
  letter-spacing: 0.3px;
}

.input-field {
  position: relative;
  display: flex;
  align-items: center;
}

.input-field input {
  width: 100%;
  padding: 14px 16px 14px 42px;
  font-size: 15px;
  border: 2px solid var(--border-color);
  border-radius: 12px; /* smoother corners */
  outline: none;
  transition: var(--transition);
  background-color: var(--input-bg);
}

.input-field input:focus {
  border-color: var(--primary-color);
  box-shadow: 0 0 0 4px rgba(0, 0, 0, 0.04);
  background-color: var(--input-bg-focus);
}

.input-field .icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-light);
  font-size: 17px; /* slightly bigger for clarity */
  transition: var(--transition);
}

.input-field input:focus + .icon {
  color: var(--primary-color);
}

/* Password strength bar */
.password-strength {
  display: flex;
  height: 5px;
  margin-top: 8px;
  border-radius: 5px;
  overflow: hidden;
}

/* Checkbox & terms */
.terms-checkbox {
  display: flex;
  align-items: flex-start;
  margin: 15px 0 22px 0;
  padding: 10px 12px;
  border: 1px solid var(--border-color);
  border-radius: 8px;
  background: var(--input-bg);
}

.terms-checkbox input {
  margin-right: 10px;
  margin-top: 3px;
  accent-color: var(--primary-color);
  width: 16px;
  height: 16px;
}

/* Submit button */
.btn-signup {
  width: 100%;
  padding: 14px;
  background: var(--primary-gradient);
  color: var(--white);
  border: none;
  border-radius: 12px;
  font-size: 15.5px;
  font-weight: 600;
  cursor: pointer;
  transition: var(--transition);
  box-shadow: 0 5px 12px rgba(0, 0, 0, 0.25);
  position: relative;
  overflow: hidden;
  z-index: 1;
}

.btn-signup:hover {
  transform: translateY(-2.5px);
  box-shadow: 0 7px 16px rgba(0, 0, 0, 0.3);
}

.login-link {
  text-align: center;
  margin-top: 24px;
  font-size: 14.5px;
  color: var(--text-light);
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

  .brand-icon::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: var(--white);
    z-index: -1;
    opacity: 0.6;
    animation: pulse 2s infinite;
  }

  @keyframes pulse {
    0% { transform: scale(1); opacity: 0.6; }
    70% { transform: scale(1.3); opacity: 0; }
    100% { transform: scale(1.3); opacity: 0; }
  }

  @media (max-width: 992px) {
    .signup-container {
      max-width: 800px;
      margin: 20px auto;
    }
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

    .signup-container {
      flex-direction: column;
      max-width: 90%;
      margin: 20px auto;
    }

    .signup-left, .signup-right {
      width: 100%;
      padding: 30px;
    }

    .form-columns {
      flex-direction: column;
      gap: 0;
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
    /* .signup-left, .signup-right {
      padding: 15px;
    } */

    .signup-left, .signup-right {
      width: 100%;
      padding: 30px;
    }

    .signup-left h1 {
      font-size: 24px;
    }

    .signup-left p {
      font-size: 14px;
    }

    .form-title {
      font-size: 20px;
    }

    .input-group label {
      font-size: 14px;
    }

    .input-field input {
      padding: 12px 12px 12px 36px;
      font-size: 14px;
    }

    .input-field .icon {
      font-size: 14px;
      left: 10px;
    }

    .toggle-password {
      right: 10px;
    }

    .password-feedback {
      font-size: 11px;
    }

    .terms-checkbox label {
      font-size: 14px;
    }

    .btn-signup {
      padding: 12px;
      font-size: 15px;
    }

    .login-link {
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
    .signup-container {
      max-width: 90%;
      border-radius: 16px;
    }

    /* .signup-left {
      padding: 10px;
    } */

    .signup-left h1 {
      font-size: 20px;
      margin-bottom: 10px;
    }

    .signup-left p {
      font-size: 13px;
      margin-bottom: 20px;
    }

    .brand-icon {
      width: 50px;
      height: 50px;
    }

    .brand-icon svg {
      width: 25px;
      height: 25px;
    }

    /* .signup-right {
      padding: 10px;
    } */

    .signup-left, .signup-right {
      width: 100%;
      padding: 30px;
    }

    .form-title {
      font-size: 18px;
      margin-bottom: 15px;
    }

    .input-group {
      margin-bottom: 20px;
    }

    .input-group label {
      font-size: 14px;
      margin-bottom: 8px;
    }

    .input-field input {
      padding: 17px 12px 15px 40px;
      font-size: 15px;
    }

    .input-field .icon {
      font-size: 15px;
      left: 15px;
    }

    .icon {
      left: 10px;
      padding: 18px 0px 15px 0px;
    }

    .toggle-password {
      right: 8px;
    }

    .password-strength {
      height: 3px;
    }

    .password-feedback {
      font-size: 15px;
    }

    .terms-checkbox {
      margin-bottom: 15px;
    }

    .terms-checkbox label {
      font-size: 15px;
    }

    .terms-checkbox input {
      width: 15px;
      height: 15px;
    }

    .btn-signup {
      padding: 15px;
      font-size: 15px;
    }

    .login-link {
      font-size: 13px;
      margin-top: 15px;
    }

    .footer {
      padding: 50px 20px;
    }

    .footer-container {
      grid-template-columns: 1fr;
      text-align: center;
    }

    /* .footer-column h4::after {
      left: 50%;
      transform: translateX(-50%);
    } */

    .footer-links {
      align-items: center;
    }

    .logo {
      flex-direction: column;
      text-align: center;
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
<br><br>
  <!-- Signup Section -->
  <div class="signup-container">
    <div class="signup-left">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M13 2L3 14h7l-1 8 11-13h-7l1-9z"/>
			
        </svg>
      </div>
     <h1>XGBoost Churn Prediction</h1>
<p>Leverage advanced machine learning with <strong>XGBoost</strong> to accurately predict customer churn, gain insights, and make data-driven business decisions with confidence.</p>

    </div>
    <div class="signup-right">
      
      <div id="notification"><?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?></div>
      <form id="signupForm" method="post" action="">
        <div class="form-columns">
          <div class="form-column">
            <div class="input-group">
              <label for="firstname">First Name</label>
              <div class="input-field">
                <input type="text" id="firstname" name="firstname" placeholder="First Name" value="<?php echo isset($firstname) ? htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="address">Address</label>
              <div class="input-field">
                <input type="text" id="address" name="address" placeholder="Address" value="<?php echo isset($address) ? htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="email">Email Address</label>
              <div class="input-field">
                <input type="email" id="email" name="email" placeholder="your.email@example.com" value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="password">Password</label>
              <div class="input-field">
                <input type="password" id="password" name="password" placeholder="Choose a strong password" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                </div>
                <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                  <svg class="eye" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-off" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
              <div class="password-strength">
                <div class="strength-segment"></div>
                <div class="strength-segment"></div>
                <div class="strength-segment"></div>
                <div class="strength-segment"></div>
              </div>
              <div class="password-feedback">Password strength: Enter a password</div>
            </div>
          </div>
          <div class="form-column">
            <div class="input-group">
              <label for="lastname">Last Name</label>
              <div class="input-field">
                <input type="text" id="lastname" name="lastname" placeholder="Last Name" value="<?php echo isset($lastname) ? htmlspecialchars($lastname, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="phone">Phone Number</label>
              <div class="input-field">
                <input type="text" id="phone" name="phone" placeholder="09xxxxxxxxx" value="<?php echo isset($phone) ? htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                  </svg>
                </div>
              </div>
            </div>
            <div class="input-group">
              <label for="username">Username</label>
              <div class="input-field">
                <input type="text" id="username" name="username" placeholder="Choose a username" value="<?php echo isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                  </svg>
                </div>
              </div>
              <div class="username-feedback" style="font-size: 12px; margin-top: 6px; color: var(--text-light);"></div>
            </div>
            <div class="input-group">
              <label for="confirm-password">Confirm Password</label>
              <div class="input-field">
                <input type="password" id="confirm-password" name="confirm-password" placeholder="Confirm your password" required>
                <div class="icon">
                  <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                  </svg>
                </div>
                <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                  <svg class="eye" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                  <svg class="eye-off" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                    <line x1="1" y1="1" x2="23" y2="23"></line>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- reCAPTCHA -->
        <div class="recaptcha-container" style="margin: 20px 0; display: flex; justify-content: center;">
          <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div>
        </div>
        
        <div class="terms-checkbox">
          <input type="checkbox" id="terms" name="terms" required>
          <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
        </div>
        <button type="submit" class="btn-signup">
          <span>Create Account</span>
          <div class="spinner"></div>
        </button>
        <div class="login-link">
          Already have an account? <a href="login.php">Log in</a>
        </div>
      </form>
    </div>
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
      header.classList.toggle('scrolled', window.scrollY > 50);
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

    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
      button.addEventListener('click', function() {
        const input = this.parentNode.querySelector('input');
        const eyeIcon = this.querySelector('.eye');
        const eyeOffIcon = this.querySelector('.eye-off');
        if (input.type === 'password') {
          input.type = 'text';
          eyeIcon.style.display = 'none';
          eyeOffIcon.style.display = 'block';
        } else {
          input.type = 'password';
          eyeIcon.style.display = 'block';
          eyeOffIcon.style.display = 'none';
        }
        input.focus();
      });
    });

    // Password strength checker
    const passwordInput = document.getElementById('password');
    const strengthSegments = document.querySelectorAll('.strength-segment');
    const passwordFeedback = document.querySelector('.password-feedback');

    passwordInput.addEventListener('input', function() {
      const password = this.value;
      let strength = 0;
      let feedback = '';

      strengthSegments.forEach(segment => {
        segment.className = 'strength-segment';
      });

      if (password.length === 0) {
        feedback = 'Password strength: Enter a password';
      } else {
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        if (password.length < 8) {
          feedback = 'Password is too short';
          passwordFeedback.className = 'password-feedback weak';
          strengthSegments[0].className = 'strength-segment weak';
        } else if (strength <= 2) {
          feedback = 'Password strength: Weak';
          passwordFeedback.className = 'password-feedback weak';
          strengthSegments[0].className = 'strength-segment weak';
        } else if (strength <= 3) {
          feedback = 'Password strength: Medium';
          passwordFeedback.className = 'password-feedback medium';
          strengthSegments[0].className = 'strength-segment medium';
          strengthSegments[1].className = 'strength-segment medium';
        } else if (strength <= 4) {
          feedback = 'Password strength: Good';
          passwordFeedback.className = 'password-feedback medium';
          strengthSegments[0].className = 'strength-segment medium';
          strengthSegments[1].className = 'strength-segment medium';
          strengthSegments[2].className = 'strength-segment medium';
        } else {
          feedback = 'Password strength: Strong';
          passwordFeedback.className = 'password-feedback strong';
          strengthSegments.forEach(segment => {
            segment.className = 'strength-segment strong';
          });
        }
      }

      passwordFeedback.textContent = feedback;
    });

    // Username availability check with AJAX
    const usernameInput = document.getElementById('username');
    const usernameFeedback = document.querySelector('.username-feedback');
    let usernameTimer;

    usernameInput.addEventListener('input', function() {
      clearTimeout(usernameTimer);
      usernameFeedback.textContent = '';
      if (this.value.trim().length >= 3) {
        usernameTimer = setTimeout(() => {
          const xhr = new XMLHttpRequest();
          xhr.open('POST', '../functions/check-username.php', true);
          xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
          xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
              const response = JSON.parse(xhr.responseText);
              usernameFeedback.textContent = response.message;
              usernameFeedback.style.color = response.status === 'error' ? '#dc2626' : '#10b981';
            }
          };
          xhr.send('username=' + encodeURIComponent(this.value.trim()));
        }, 800);
      }
    });

    // Form submission with client-side validation
    // Form submission with client-side validation
    const form = document.getElementById('signupForm');
    const submitButton = form.querySelector('.btn-signup');
    const spinner = submitButton.querySelector('.spinner');
    const buttonText = submitButton.querySelector('span');

    form.addEventListener('submit', function(e) {
      // Basic client-side validation
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm-password').value;
      const terms = document.getElementById('terms').checked;
      const recaptcha = grecaptcha.getResponse();

      // Clear previous notifications
      const notification = document.getElementById('notification');
      notification.textContent = '';
      notification.className = '';

      // Validate passwords match
      if (password !== confirmPassword) {
        e.preventDefault();
        showNotification('Passwords do not match', 'error');
        return false;
      }

      // Validate password strength
      if (password.length < 8) {
        e.preventDefault();
        showNotification('Password must be at least 8 characters long', 'error');
        return false;
      }

      // Validate terms acceptance
      if (!terms) {
        e.preventDefault();
        showNotification('You must agree to the Terms of Service and Privacy Policy', 'error');
        return false;
      }

      // Validate reCAPTCHA
      if (!recaptcha) {
        e.preventDefault();
        showNotification('Please complete the reCAPTCHA verification', 'error');
        return false;
      }

      // Show loading state
      submitButton.disabled = true;
      spinner.style.display = 'block';
      buttonText.textContent = 'Creating Account...';
    });

    // Password confirmation validation
    const confirmPasswordInput = document.getElementById('confirm-password');
    confirmPasswordInput.addEventListener('input', function() {
      const password = passwordInput.value;
      const confirmPassword = this.value;
      const inputField = this.parentNode;

      if (confirmPassword.length > 0) {
        if (password === confirmPassword) {
          inputField.classList.remove('error');
          inputField.classList.add('success');
        } else {
          inputField.classList.remove('success');
          inputField.classList.add('error');
        }
      } else {
        inputField.classList.remove('success', 'error');
      }
    });

    // Email validation
    const emailInput = document.getElementById('email');
    emailInput.addEventListener('blur', function() {
      const email = this.value;
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      const inputField = this.parentNode;

      if (email.length > 0) {
        if (emailRegex.test(email)) {
          inputField.classList.remove('error');
          inputField.classList.add('success');
        } else {
          inputField.classList.remove('success');
          inputField.classList.add('error');
        }
      } else {
        inputField.classList.remove('success', 'error');
      }
    });

    // Phone number validation (Philippine format)
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function() {
      // Remove non-numeric characters except +
      let value = this.value.replace(/[^\d+]/g, '');
      
      // Format Philippine mobile number
      if (value.startsWith('09') && value.length <= 11) {
        this.value = value;
      } else if (value.startsWith('+639') && value.length <= 13) {
        this.value = value;
      } else if (value.startsWith('9') && value.length <= 10) {
        this.value = '0' + value;
      } else {
        // Limit to 11 digits for local format
        this.value = value.slice(0, 11);
      }
    });

    phoneInput.addEventListener('blur', function() {
      const phone = this.value;
      const phoneRegex = /^(09|\+639)\d{9}$/;
      const inputField = this.parentNode;

      if (phone.length > 0) {
        if (phoneRegex.test(phone)) {
          inputField.classList.remove('error');
          inputField.classList.add('success');
        } else {
          inputField.classList.remove('success');
          inputField.classList.add('error');
        }
      } else {
        inputField.classList.remove('success', 'error');
      }
    });

    // Real-time form validation for all required inputs
    const requiredInputs = form.querySelectorAll('input[required]');
    requiredInputs.forEach(input => {
      input.addEventListener('blur', function() {
        const inputField = this.parentNode;
        if (this.type !== 'email' && this.type !== 'password' && this.id !== 'phone') {
          if (this.value.trim().length > 0) {
            inputField.classList.remove('error');
            inputField.classList.add('success');
          } else {
            inputField.classList.remove('success');
            inputField.classList.add('error');
          }
        }
      });
    });

    // Notification function
    function showNotification(message, type) {
      const notification = document.getElementById('notification');
      notification.textContent = message;
      notification.className = type;
      notification.style.display = 'block';
      
      // Auto-hide after 5 seconds
      setTimeout(() => {
        notification.style.display = 'none';
      }, 5000);
    }

    // Show PHP notifications if they exist
    const phpNotification = document.getElementById('notification');
    if (phpNotification.textContent.trim()) {
      phpNotification.className = '<?php echo $notification["type"]; ?>';
      phpNotification.style.display = 'block';
      
      // Auto-hide after 5 seconds
      setTimeout(() => {
        phpNotification.style.display = 'none';
      }, 5000);
    }

    // Form input animations
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
      input.addEventListener('focus', function() {
        this.parentNode.classList.add('focused');
      });

      input.addEventListener('blur', function() {
        if (!this.value) {
          this.parentNode.classList.remove('focused');
        }
      });

      // Check if input has value on page load
      if (input.value) {
        input.parentNode.classList.add('focused');
      }
    });

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }

    // Character limits for inputs
    const firstnameInput = document.getElementById('firstname');
    const lastnameInput = document.getElementById('lastname');
    const usernameInputChar = document.getElementById('username');
    const addressInput = document.getElementById('address');

    [firstnameInput, lastnameInput].forEach(input => {
      input.addEventListener('input', function() {
        if (this.value.length > 50) {
          this.value = this.value.slice(0, 50);
        }
      });
    });

    usernameInputChar.addEventListener('input', function() {
      if (this.value.length > 30) {
        this.value = this.value.slice(0, 30);
      }
    });

    addressInput.addEventListener('input', function() {
      if (this.value.length > 255) {
        this.value = this.value.slice(0, 255);
      }
    });

    // Prevent spaces in username
    usernameInputChar.addEventListener('keypress', function(e) {
      if (e.key === ' ') {
        e.preventDefault();
      }
    });

    // Auto-capitalize names
    [firstnameInput, lastnameInput].forEach(input => {
      input.addEventListener('input', function() {
        this.value = this.value.replace(/\b\w/g, l => l.toUpperCase());
      });
    });
  </script>
</body>
</html>