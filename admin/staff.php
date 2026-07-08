<?php
/**
 * NexaTech Solutions — Admin Staff / Administrator Management
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = Database::getInstance();

$validRoles = ['admin', 'technician'];
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_admin') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token. Please refresh the page and try again.';
        $msgType = 'error';
    } else {
        $newUsername = sanitize($_POST['username'] ?? '');
        $newEmail    = sanitizeEmail($_POST['email'] ?? '');
        $newRole     = sanitize($_POST['role'] ?? 'technician');
        $newPassword = $_POST['password'] ?? '';

        if (!$newUsername || !$newEmail || !$newPassword || !in_array($newRole, $validRoles)) {
            $message = 'Username, email, role, and password are all required.';
            $msgType = 'error';
        } elseif (strlen($newPassword) < 8) {
            $message = 'Password must be at least 8 characters.';
            $msgType = 'error';
        } else {
            $existing = $db->fetchOne("SELECT id FROM admin_users WHERE username = ? OR email = ?", [$newUsername, $newEmail]);
            if ($existing) {
                $message = 'An admin user with that username or email already exists.';
                $msgType = 'error';
            } else {
                $db->execute(
                    "INSERT INTO admin_users (username, email, password_hash, role) VALUES (?, ?, ?, ?)",
                    [$newUsername, $newEmail, hashPassword($newPassword), $newRole]
                );
                $message = 'Admin user created successfully.';
                $msgType = 'success';
            }
        }
    }
}

$staff = $db->fetchAll("SELECT id, username, email, role, created_at FROM admin_users ORDER BY created_at DESC", []);

$csrfToken = generateCsrfToken();
$username  = htmlspecialchars($_SESSION['admin_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff — NexaTech Admin</title>
  <link rel="stylesheet" href="../assets/css/styles.css?v=3" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
</head>
<body class="admin-body">
<div class="app-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <a href="../index.html">
        <span style="font-size:1rem;">⚡</span>
        <span style="background:linear-gradient(135deg,var(--accent-cyan),var(--accent-green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;font-size:.95rem;">NexaTech Solutions</span>
      </a>
      <div style="margin-top:6px;"><span class="badge badge-high" style="font-size:.65rem;">ADMIN</span></div>
    </div>
    <div class="sidebar-user flex gap-2" style="align-items:center;">
      <div class="avatar" style="background:linear-gradient(135deg,#ff6b6b,#ff9999);"><?= strtoupper(substr($username,0,2)) ?></div>
      <div class="user-info"><h4><?= $username ?></h4><span><?= htmlspecialchars($_SESSION['admin_role']??'admin') ?></span></div>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="tickets.php"><span class="nav-icon">🎫</span> Tickets</a>
      <a href="leads.php"><span class="nav-icon">📩</span> Leads</a>
      <a href="customers.php"><span class="nav-icon">👥</span> Customers</a>
      <a href="staff.php" class="active"><span class="nav-icon">🔑</span> Staff</a>
      <a href="automation-setup.php"><span class="nav-icon">🤖</span> New Client Setup</a>
      <a href="help.php"><span class="nav-icon">📖</span> Manual</a>
    </nav>
    <div class="sidebar-footer"><a href="logout.php"><span>🚪</span> Log Out</a></div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <h1>Staff / Admin Users</h1>
      <div class="topbar-actions">
        <button type="button" id="toggle-add-admin" class="btn btn-primary btn-sm">+ Add Admin User</button>
      </div>
    </div>

    <div class="content-area">

      <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <!-- Add Admin Form (hidden by default) -->
      <div class="card hidden" id="add-admin-form" style="margin-bottom:24px;">
        <div class="card-header">
          <h3>➕ Add New Admin User</h3>
          <button type="button" id="cancel-add-admin" class="btn btn-ghost btn-sm">Cancel</button>
        </div>
        <div class="card-body">
          <p class="alert alert-info" style="margin-bottom:16px;">
            <strong>⚠️ Heads up:</strong> the <strong>Admin</strong> role has full access to this entire panel, including creating other admin users. Use <strong>Technician</strong> for staff who only need day-to-day ticket/customer access (note: this role distinction isn't enforced anywhere yet — see the Manual for details).
          </p>
          <form method="POST" action="staff.php">
            <input type="hidden" name="action" value="add_admin" />
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Username *</label>
                <input type="text" name="username" class="form-control" required />
              </div>
              <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Role *</label>
                <select name="role" class="form-control" required>
                  <option value="technician">Technician</option>
                  <option value="admin">Admin (full access)</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Temporary Password *</label>
                <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required minlength="8" />
              </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Admin User</button>
          </form>
        </div>
      </div>

      <!-- Staff Table -->
      <div class="card">
        <div class="card-header">
          <h3>🔑 All Staff (<?= count($staff) ?>)</h3>
        </div>
        <div class="card-body no-pad">
          <?php if (empty($staff)): ?>
            <div style="padding:40px;text-align:center;"><p>No staff accounts yet.</p></div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Added</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($staff as $s): ?>
                    <tr>
                      <td style="font-weight:500;font-size:.88rem;"><?= htmlspecialchars($s['username']) ?></td>
                      <td><a href="mailto:<?= htmlspecialchars($s['email']) ?>"><?= htmlspecialchars($s['email']) ?></a></td>
                      <td><span class="<?= $s['role']==='admin' ? 'badge badge-high' : 'badge badge-medium' ?>"><?= ucfirst($s['role']) ?></span></td>
                      <td class="td-secondary"><?= formatDate($s['created_at'], 'M j, Y') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>
<script src="../assets/js/main.js?v=6"></script>
</body>
</html>
