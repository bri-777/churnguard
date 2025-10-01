<?php
require '../connection/config.php';
session_start();

$notification = ['message' => '', 'type' => ''];
$otp = '';
$new_password = '';
$confirm_password = '';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot-password.php");
    exit;
}
$email = $_SESSION['reset_email'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $otp              = trim($_POST['otp'] ?? '');
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($otp)) {
        $notification = ['message' => 'Please enter the OTP.', 'type' => 'error'];
    } elseif (!preg_match('/^\d{6}$/', $otp)) {
        $notification = ['message' => 'OTP must be 6 digits.', 'type' => 'error'];
    } elseif (empty($new_password) || empty($confirm_password)) {
        $notification = ['message' => 'Please enter both password fields.', 'type' => 'error'];
    } elseif ($new_password !== $confirm_password) {
        $notification = ['message' => 'Passwords do not match.', 'type' => 'error'];
    } elseif (strlen($new_password) < 8) {
        $notification = ['message' => 'Password must be at least 8 characters long.', 'type' => 'error'];
    } else {
        try {
            $pdo = getDBConnection();
            $pdo->beginTransaction();

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Atomic conditional update: only succeeds if OTP is correct, unexpired, unused, and for PASSWORD_RESET
            $stmt = $pdo->prepare("
                UPDATE users
                SET password = :pwd,
                    otp_is_used = 1,
                    otp_code = NULL,
                    otp_expires_at = NULL,
                    otp_purpose = NULL
                WHERE email = :email
                  AND otp_code = :otp
                  AND otp_purpose = 'PASSWORD_RESET'
                  AND otp_is_used = 0
                  AND otp_expires_at >= NOW()
            ");
            $stmt->execute([
                ':pwd'   => $hashed_password,
                ':email' => $email,
                ':otp'   => $otp
            ]);

            if ($stmt->rowCount() === 1) {
                $pdo->commit();
                unset($_SESSION['reset_email']);

                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    if (function_exists('ob_get_length') && ob_get_length()) { ob_clean(); }
                    echo json_encode([
                        'success'  => true,
                        'message'  => 'Password reset successfully. Redirecting to login...',
                        'redirect' => 'login.php'
                    ]);
                    exit;
                } else {
                    header("Location: login.php");
                    exit;
                }
            } else {
                $pdo->rollBack();
                $notification = ['message' => 'Invalid or expired OTP. Please request a new one.', 'type' => 'error'];
            }
        } catch (PDOException $e) {
            if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
            error_log("Password reset error: " . $e->getMessage());
            $notification = ['message' => 'An error occurred. Please try again.', 'type' => 'error'];
        }
    }

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        if (function_exists('ob_get_length') && ob_get_length()) { ob_clean(); }
        echo json_encode(['success' => false, 'message' => $notification['message']]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --black: #1a1a1a;
      --white: #ffffff;
      --gray: #6b7280;
      --light-gray: #e5e7eb;
      --dark-gray: #4b5563;
      --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
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
      --success-color: #8b5cf6;
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

    .reset-container {
      width: 100%;
      max-width: 460px;
      background: var(--card-bg);
      border: 1px solid var(--border-color);
      border-radius: 24px;
      box-shadow: var(--shadow-lg);
      padding: 48px 40px;
      position: relative;
      overflow: hidden;
      transition: var(--transition);
      z-index: 1;
      margin: auto;
      margin-top: 40px;
      margin-bottom: 40px;
    }

    .reset-container::before {
      content: '';
      position: absolute;
      top: -1px;
      left: 50%;
      transform: translateX(-50%);
      width: 100px;
      height: 6px;
      background: var(--primary-gradient);
      border-radius: 0 0 50px 50px;
      z-index: 5;
    }

    .reset-container:hover {
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
    }

    .reset-header {
      text-align: center;
      margin-bottom: 40px;
      position: relative;
    }

    .reset-header .brand-icon {
      width: 70px;
      height: 70px;
      background: var(--primary-gradient);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      box-shadow: 0 10px 20px rgba(26, 26, 26, 0.3);
      position: relative;
    }

    .reset-header .brand-icon::after {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: var(--primary-gradient);
      z-index: -1;
      opacity: 0.6;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { transform: scale(1); opacity: 0.6; }
      70% { transform: scale(1.3); opacity: 0; }
      100% { transform: scale(1.3); opacity: 0; }
    }

    .reset-header .brand-icon svg {
      width: 35px;
      height: 35px;
      fill: var(--white);
    }

    .reset-header h1 {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--text-color);
      letter-spacing: -0.5px;
    }

    .reset-header p {
      color: var(--text-light);
      font-size: 16px;
    }

    .input-group {
      margin-bottom: 24px;
      position: relative;
    }

    .input-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--text-color);
      font-size: 15px;
    }

    .input-field {
      position: relative;
    }

    .input-field input {
      width: 100%;
      padding: 16px 20px;
      padding-left: 50px;
      font-size: 16px;
      border: 2px solid var(--border-color);
      border-radius: 12px;
      outline: none;
      transition: var(--transition);
      background-color: var(--white);
    }

    .input-field input:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 4px rgba(26, 26, 26, 0.1);
    }

    .input-field .icon {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      font-size: 16px;
      transition: var(--transition);
    }

    .input-field input:focus + .icon {
      color: var(--primary-color);
    }

    .toggle-password {
      position: absolute;
      right: 18px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: var(--text-light);
      background: none;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2;
      transition: var(--transition);
    }

    .toggle-password:hover {
      color: var(--primary-color);
    }

    .input-field input[type="password"] {
      padding-right: 50px;
    }

    .btn-submit {
      width: 100%;
      padding: 16px;
      background: var(--primary-gradient);
      color: var(--white);
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      box-shadow: var(--shadow-md);
      position: relative;
      overflow: hidden;
      z-index: 1;
    }

    .btn-submit::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.7s ease;
      z-index: -1;
    }

    .btn-submit:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-lg);
    }

    .btn-submit:hover::before {
      left: 100%;
    }

    .btn-submit.loading {
      opacity: 0.8;
      pointer-events: none;
    }

    .btn-submit .spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      border-top-color: var(--white);
      animation: spin 1s linear infinite;
      margin: 0 auto;
    }

    .btn-submit.loading .spinner {
      display: block;
    }

    .btn-submit.loading span {
      display: none;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .back-link {
      text-align: center;
      margin-top: 32px;
      font-size: 15px;
      color: var(--text-light);
    }

    .back-link a {
      color: var(--primary-color);
      text-decoration: none;
      font-weight: 600;
      transition: var(--transition);
    }

    .back-link a:hover {
      color: var(--dark-gray);
      text-decoration: underline;
    }

    .notification {
      display: none;
      padding: 16px;
      border-radius: 12px;
      margin-bottom: 25px;
      text-align: center;
      font-size: 15px;
      font-weight: 500;
      color: var(--white);
      animation: slideIn 0.4s ease;
    }

    .notification.error {
      background: linear-gradient(135deg, var(--error-color), #b91c1c);
    }

    .notification.success {
      background: var(--success-color);
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
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
        background: var(--gradient);
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

    @media (max-width: 480px) {
      .reset-container {
        padding: 32px 25px;
        margin: 20px auto;
        border-radius: 20px;
        width: 90%;
      }

      .reset-header h1 {
        font-size: 28px;
      }

      .input-field input {
        padding: 14px 18px;
        padding-left: 45px;
        font-size: 15px;
      }

      .input-field .icon {
        left: 15px;
      }

      .toggle-password {
        right: 15px;
      }

      .btn-submit {
        padding: 14px;
        font-size: 15px;
      }

      .footer {
        padding: 40px 15px;
      }

      .copyright {
        font-size: 15px;
      }
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
      20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
  </style>
</head>
<body>
  <!-- Header -->
  <header>
    <nav>
      <h1>LAB Jewels</h1>
      <div class="menu-toggle">
        <div class="bar"></div>
        <div class="bar"></div>
        <div class="bar"></div>
      </div>
      <ul>
        <li><a href="../pages/landing.php#home">Home</a></li>
        <li><a href="../pages/landing.php#categories">Shop</a></li>
        <li><a href="../pages/landing.php#about">About</a></li>
        <li><a href="../pages/landing.php#contact">Contact</a></li>
        <li><a href="#cart"><i class="fas fa-shopping-cart" title="Cart"></i></a></li>
        <li><a href="signup.php" class="button">Sign Up</a></li>
      </ul>
    </nav>
  </header>

  <!-- Reset Password Container -->
  <div class="reset-container">
    <div class="reset-header">
      <div class="brand-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
        </svg>
      </div>
      <h1>Reset Password</h1>
      <p>Enter the OTP and your new password</p>
    </div>

    <div id="notification" class="notification <?php echo $notification['type']; ?>" style="display: <?php echo $notification['message'] ? 'block' : 'none'; ?>">
      <?php echo htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'); ?>
    </div>

    <form id="reset-password-form" method="post" action="">
      <div class="input-group">
        <label for="otp">OTP</label>
        <div class="input-field">
          <input type="text" id="otp" name="otp" placeholder="Enter the 6-digit OTP" value="<?php echo htmlspecialchars($otp, ENT_QUOTES, 'UTF-8'); ?>" required>
          <i class="fas fa-key icon"></i>
        </div>
      </div>
      <div class="input-group">
        <label for="new_password">New Password</label>
        <div class="input-field">
          <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
          <i class="fas fa-lock icon"></i>
          <button type="button" class="toggle-password" aria-label="Toggle password visibility">
            <i class="fas fa-eye eye"></i>
            <i class="fas fa-eye-slash eye-off" style="display: none;"></i>
          </button>
        </div>
      </div>
      <div class="input-group">
        <label for="confirm_password">Confirm Password</label>
        <div class="input-field">
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
          <i class="fas fa-lock icon"></i>
          <button type="button" class="toggle-password" aria-label="Toggle password visibility">
            <i class="fas fa-eye eye"></i>
            <i class="fas fa-eye-slash eye-off" style="display: none;"></i>
          </button>
        </div>
      </div>
      <button type="submit" id="submit-btn" class="btn-submit">
        <span>Reset Password</span>
        <div class="spinner"></div>
      </button>

      <div class="back-link">
        <a href="forgot-password.php">Request a new OTP</a>
      </div>
    </form>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-container">
      <div class="brand-section">
        <div class="logo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
          </svg>
          <h1>LAB Jewels</h1>
        </div>
        <p class="brand-description">Crafting elegance since 2025. Discover jewelry that celebrates your unique style.</p>
        <div class="social-links">
          <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
          <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
        </div>
      </div>
      <div class="footer-column">
        <h4>Quick Links</h4>
        <div class="footer-links">
          <a href="../pages/landing.php#home">Home</a>
          <a href="../pages/landing.php#categories">Shop</a>
          <a href="../pages/landing.php#about">About</a>
          <a href="../pages/landing.php#contact">Contact</a>
        </div>
      </div>
      <div class="footer-column">
        <h4>Support</h4>
        <div class="footer-links">
          <a href="#">Help Center</a>
          <a href="#">Returns</a>
          <a href="#">Shipping</a>
          <a href="#">FAQs</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div class="copyright">
        © 2025 <a href="#">LAB Jewels</a>. All Rights Reserved.
      </div>
    </div>
  </footer>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('reset-password-form');
      const submitBtn = document.getElementById('submit-btn');
      const notification = document.getElementById('notification');
      const inputs = document.querySelectorAll('.input-field input');
      const resetContainer = document.querySelector('.reset-container');
      const togglePasswordButtons = document.querySelectorAll('.toggle-password');

      function showNotification(message, type) {
        notification.textContent = message;
        notification.className = `notification ${type}`;
        notification.style.display = 'block';
        setTimeout(() => {
          notification.style.display = 'none';
        }, 5000);
      }

      // Input focus animations
      inputs.forEach(input => {
        input.addEventListener('focus', function() {
          this.parentElement.classList.add('focused');
        });

        input.addEventListener('blur', function() {
          if (this.value === '') {
            this.parentElement.classList.remove('focused');
          }
        });

        if (input.value !== '') {
          input.parentElement.classList.add('focused');
        }

        input.addEventListener('input', function() {
          if (this.value.length > 0) {
            this.classList.add('has-value');
          } else {
            this.classList.remove('has-value');
          }
        });

        if (input.value.length > 0) {
          input.classList.add('has-value');
        }
      });

      // Toggle password visibility
      togglePasswordButtons.forEach(button => {
        button.addEventListener('click', function() {
          const input = this.previousElementSibling.previousElementSibling; // Get the input element
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

      // Form submission with AJAX
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        notification.style.display = 'none';
        submitBtn.classList.add('loading');

        const formData = new FormData(this);

        fetch('', {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          submitBtn.classList.remove('loading');
          showNotification(data.message, data.success ? 'success' : 'error');

          if (data.success) {
            resetContainer.style.transform = 'scale(0.95)';
            resetContainer.style.opacity = '0.8';
            setTimeout(() => {
              window.location.href = data.redirect;
            }, 1500);
          } else {
            resetContainer.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
              resetContainer.style.animation = '';
            }, 500);
          }
        })
        .catch(error => {
          submitBtn.classList.remove('loading');
          showNotification('An error occurred. Please try again.', 'error');
          console.error('Error:', error);
        });
      });

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

      // Add enter key support
      inputs.forEach(input => {
        input.addEventListener('keypress', function(e) {
          if (e.key === 'Enter') {
            form.dispatchEvent(new Event('submit'));
          }
        });
      });

      // Auto-hide notification
      notification.addEventListener('click', function() {
        this.style.display = 'none';
      });
    });
  </script>
</body>
</html>