<?php
/**
 * NexaTech Solutions — Customer Authentication API
 * POST /api/auth.php
 * Returns JSON
 *
 * Note: Login is also handled inline in portal/index.php for direct form submissions.
 * This endpoint supports AJAX-based login from the portal login form.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$action = sanitize($_POST['action'] ?? 'login');

// ============================================================
// CUSTOMER LOGIN
// ============================================================
if ($action === 'login') {

    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid security token.']);
        exit;
    }

    $email    = sanitizeEmail($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Email and password are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
        exit;
    }

    // Simple brute-force protection — track failed attempts in session
    $attemptKey = 'login_attempts_' . md5($email);
    $lockKey    = 'login_locked_'   . md5($email);

    if (isset($_SESSION[$lockKey]) && $_SESSION[$lockKey] > time()) {
        $wait = ceil(($_SESSION[$lockKey] - time()) / 60);
        echo json_encode(['success' => false, 'error' => "Too many failed attempts. Please wait {$wait} minute(s) and try again."]);
        exit;
    }

    try {
        $db       = Database::getInstance();
        $customer = $db->fetchOne(
            "SELECT id, first_name, last_name, email, password_hash, status, plan FROM customers WHERE email = ?",
            [$email]
        );

        if (!$customer || !verifyPassword($password, $customer['password_hash'])) {
            // Increment failed attempts
            $_SESSION[$attemptKey] = ($_SESSION[$attemptKey] ?? 0) + 1;
            if ($_SESSION[$attemptKey] >= MAX_LOGIN_ATTEMPTS) {
                $_SESSION[$lockKey] = time() + LOGIN_LOCKOUT_TIME;
                unset($_SESSION[$attemptKey]);
            }
            // Generic error message (don't reveal whether email exists)
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid email or password.']);
            exit;
        }

        if ($customer['status'] === 'inactive') {
            echo json_encode(['success' => false, 'error' => 'Your account has been deactivated. Please contact support at ' . SUPPORT_PHONE]);
            exit;
        }

        // Clear failed attempts on success
        unset($_SESSION[$attemptKey], $_SESSION[$lockKey]);

        // Set session
        session_regenerate_id(true);
        $_SESSION['customer_id']    = $customer['id'];
        $_SESSION['customer_name']  = $customer['first_name'] . ' ' . $customer['last_name'];
        $_SESSION['customer_email'] = $customer['email'];
        $_SESSION['customer_first'] = $customer['first_name'];
        $_SESSION['customer_plan']  = $customer['plan'];
        $_SESSION['login_time']     = time();

        echo json_encode([
            'success'  => true,
            'message'  => 'Login successful.',
            'redirect' => '/portal/dashboard.php',
            'name'     => $customer['first_name'],
        ]);

    } catch (Exception $e) {
        error_log('[Auth API Login] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
    }

// ============================================================
// ADMIN STATUS UPDATE (inline ticket status change)
// ============================================================
} elseif ($action === 'update_status') {

    // Require admin session
    if (!isAdminLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
        exit;
    }

    $ticketId  = (int) ($_POST['ticket_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    $valid     = ['open','in_progress','waiting','resolved','closed'];

    if (!$ticketId || !in_array($newStatus, $valid)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid ticket ID or status.']);
        exit;
    }

    try {
        $db = Database::getInstance();
        $resolvedAt = in_array($newStatus, ['resolved','closed']) ? 'NOW()' : 'NULL';
        $db->execute(
            "UPDATE tickets SET status = ?, resolved_at = {$resolvedAt}, updated_at = NOW() WHERE id = ?",
            [$newStatus, $ticketId]
        );
        echo json_encode(['success' => true, 'message' => 'Status updated.']);
    } catch (Exception $e) {
        error_log('[Auth API update_status] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error.']);
    }

// ============================================================
// SESSION CHECK
// ============================================================
} elseif ($action === 'check') {
    echo json_encode([
        'logged_in'  => isLoggedIn(),
        'admin'      => isAdminLoggedIn(),
        'customer_id'=> $_SESSION['customer_id'] ?? null,
    ]);

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
}
