<?php
/**
 * NexaTech Solutions — Helper Functions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ============================================================
// INPUT SANITIZATION
// ============================================================

/**
 * Sanitize a string input: trim and escape HTML special characters
 */
function sanitize(mixed $input): string
{
    if ($input === null) return '';
    return htmlspecialchars(trim((string) $input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize an email address
 */
function sanitizeEmail(string $email): string
{
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

// ============================================================
// REDIRECTS
// ============================================================

/**
 * Redirect to a URL and exit
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ============================================================
// SESSION / AUTHENTICATION HELPERS
// ============================================================

/**
 * Check if a customer is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
}

/**
 * Check if an admin user is logged in
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Require customer login — redirect to portal login if not authenticated
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('/portal/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

/**
 * Require admin login — redirect to admin login if not authenticated
 */
function requireAdmin(): void
{
    if (!isAdminLoggedIn()) {
        redirect('/admin/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

// ============================================================
// CSRF PROTECTION
// ============================================================

/**
 * Generate a CSRF token and store it in session
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a submitted CSRF token against the session token
 */
function validateCsrfToken(string $token): bool
{
    if (empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// PASSWORD HELPERS
// ============================================================

/**
 * Hash a password using bcrypt
 */
function hashPassword(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against a bcrypt hash
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Check password strength (min 8 chars, at least one letter and one number)
 */
function isStrongPassword(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Za-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

// ============================================================
// BADGE HELPERS
// ============================================================

/**
 * Return the CSS class for a ticket status badge
 */
function getStatusBadgeClass(string $status): string
{
    return match ($status) {
        'open'        => 'badge badge-open',
        'in_progress' => 'badge badge-in_progress',
        'waiting'     => 'badge badge-waiting',
        'resolved'    => 'badge badge-resolved',
        'closed'      => 'badge badge-closed',
        default       => 'badge',
    };
}

/**
 * Return a human-readable label for a ticket status
 */
function getStatusLabel(string $status): string
{
    return match ($status) {
        'open'        => 'Open',
        'in_progress' => 'In Progress',
        'waiting'     => 'Waiting',
        'resolved'    => 'Resolved',
        'closed'      => 'Closed',
        default       => ucfirst($status),
    };
}

/**
 * Return the CSS class for a priority badge
 */
function getPriorityBadgeClass(string $priority): string
{
    return match ($priority) {
        'low'      => 'badge badge-low',
        'medium'   => 'badge badge-medium',
        'high'     => 'badge badge-high',
        'critical' => 'badge badge-critical',
        default    => 'badge',
    };
}

/**
 * Return the CSS class for a customer status badge
 */
function getCustomerStatusBadgeClass(string $status): string
{
    return match ($status) {
        'active'   => 'badge badge-active',
        'pending'  => 'badge badge-pending',
        'inactive' => 'badge badge-inactive',
        default    => 'badge',
    };
}

/**
 * Return the CSS class for a plan badge
 */
function getPlanBadgeClass(string $plan): string
{
    return match ($plan) {
        'starter'    => 'badge badge-starter',
        'growth'     => 'badge badge-growth',
        'enterprise' => 'badge badge-enterprise',
        default      => 'badge badge-none',
    };
}

// ============================================================
// TIME HELPERS
// ============================================================

/**
 * Return a human-readable "time ago" string from a datetime string
 */
function timeAgo(string $datetime): string
{
    $now  = new DateTime('now');
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return $diff->y . ' year'  . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day'   . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour'  . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute'. ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Format a MySQL datetime to a readable date/time string
 */
function formatDate(string $datetime, string $format = 'M j, Y g:i A'): string
{
    return (new DateTime($datetime))->format($format);
}

// ============================================================
// EMAIL
// ============================================================

/**
 * Send an email using authenticated SMTP via PHPMailer
 * (PHP's mail() was found to silently drop script-originated messages
 * on this SiteGround account — see includes/PHPMailer/)
 *
 * @param  string $to      Recipient email address
 * @param  string $subject Email subject
 * @param  string $body    Email body (HTML supported)
 * @param  string $from    From address (defaults to MAIL_FROM_EMAIL)
 * @return bool
 */
function sendEmail(string $to, string $subject, string $body, string $from = ''): bool
{
    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';

    if (empty($from)) $from = ADMIN_NAME . ' <' . MAIL_FROM_EMAIL . '>';

    // Wrap body in basic HTML template
    $htmlBody = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;color:#333;'>"
        . "<div style='max-width:600px;margin:0 auto;padding:20px;'>"
        . "<div style='background:#050b1a;padding:16px 24px;border-radius:8px 8px 0 0;'>"
        . "<h2 style='color:#00d4ff;margin:0;font-size:18px;'>⚡ NexaTech Solutions</h2>"
        . "</div>"
        . "<div style='background:#f9f9f9;padding:24px;border-radius:0 0 8px 8px;border:1px solid #ddd;'>"
        . $body
        . "</div>"
        . "<p style='font-size:12px;color:#999;margin-top:16px;text-align:center;'>NexaTech Solutions — " . SITE_URL . "</p>"
        . "</div></body></html>";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->Port       = SMTP_PORT;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;

        $mail->setFrom(MAIL_FROM_EMAIL, ADMIN_NAME);
        $mail->addAddress($to);
        // Avoid Reply-To == To (SiteGround's antispam gateway flags this as suspicious)
        if (strcasecmp(ADMIN_EMAIL, $to) !== 0) {
            $mail->addReplyTo(ADMIN_EMAIL, ADMIN_NAME);
        }

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $body)));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[NexaTech Email] Failed to send to: ' . $to . ' Subject: ' . $subject . ' | ' . $mail->ErrorInfo);
        return false;
    }
}

// ============================================================
// JSON RESPONSE HELPERS
// ============================================================

/**
 * Output a JSON success response and exit
 */
function jsonSuccess(array $data = [], string $message = 'Success'): never
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}

/**
 * Output a JSON error response and exit
 */
function jsonError(string $error, int $httpCode = 400): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

// ============================================================
// MISC UTILITIES
// ============================================================

/**
 * Get the client's real IP address
 */
function getClientIp(): string
{
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = filter_var($_SERVER[$key], FILTER_VALIDATE_IP);
            if ($ip !== false) return $ip;
        }
    }
    return '0.0.0.0';
}

/**
 * Truncate a string to a given length
 */
function truncate(string $text, int $length = 100, string $suffix = '…'): string
{
    if (strlen($text) <= $length) return $text;
    return rtrim(substr($text, 0, $length)) . $suffix;
}

/**
 * Generate the navigation HTML (shared between portal pages)
 * Returns inline HTML string
 */
function renderSidebarNav(string $active = 'dashboard', bool $isAdmin = false): string
{
    if ($isAdmin) {
        $links = [
            'dashboard' => ['href' => 'dashboard.php', 'icon' => '📊', 'label' => 'Dashboard'],
            'tickets'   => ['href' => 'tickets.php',   'icon' => '🎫', 'label' => 'Tickets'],
            'customers' => ['href' => 'customers.php', 'icon' => '👥', 'label' => 'Customers'],
        ];
    } else {
        $links = [
            'dashboard'     => ['href' => 'dashboard.php',     'icon' => '📊', 'label' => 'Dashboard'],
            'submit-ticket' => ['href' => 'submit-ticket.php', 'icon' => '➕', 'label' => 'Submit Ticket'],
            'tickets'       => ['href' => 'dashboard.php#tickets', 'icon' => '🎫', 'label' => 'My Tickets'],
        ];
    }

    $html = '<nav class="sidebar-nav">';
    foreach ($links as $key => $link) {
        $cls = ($key === $active) ? ' class="active"' : '';
        $html .= '<a href="' . $link['href'] . '"' . $cls . '>'
               . '<span class="nav-icon">' . $link['icon'] . '</span>'
               . $link['label']
               . '</a>';
    }
    $html .= '</nav>';
    return $html;
}
