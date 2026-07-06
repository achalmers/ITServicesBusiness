<?php
/**
 * NexaTech Solutions — Application Configuration (template)
 * SiteGround PHP 8.x + MySQL 8.x
 *
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to config.php (which is gitignored, never committed)
 * 2. In cPanel > MySQL Databases, create a database and user
 * 3. Import db/schema.sql via phpMyAdmin
 * 4. Update DB_NAME, DB_USER, DB_PASS below
 * 5. Update SITE_URL with your actual domain
 * 6. Change CSRF_SECRET to a long random string
 * 7. Generate and update the admin password hash (see schema.sql)
 * 8. Update SMTP_* with your outbound mail credentials
 */

// ---- Database Configuration ----
define('DB_HOST',    'localhost');
define('DB_NAME',    'your_db_name');
define('DB_USER',    'your_db_user');
define('DB_PASS',    'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// ---- Site Configuration ----
define('SITE_NAME', 'NexaTech Solutions');
define('SITE_URL',  'https://example.com/ITServicesBusiness');
define('SITE_TAGLINE', 'Your IT Department, Without the Overhead');

// ---- Contact / Email ----
define('ADMIN_EMAIL', 'admin@example.com');
define('MAIL_FROM_EMAIL', 'mailbox@example.com');
define('ADMIN_NAME',  'Admin Name');
define('SUPPORT_PHONE', '(000) 000-0000');

// ---- SMTP (used instead of PHP mail() — see includes/functions.php sendEmail()) ----
define('SMTP_HOST', 'mail.example.com');
define('SMTP_PORT', 465);          // 465 = SMTPS (implicit TLS)
define('SMTP_SECURE', 'ssl');      // 'ssl' for port 465, 'tls' for port 587
define('SMTP_USER', 'mailbox@example.com');
define('SMTP_PASS', 'your_smtp_password');

// ---- Session & Security ----
define('SESSION_LIFETIME', 3600);        // 1 hour in seconds
define('CSRF_SECRET', 'change-this-to-a-long-random-string-minimum-32-chars');
define('MAX_LOGIN_ATTEMPTS', 5);         // Lock after 5 failed attempts
define('LOGIN_LOCKOUT_TIME', 900);       // 15 minutes lockout

// ---- File Uploads ----
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_TYPES', ['image/jpeg','image/png','image/gif','application/pdf',
                          'text/plain','application/msword',
                          'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// ---- Error Reporting ----
// Set E_ALL during development, 0 in production
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// ---- Session Security (applied before session_start()) ----
// These are also set in .htaccess but PHP values take precedence if PHP-FPM
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// ---- Timezone ----
date_default_timezone_set('America/New_York');
