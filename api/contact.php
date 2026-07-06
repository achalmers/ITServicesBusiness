<?php
/**
 * NexaTech Solutions — Contact Form API Endpoint
 * POST /api/contact.php
 * Returns JSON
 */

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Rate limiting — simple IP-based check (1 submission per 5 minutes per IP)
$ip          = getClientIp();
$sessionKey  = 'contact_last_submit_' . md5($ip);
if (isset($_SESSION[$sessionKey]) && (time() - $_SESSION[$sessionKey]) < 300) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Please wait before submitting another message.']);
    exit;
}

// Validate CSRF token
$token = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid security token. Please refresh the page.']);
    exit;
}

// Collect and sanitize fields
$name            = sanitize($_POST['name']             ?? '');
$company         = sanitize($_POST['company']          ?? '');
$email           = sanitizeEmail($_POST['email']       ?? '');
$phone           = sanitize($_POST['phone']            ?? '');
$serviceInterest = sanitize($_POST['service_interest'] ?? '');
$message         = sanitize($_POST['message']          ?? '');

// Server-side validation
$errors = [];

if (empty($name) || strlen($name) < 2) {
    $errors[] = 'Full name is required (minimum 2 characters).';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (empty($message) || strlen($message) < 10) {
    $errors[] = 'Message is required (minimum 10 characters).';
}
if (strlen($name) > 200 || strlen($email) > 255 || strlen($message) > 5000) {
    $errors[] = 'One or more fields exceed maximum length.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

// Honeypot check (if present and non-empty → bot)
if (!empty($_POST['website'])) {
    // Silently succeed to not tip off bots
    echo json_encode(['success' => true, 'message' => 'Message received. We\'ll be in touch soon!']);
    exit;
}

try {
    $db = Database::getInstance();

    // Insert into contact_submissions
    $insertId = (int) $db->execute(
        "INSERT INTO contact_submissions (name, company, email, phone, service_interest, message, status, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, 'new', ?)",
        [$name, $company, $email, $phone, $serviceInterest, $message, $ip]
    );

    // Send notification email to admin
    $adminBody = "
        <p>A new contact form submission has been received on the NexaTech Solutions website.</p>
        <table style='border-collapse:collapse;width:100%;font-size:14px;'>
          <tr style='background:#f0f4f8;'><td style='padding:10px;font-weight:bold;width:140px;'>Reference #</td><td style='padding:10px;'>{$insertId}</td></tr>
          <tr><td style='padding:10px;font-weight:bold;'>Name</td><td style='padding:10px;'>" . htmlspecialchars($name) . "</td></tr>
          <tr style='background:#f0f4f8;'><td style='padding:10px;font-weight:bold;'>Company</td><td style='padding:10px;'>" . htmlspecialchars($company ?: '—') . "</td></tr>
          <tr><td style='padding:10px;font-weight:bold;'>Email</td><td style='padding:10px;'><a href='mailto:{$email}'>{$email}</a></td></tr>
          <tr style='background:#f0f4f8;'><td style='padding:10px;font-weight:bold;'>Phone</td><td style='padding:10px;'>" . htmlspecialchars($phone ?: '—') . "</td></tr>
          <tr><td style='padding:10px;font-weight:bold;'>Service Interest</td><td style='padding:10px;'>" . htmlspecialchars(ucfirst(str_replace('_',' ', $serviceInterest ?: '—'))) . "</td></tr>
          <tr style='background:#f0f4f8;'><td style='padding:10px;font-weight:bold;'>Message</td><td style='padding:10px;'>" . nl2br(htmlspecialchars($message)) . "</td></tr>
          <tr><td style='padding:10px;font-weight:bold;'>Submitted</td><td style='padding:10px;'>" . date('F j, Y g:i A T') . "</td></tr>
        </table>
        <p style='margin-top:16px;'><a href='" . SITE_URL . "/admin/dashboard.php' style='color:#00d4ff;'>View in Admin Panel →</a></p>
    ";

    sendEmail(
        ADMIN_EMAIL,
        "New Contact: " . htmlspecialchars($name) . " — NexaTech Solutions",
        $adminBody
    );

    // Send auto-reply to the person who submitted
    $replyBody = "
        <p>Hi " . htmlspecialchars($name) . ",</p>
        <p>Thank you for reaching out to NexaTech Solutions! We've received your message and will get back to you within <strong>2 business hours</strong> (Mon–Fri, 8am–6pm ET).</p>
        <p><strong>Your reference number:</strong> #" . $insertId . "</p>
        <p>If you have an urgent need, please call us directly:</p>
        <p style='font-size:18px;font-weight:bold;'><a href='tel:+16077655410' style='color:#00d4ff;'>(607) 765-5410</a></p>
        <p>— " . ADMIN_NAME . "<br />NexaTech Solutions</p>
    ";

    sendEmail(
        $email,
        "We received your message — NexaTech Solutions",
        $replyBody,
        ADMIN_NAME . ' <' . MAIL_FROM_EMAIL . '>'
    );

    // Set rate limit timestamp
    $_SESSION[$sessionKey] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Message received! We\'ll be in touch within 2 business hours. Check your email for a confirmation.',
        'ref'     => $insertId,
    ]);

} catch (Exception $e) {
    error_log('[Contact API] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again or email us directly at ' . ADMIN_EMAIL]);
}
