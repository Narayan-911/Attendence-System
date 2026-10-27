<?php
/**
 * Authentication, RBAC, and Security Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    // Configure secure session cookie settings
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'],
        'path'     => $cookieParams['path'] ?: '/',
        'domain'   => $cookieParams['domain'],
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

/**
 * Escape string for safe HTML output
 */
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Check if current user is authenticated
 */
function is_logged_in() {
    return !empty($_SESSION['teacher_id']);
}

/**
 * Enforce authentication on protected pages
 */
function require_login($redirect_to = 'login.php') {
    if (!is_logged_in()) {
        header("Location: " . $redirect_to);
        exit();
    }
}

/**
 * Get the current user's role ('admin', 'teacher', 'student')
 */
function get_user_role() {
    return $_SESSION['role'] ?? 'teacher';
}

/**
 * Get current user ID
 */
function get_user_id() {
    return (int)($_SESSION['teacher_id'] ?? 0);
}

/**
 * Get current username
 */
function get_username() {
    return $_SESSION['teacher_username'] ?? 'User';
}

/**
 * Enforce role-based access control
 */
function require_role($allowed_roles = ['teacher', 'admin'], $redirect_to = 'dashboard.php') {
    require_login();
    $current_role = get_user_role();
    if (!in_array($current_role, (array)$allowed_roles)) {
        header("Location: " . $redirect_to . "?error=unauthorized");
        exit();
    }
}

/**
 * Generate or retrieve CSRF token
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(get_csrf_token()) . '">';
}

/**
 * Validate CSRF token from POST requests
 */
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    $session_token = $_SESSION['csrf_token'] ?? '';
    if (empty($session_token) || empty($token) || !hash_equals($session_token, $token)) {
        http_response_code(403);
        die("Security validation failed (Invalid or missing CSRF token). Please refresh and try again.");
    }
    return true;
}
?>