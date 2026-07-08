<?php
/**
 * NexaTech Solutions — Admin Customer Management
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = Database::getInstance();

$message = '';
$msgType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'update_customer') {
        $id     = (int) ($_POST['customer_id'] ?? 0);
        $plan   = sanitize($_POST['plan'] ?? 'none');
        $status = sanitize($_POST['status'] ?? 'active');
        $notes  = sanitize($_POST['notes'] ?? '');

        $validPlans   = ['starter','growth','enterprise','none'];
        $validStatus  = ['active','inactive','pending'];

        if ($id && in_array($plan, $validPlans) && in_array($status, $validStatus)) {
            $db->execute(
                "UPDATE customers SET plan = ?, status = ?, notes = ?, updated_at = NOW() WHERE id = ?",
                [$plan, $status, $notes, $id]
            );
            $message = 'Customer updated successfully.';
            $msgType = 'success';
        }
    }

    if ($action === 'add_customer') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName  = sanitize($_POST['last_name']  ?? '');
        $email     = sanitizeEmail($_POST['email']  ?? '');
        $phone     = sanitize($_POST['phone']       ?? '');
        $company   = sanitize($_POST['company']     ?? '');
        $plan      = sanitize($_POST['plan']        ?? 'none');
        $password  = $_POST['password'] ?? '';

        if (!$firstName || !$lastName || !$email || !$password) {
            $message = 'First name, last name, email, and password are required.';
            $msgType = 'error';
        } else {
            $existing = $db->fetchOne("SELECT id FROM customers WHERE email = ?", [$email]);
            if ($existing) {
                $message = 'A customer with that email already exists.';
                $msgType = 'error';
            } else {
                $db->execute(
                    "INSERT INTO customers (first_name, last_name, email, phone, company, password_hash, plan, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'active')",
                    [$firstName, $lastName, $email, $phone, $company, hashPassword($password), $plan]
                );
                $message = 'Customer added successfully.';
                $msgType = 'success';
            }
        }
    }
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $db->fetchAll(
        "SELECT c.first_name, c.last_name, c.email, c.phone, c.company, c.plan, c.status, c.created_at,
                COUNT(t.id) AS ticket_count
         FROM customers c
         LEFT JOIN tickets t ON t.customer_id = c.id
         GROUP BY c.id
         ORDER BY c.created_at DESC",
        []
    );
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="nexatech_customers_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['First Name','Last Name','Email','Phone','Company','Plan','Status','Tickets','Joined']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['first_name'],$r['last_name'],$r['email'],$r['phone'],$r['company'],$r['plan'],$r['status'],$r['ticket_count'],$r['created_at']]);
    }
    fclose($out);
    exit;
}

// View single customer
$viewId   = (int) ($_GET['id'] ?? 0);
$customer = null;
$custTickets = [];

if ($viewId) {
    $customer = $db->fetchOne("SELECT * FROM customers WHERE id = ?", [$viewId]);
    if ($customer) {
        $custTickets = $db->fetchAll(
            "SELECT id, subject, category, priority, status, created_at FROM tickets WHERE customer_id = ? ORDER BY created_at DESC",
            [$viewId]
        );
    }
}

// Customer list
$customers = $db->fetchAll(
    "SELECT c.id, c.first_name, c.last_name, c.email, c.phone, c.company, c.plan, c.status, c.created_at,
            COUNT(t.id) AS ticket_count
     FROM customers c
     LEFT JOIN tickets t ON t.customer_id = c.id
     GROUP BY c.id
     ORDER BY c.created_at DESC",
    []
);

$csrfToken = generateCsrfToken();
$username  = htmlspecialchars($_SESSION['admin_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customers — NexaTech Admin</title>
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
      <a href="customers.php" class="active"><span class="nav-icon">👥</span> Customers</a>
      <a href="staff.php"><span class="nav-icon">🔑</span> Staff</a>
      <a href="automation-setup.php"><span class="nav-icon">🤖</span> New Client Setup</a>
      <a href="help.php"><span class="nav-icon">📖</span> Manual</a>
    </nav>
    <div class="sidebar-footer"><a href="logout.php"><span>🚪</span> Log Out</a></div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <h1><?= $customer ? 'Customer: ' . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) : 'Customers' ?></h1>
      <div class="topbar-actions">
        <?php if ($customer): ?>
          <a href="customers.php" class="btn btn-ghost btn-sm">← All Customers</a>
        <?php else: ?>
          <a href="customers.php?export=csv" class="btn btn-ghost btn-sm">⬇ Export CSV</a>
          <button type="button" id="toggle-add-customer" class="btn btn-primary btn-sm">+ Add Customer</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="content-area">

      <?php if ($message): ?>
        <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <?php if ($customer): ?>
        <!-- ======= SINGLE CUSTOMER VIEW ======= -->

        <!-- Customer Info Card -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
          <div class="card">
            <div class="card-header"><h3>👤 Customer Info</h3></div>
            <div class="card-body">
              <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
                <div class="avatar" style="width:52px;height:52px;font-size:1.2rem;">
                  <?= strtoupper(substr($customer['first_name'],0,1).substr($customer['last_name'],0,1)) ?>
                </div>
                <div>
                  <h3 style="margin-bottom:4px;"><?= htmlspecialchars($customer['first_name'].' '.$customer['last_name']) ?></h3>
                  <div><?= htmlspecialchars($customer['company'] ?? '—') ?></div>
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;gap:10px;font-size:.9rem;">
                  <span style="color:var(--text-secondary);width:70px;">Email:</span>
                  <a href="mailto:<?= htmlspecialchars($customer['email']) ?>"><?= htmlspecialchars($customer['email']) ?></a>
                </div>
                <div style="display:flex;gap:10px;font-size:.9rem;">
                  <span style="color:var(--text-secondary);width:70px;">Phone:</span>
                  <span><?= htmlspecialchars($customer['phone'] ?? '—') ?></span>
                </div>
                <div style="display:flex;gap:10px;font-size:.9rem;">
                  <span style="color:var(--text-secondary);width:70px;">Joined:</span>
                  <span><?= formatDate($customer['created_at'], 'M j, Y') ?></span>
                </div>
                <div style="display:flex;gap:10px;font-size:.9rem;align-items:center;">
                  <span style="color:var(--text-secondary);width:70px;">Plan:</span>
                  <span class="<?= getPlanBadgeClass($customer['plan']) ?>"><?= ucfirst($customer['plan']) ?></span>
                </div>
                <div style="display:flex;gap:10px;font-size:.9rem;align-items:center;">
                  <span style="color:var(--text-secondary);width:70px;">Status:</span>
                  <span class="<?= getCustomerStatusBadgeClass($customer['status']) ?>"><?= ucfirst($customer['status']) ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Edit Customer Form -->
          <div class="card">
            <div class="card-header"><h3>✏️ Edit Customer</h3></div>
            <div class="card-body">
              <form method="POST" action="customers.php?id=<?= $viewId ?>">
                <input type="hidden" name="action" value="update_customer" />
                <input type="hidden" name="customer_id" value="<?= $viewId ?>" />
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />

                <div class="form-group">
                  <label class="form-label">Plan</label>
                  <select name="plan" class="form-control">
                    <?php foreach (['none','starter','growth','enterprise'] as $p): ?>
                      <option value="<?=$p?>" <?=$customer['plan']===$p?'selected':''?>><?= ucfirst($p) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Status</label>
                  <select name="status" class="form-control">
                    <?php foreach (['active','pending','inactive'] as $s): ?>
                      <option value="<?=$s?>" <?=$customer['status']===$s?'selected':''?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label">Internal Notes</label>
                  <textarea name="notes" class="form-control" rows="4" placeholder="Internal notes about this customer..."><?= htmlspecialchars($customer['notes'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Customer Tickets -->
        <div class="card">
          <div class="card-header"><h3>🎫 Ticket History (<?= count($custTickets) ?>)</h3></div>
          <div class="card-body no-pad">
            <?php if (empty($custTickets)): ?>
              <div style="padding:24px;text-align:center;"><p>No tickets from this customer yet.</p></div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr><th>#</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th><th>Date</th><th>Action</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($custTickets as $t): ?>
                      <tr>
                        <td class="td-secondary">#<?= $t['id'] ?></td>
                        <td><?= htmlspecialchars(truncate($t['subject'], 55)) ?></td>
                        <td class="td-secondary"><?= ucfirst($t['category']) ?></td>
                        <td><span class="<?= getPriorityBadgeClass($t['priority']) ?>"><?= ucfirst($t['priority']) ?></span></td>
                        <td><span class="<?= getStatusBadgeClass($t['status']) ?>"><?= getStatusLabel($t['status']) ?></span></td>
                        <td class="td-secondary"><?= formatDate($t['created_at'], 'M j, Y') ?></td>
                        <td><a href="tickets.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">View</a></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <?php else: ?>
        <!-- ======= CUSTOMER LIST VIEW ======= -->

        <!-- Add Customer Form (hidden by default) -->
        <div class="card hidden" id="add-customer-form" style="margin-bottom:24px;">
          <div class="card-header">
            <h3>➕ Add New Customer</h3>
            <button type="button" id="cancel-add-customer" class="btn btn-ghost btn-sm">Cancel</button>
          </div>
          <div class="card-body">
            <form method="POST" action="customers.php">
              <input type="hidden" name="action" value="add_customer" />
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">First Name *</label>
                  <input type="text" name="first_name" class="form-control" required />
                </div>
                <div class="form-group">
                  <label class="form-label">Last Name *</label>
                  <input type="text" name="last_name" class="form-control" required />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Email *</label>
                  <input type="email" name="email" class="form-control" required />
                </div>
                <div class="form-group">
                  <label class="form-label">Phone</label>
                  <input type="tel" name="phone" class="form-control" />
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Company</label>
                  <input type="text" name="company" class="form-control" />
                </div>
                <div class="form-group">
                  <label class="form-label">Plan</label>
                  <select name="plan" class="form-control">
                    <option value="none">None</option>
                    <option value="starter">Starter ($299/mo)</option>
                    <option value="growth">Growth ($599/mo)</option>
                    <option value="enterprise">Enterprise (Custom)</option>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Temporary Password *</label>
                <input type="password" name="password" class="form-control" placeholder="Customer will use this to log in" required />
              </div>
              <button type="submit" class="btn btn-primary">Add Customer</button>
            </form>
          </div>
        </div>

        <!-- Customer Table -->
        <div class="card">
          <div class="card-header">
            <h3>👥 All Customers (<?= count($customers) ?>)</h3>
          </div>
          <div class="card-body no-pad">
            <?php if (empty($customers)): ?>
              <div style="padding:40px;text-align:center;"><p>No customers yet. Add your first customer above.</p></div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Company</th>
                      <th>Email</th>
                      <th>Phone</th>
                      <th>Plan</th>
                      <th>Status</th>
                      <th>Tickets</th>
                      <th>Joined</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($customers as $c): ?>
                      <tr>
                        <td style="font-weight:500;"><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
                        <td class="td-secondary"><?= htmlspecialchars($c['company'] ?? '—') ?></td>
                        <td><a href="mailto:<?= htmlspecialchars($c['email']) ?>"><?= htmlspecialchars($c['email']) ?></a></td>
                        <td class="td-secondary"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                        <td><span class="<?= getPlanBadgeClass($c['plan']) ?>"><?= ucfirst($c['plan']) ?></span></td>
                        <td><span class="<?= getCustomerStatusBadgeClass($c['status']) ?>"><?= ucfirst($c['status']) ?></span></td>
                        <td class="td-secondary"><?= (int)$c['ticket_count'] ?></td>
                        <td class="td-secondary"><?= formatDate($c['created_at'], 'M j, Y') ?></td>
                        <td style="display:flex;gap:6px;">
                          <a href="customers.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">View</a>
                          <a href="tickets.php?customer=<?= $c['id'] ?>" class="btn btn-ghost btn-sm" title="View tickets">🎫</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </main>
</div>
<script src="../assets/js/main.js?v=7"></script>
</body>
</html>
