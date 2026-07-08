<?php
/**
 * NexaTech Solutions — Admin Login
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in → dashboard
if (isAdminLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter your username and password.';
        } else {
            $db    = Database::getInstance();
            $admin = $db->fetchOne(
                "SELECT id, username, email, password_hash, role FROM admin_users WHERE username = ?",
                [$username]
            );

            if ($admin && verifyPassword($password, $admin['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']       = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email']    = $admin['email'];
                $_SESSION['admin_role']     = $admin['role'];
                redirect('dashboard.php');
            } else {
                // Intentional delay to slow brute force
                usleep(500000); // 0.5s
                $error = 'Invalid username or password.';
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$msgParam  = $_GET['msg'] ?? '';
$success   = ($msgParam === 'logged_out') ? 'You have been logged out.' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Login — NexaTech Solutions</title>
  <link rel="stylesheet" href="../assets/css/styles.css?v=3" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
</head>
<body class="admin-body">

<div class="auth-page">
  <div class="auth-container" style="max-width:420px;">

    <div class="auth-logo">
      <a href="../index.html" class="nav-logo" style="justify-content:center;">
        <span class="logo-icon">⚡</span>
        <span>NexaTech Solutions</span>
      </a>
      <p>Admin Panel</p>
      <div style="margin-top:8px;">
        <span class="badge badge-high" style="font-size:.7rem;">RESTRICTED ACCESS</span>
      </div>
    </div>

    <div class="auth-card">
      <div class="auth-form-area">

        <?php if ($error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="index.php">
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />

          <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control"
                   placeholder="admin" required autocomplete="username" />
          </div>

          <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control"
                   placeholder="••••••••" required autocomplete="current-password" />
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:13px;">
            🔐 Log In to Admin
          </button>
        </form>

        <div class="auth-footer" style="margin-top:16px;">
          <a href="../portal/index.php">← Customer Portal</a>
          &nbsp;·&nbsp;
          <a href="../index.html">Main Website</a>
        </div>
      </div>
    </div>

    <div style="text-align:center;margin-top:16px;font-size:.78rem;color:var(--text-secondary);">
      Unauthorized access is prohibited and may be prosecuted.
    </div>
  </div>
</div>

<script src="../assets/js/main.js?v=7"></script>
</body>
</html>
