<?php
// /churnguard-pro/auth/destroyer.php
declare(strict_types=1);

require_once __DIR__ . '/../connection/config.php';

try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // If you have a DB-backed session/token, clear it
    if (function_exists('destroySession')) {
        try {
            destroySession(); // no args if your helper infers session/user from $_SESSION
        } catch (Throwable $e) {
            error_log('destroySession() failed: ' . $e->getMessage());
            // continue with local session cleanup
        }
    }

    // Wipe PHP session data
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();

    // Optional: also clear a custom session token cookie if you set one
    if (!empty($_COOKIE['session_token'])) {
        setcookie('session_token', '', time() - 3600, '/', '', false, true);
    }

    // Back to your app landing
    header('Location: ../index.php');
    exit;
} catch (Throwable $e) {
    error_log('Logout error: ' . $e->getMessage());
    header('Location: ../index.php?error=logout_failed');
    exit;
}
