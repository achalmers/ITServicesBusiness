<?php
/**
 * NexaTech Solutions — Customer Portal Login & Registration
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error   = '';
$success = '';
$tab     = 'login'; // default tab

// Handle POST (login handled via API, but we can also handle inline)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? 'login');

    if ($action === 'login') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCsrfToken($token)) {
            $error = 'Invalid security token. Please refresh and try again.';
        } else {
            $email    = sanitizeEmail($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $error = 'Please enter your email and password.';
            } else {
                $db       = Database::getInstance();
                $customer = $db->fetchOne(
                    "SELECT id, first_name, last_name, email, password_hash, status FROM customers WHERE email = ?",
                    [$email]
                );

                if ($customer && verifyPassword($password, $customer['password_hash'])) {
                    if ($customer['status'] === 'inactive') {
                        $error = 'Your account has been deactivated. Please contact support.';
                    } else {
                        // Regenerate session ID for security
                        session_regenerate_id(true);
                        $_SESSION['customer_id']    = $customer['id'];
                        $_SESSION['customer_name']  = $customer['first_name'] . ' ' . $customer['last_name'];
                        $_SESSION['customer_email'] = $customer['email'];
                        $_SESSION['customer_first'] = $customer['first_name'];
                        redirect('dashboard.php');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        }
    }
}

$csrfToken = generateCsrfToken();

// Flash message from logout
$msgParam = $_GET['msg'] ?? '';
if ($msgParam === 'logged_out') $success = 'You have been logged out successfully.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customer Portal — NexaTech Solutions</title>
  <link rel="stylesheet" href="../assets/css/styles.css?v=3" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
</head>
<body class="portal-body">

<div class="auth-page">
  <div class="auth-container">

    <div class="auth-logo">
      <a href="../index.html" class="nav-logo" style="justify-content:center;">
        <span class="logo-icon">⚡</span>
        <span>NexaTech Solutions</span>
      </a>
      <p>Customer Support Portal</p>
    </div>

    <div class="auth-card">
      <div class="auth-tabs">
        <button class="auth-tab active" data-tab="login" type="button">Log In</button>
        <button class="auth-tab" data-tab="register" type="button">Register</button>
      </div>

      <div class="auth-form-area">

        <?php if ($error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- LOGIN FORM -->
        <div class="auth-form active" id="form-login">
          <form method="POST" action="index.php">
            <input type="hidden" name="action" value="login" />
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />

            <div class="form-group">
              <label class="form-label" for="login-email">Email Address</label>
              <input type="email" id="login-email" name="email" class="form-control"
                     placeholder="you@company.com" required autocomplete="email" />
            </div>

            <div class="form-group">
              <label class="form-label" for="login-password">Password</label>
              <input type="password" id="login-password" name="password" class="form-control"
                     placeholder="••••••••" required autocomplete="current-password" />
            </div>

            <div class="form-group form-check">
              <input type="checkbox" id="remember" name="remember" value="1" />
              <label for="remember">Keep me signed in</label>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;">
              Log In to Portal
            </button>
          </form>

          <div class="auth-footer">
            Don't have an account?
            <a href="#" onclick="document.querySelector('[data-tab=register]').click();return false;">Register here</a>
          </div>
        </div>

        <!-- REGISTRATION FORM -->
        <div class="auth-form" id="form-register">
          <div id="register-msg"></div>
          <form id="register-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="reg-first">First Name *</label>
                <input type="text" id="reg-first" name="first_name" class="form-control"
                       placeholder="Jane" required />
              </div>
              <div class="form-group">
                <label class="form-label" for="reg-last">Last Name *</label>
                <input type="text" id="reg-last" name="last_name" class="form-control"
                       placeholder="Smith" required />
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="reg-company">Company</label>
              <input type="text" id="reg-company" name="company" class="form-control"
                     placeholder="Acme Inc." />
            </div>

            <div class="form-group">
              <label class="form-label" for="reg-email">Email Address *</label>
              <input type="email" id="reg-email" name="email" class="form-control"
                     placeholder="jane@company.com" required />
            </div>

            <div class="form-group">
              <label class="form-label" for="reg-phone">Phone</label>
              <input type="tel" id="reg-phone" name="phone" class="form-control"
                     placeholder="(607) 555-0100" />
            </div>

            <div class="form-group">
              <label class="form-label" for="reg-password">Password *</label>
              <input type="password" id="reg-password" name="password" class="form-control"
                     placeholder="Min. 8 characters" required autocomplete="new-password" />
              <small style="margin-top:6px;display:block;color:var(--text-secondary);">At least 8 characters with a letter and number</small>
            </div>

            <div class="form-group">
              <label class="form-label" for="reg-confirm">Confirm Password *</label>
              <input type="password" id="reg-confirm" name="confirm_password" class="form-control"
                     placeholder="Repeat password" required autocomplete="new-password" />
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;">
              Create Account
            </button>
          </form>

          <div class="auth-footer">
            Already have an account?
            <a href="#" onclick="document.querySelector('[data-tab=login]').click();return false;">Log in</a>
          </div>
        </div>

      </div>
    </div>

    <div style="text-align:center;margin-top:20px;">
      <a href="../index.html" style="color:var(--text-secondary);font-size:.85rem;">← Back to NexaTech Solutions</a>
    </div>

    <div style="text-align:center;margin-top:12px;font-size:.8rem;color:var(--text-secondary);">
      Need urgent help? Call <a href="tel:+16077655410" style="color:var(--accent-cyan);">(607) 765-5410</a>
    </div>
  </div>
</div>

<script src="../assets/js/main.js?v=3"></script>
</body>
</html>
