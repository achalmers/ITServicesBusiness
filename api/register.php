<?php
/**
 * NexaTech Solutions — Customer Registration API
 * POST /api/register.php
 * Returns JSON
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

// Validate CSRF token
$token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

// Rate limiting — max 3 registrations per hour per IP
$ip      = getClientIp();
$rateKey = 'reg_count_' . md5($ip);
$rateTs  = 'reg_ts_'    . md5($ip);

if (isset($_SESSION[$rateTs]) && (time() - $_SESSION[$rateTs]) < 3600) {
    if (($_SESSION[$rateKey] ?? 0) >= 3) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many registration attempts. Please try again later.']);
        exit;
    }
} else {
    $_SESSION[$rateKey] = 0;
    $_SESSION[$rateTs]  = time();
}

// Collect and sanitize inputs
$firstName       = sanitize($_POST['first_name']       ?? '');
$lastName        = sanitize($_POST['last_name']        ?? '');
$email           = sanitizeEmail($_POST['email']       ?? '');
$phone           = sanitize($_POST['phone']            ?? '');
$company         = sanitize($_POST['company']          ?? '');
$password        = $_POST['password']                  ?? '';
$confirmPassword = $_POST['confirm_password']          ?? '';

// ---- Validation ----
$errors = [];

if (empty($firstName) || strlen($firstName) < 1 || strlen($firstName) > 100) {
    $errors[] = 'First name is required (max 100 characters).';
}
if (empty($lastName) || strlen($lastName) < 1 || strlen($lastName) > 100) {
    $errors[] = 'Last name is required (max 100 characters).';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (strlen($email) > 255) {
    $errors[] = 'Email address is too long.';
}
if (empty($password)) {
    $errors[] = 'Password is required.';
} elseif (!isStrongPassword($password)) {
    $errors[] = 'Password must be at least 8 characters and include at least one letter and one number.';
}
if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}
if (!empty($phone) && strlen($phone) > 20) {
    $errors[] = 'Phone number is too long.';
}
if (!empty($company) && strlen($company) > 200) {
    $errors[] = 'Company name is too long.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// Honeypot
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true, 'message' => 'Account created! Please log in.']);
    exit;
}

try {
    $db = Database::getInstance();

    // Check if email is already registered
    $existing = $db->fetchOne("SELECT id FROM customers WHERE email = ?", [$email]);
    if ($existing) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'An account with that email address already exists. Please log in or use a different email.']);
        exit;
    }

    // Hash password
    $passwordHash = hashPassword($password);

    // Insert customer with 'pending' status (admin can activate)
    $customerId = (int) $db->execute(
        "INSERT INTO customers (first_name, last_name, email, phone, company, password_hash, plan, status)
         VALUES (?, ?, ?, ?, ?, ?, 'none', 'pending')",
        [$firstName, $lastName, $email, $phone, $company, $passwordHash]
    );

    // Increment rate limit counter
    $_SESSION[$rateKey] = ($_SESSION[$rateKey] ?? 0) + 1;

    // ---- Send welcome email to customer ----
    $welcomeBody = "
        <p>Hi " . htmlspecialchars($firstName) . ",</p>
        <p>Welcome to <strong>NexaTech Solutions</strong>! Your customer portal account has been created successfully.</p>
        <p>You can now log in to:</p>
        <ul style='margin:12px 0;padding-left:20px;'>
          <li>Submit support tickets</li>
          <li>Track the status of your requests</li>
          <li>Communicate with our team</li>
        </ul>
        <p><strong>Your Login Details:</strong><br />
        Email: " . htmlspecialchars($email) . "<br />
        Portal: <a href='" . SITE_URL . "/portal/index.php' style='color:#00d4ff;'>" . SITE_URL . "/portal/index.php</a></p>
        <p><strong>Note:</strong> Your account is currently pending activation. One of our team members will review your account and reach out shortly to discuss your IT needs.</p>
        <p>Questions? Reply to this email or call us at <strong>" . SUPPORT_PHONE . "</strong>.</p>
        <p>— " . ADMIN_NAME . "<br />NexaTech Solutions</p>
    ";

    sendEmail(
        $email,
        'Welcome to NexaTech Solutions — Portal Account Created',
        $welcomeBody,
        ADMIN_NAME . ' <' . MAIL_FROM_EMAIL . '>'
    );

    // ---- Notify admin ----
    $adminBody = "
        <p>A new customer has registered on the NexaTech Solutions portal.</p>
        <table style='border-collapse:collapse;width:100%;font-size:14px;'>
          <tr style='background:#f0f4f8;'><td style='padding:10px;font-weight:bold;width:120px;'>Customer #</td><td style='padding:10px;'>{$customerId}</td></tr>
          <tr><td style='padding:10px;font-weight:bold;'>Name</td><td style='padding:10px;'>" . htmlspecialchars($firstName . ' ' . $lastName) . "</td></tr>
          <tr style='background:#f0f4f8;'><td style='padding:10px;font-weight:bold;'>Email</td><td style='padding:10px;'><a href='mailto:{$email}'>{$email}</a></td></tr>
          <tr><td style='padding:10px;font-weight:bold;'>Company</td><td style='padding:10px;'>" . htmlspecialchars($company ?: '—') . "</td></tr>
          <tr style='background:#f0f4f8;'><td style='padding:10px;font-weight:bold;'>Phone</td><td style='padding:10px;'>" . htmlspecialchars($phone ?: '—') . "</td></tr>
          <tr><td style='padding:10px;font-weight:bold;'>Status</td><td style='padding:10px;'>Pending Activation</td></tr>
        </table>
        <p style='margin-top:16px;'>
          <a href='" . SITE_URL . "/admin/customers.php?id={$customerId}' style='color:#00d4ff;'>View in Admin Panel →</a>
        </p>
    ";

    sendEmail(
        ADMIN_EMAIL,
        "New Portal Registration: " . htmlspecialchars($firstName . ' ' . $lastName),
        $adminBody
    );

    echo json_encode([
        'success'     => true,
        'message'     => 'Account created successfully! Please log in. Note: your account requires activation before you can submit tickets.',
        'customer_id' => $customerId,
    ]);

} catch (Exception $e) {
    error_log('[Register API] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Registration failed due to a server error. Please try again or contact us at ' . ADMIN_EMAIL]);
}
