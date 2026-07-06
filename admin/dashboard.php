<?php
/**
 * NexaTech Solutions — Admin Dashboard
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = Database::getInstance();

// Dashboard stats
$totalOpen     = (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM tickets WHERE status = 'open'")['c'] ?? 0);
$criticalOpen  = (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM tickets WHERE priority = 'critical' AND status NOT IN ('resolved','closed')")['c'] ?? 0);
$totalCustomers= (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM customers")['c'] ?? 0);
$newLeads      = (int) ($db->fetchOne("SELECT COUNT(*) AS c FROM contact_submissions WHERE status = 'new'")['c'] ?? 0);

// Recent open tickets
$openTickets = $db->fetchAll(
    "SELECT t.id, t.subject, t.category, t.priority, t.status, t.updated_at,
            CONCAT(c.first_name,' ',c.last_name) AS customer_name,
            c.company,
            a.username AS assigned_to_name
     FROM tickets t
     JOIN customers c ON c.id = t.customer_id
     LEFT JOIN admin_users a ON a.id = t.assigned_to
     WHERE t.status NOT IN ('resolved','closed')
     ORDER BY
       FIELD(t.priority,'critical','high','medium','low'),
       t.updated_at ASC
     LIMIT 15",
    []
);

// Recent contact submissions
$recentLeads = $db->fetchAll(
    "SELECT id, name, company, email, service_interest, status, created_at
     FROM contact_submissions
     ORDER BY created_at DESC
     LIMIT 8",
    []
);

// Tickets created per day, last 14 days (zero-filled)
$ticketsByDayRaw = $db->fetchAll(
    "SELECT DATE(created_at) AS d, COUNT(*) AS c
     FROM tickets
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY DATE(created_at)",
    []
);
$ticketsByDayMap = [];
foreach ($ticketsByDayRaw as $row) {
    $ticketsByDayMap[$row['d']] = (int) $row['c'];
}
$ticketsOverTime = [];
for ($i = 13; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $ticketsOverTime[] = ['label' => date('M j', strtotime($day)), 'value' => $ticketsByDayMap[$day] ?? 0];
}

// Tickets by category
$ticketsByCategory = $db->fetchAll(
    "SELECT category, COUNT(*) AS c FROM tickets GROUP BY category ORDER BY c DESC",
    []
);

$username = htmlspecialchars($_SESSION['admin_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Dashboard — NexaTech Solutions</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
</head>
<body class="admin-body">
<div class="app-layout">

  <!-- ADMIN SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <a href="../index.html">
        <span style="font-size:1rem;">⚡</span>
        <span style="background:linear-gradient(135deg,var(--accent-cyan),var(--accent-green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;font-size:.95rem;">NexaTech Solutions</span>
      </a>
      <div style="margin-top:6px;"><span class="badge badge-high" style="font-size:.65rem;">ADMIN</span></div>
    </div>
    <div class="sidebar-user flex gap-2" style="align-items:center;">
      <div class="avatar" style="background:linear-gradient(135deg,#ff6b6b,#ff9999);">
        <?= strtoupper(substr($username, 0, 2)) ?>
      </div>
      <div class="user-info">
        <h4><?= $username ?></h4>
        <span><?= htmlspecialchars($_SESSION['admin_role'] ?? 'admin') ?></span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php" class="active"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="tickets.php"><span class="nav-icon">🎫</span> Tickets</a>
      <a href="customers.php"><span class="nav-icon">👥</span> Customers</a>
      <a href="help.php"><span class="nav-icon">📖</span> Manual</a>
    </nav>
    <div class="sidebar-footer">
      <a href="logout.php"><span>🚪</span> Log Out</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="topbar">
      <h1>Admin Dashboard</h1>
      <div class="topbar-actions">
        <a href="tickets.php" class="btn btn-primary btn-sm">View All Tickets</a>
        <a href="../portal/index.php" class="btn btn-ghost btn-sm" target="_blank">Customer Portal ↗</a>
      </div>
    </div>

    <div class="content-area">

      <!-- Stats Row -->
      <div class="dash-stats">
        <div class="dash-stat-card">
          <div class="stat-label">Open Tickets</div>
          <div class="stat-value cyan"><?= $totalOpen ?></div>
          <div class="stat-sub"><a href="tickets.php?status=open" style="color:var(--accent-cyan);font-size:.8rem;">View all →</a></div>
        </div>
        <div class="dash-stat-card">
          <div class="stat-label">Critical / Urgent</div>
          <div class="stat-value red"><?= $criticalOpen ?></div>
          <div class="stat-sub"><a href="tickets.php?priority=critical" style="color:#ff6b6b;font-size:.8rem;">View critical →</a></div>
        </div>
        <div class="dash-stat-card">
          <div class="stat-label">Total Customers</div>
          <div class="stat-value green"><?= $totalCustomers ?></div>
          <div class="stat-sub"><a href="customers.php" style="color:var(--accent-green);font-size:.8rem;">View all →</a></div>
        </div>
        <div class="dash-stat-card">
          <div class="stat-label">New Leads</div>
          <div class="stat-value yellow"><?= $newLeads ?></div>
          <div class="stat-sub">Uncontacted inquiries</div>
        </div>
      </div>

      <!-- Open Tickets Table -->
      <div class="card">
        <div class="card-header">
          <h3>🔥 Open Tickets (Priority Order)</h3>
          <a href="tickets.php" class="btn btn-ghost btn-sm">All Tickets</a>
        </div>
        <div class="card-body no-pad">
          <?php if (empty($openTickets)): ?>
            <div style="padding:40px;text-align:center;">
              <div style="font-size:2rem;margin-bottom:10px;">🎉</div>
              <p>No open tickets! All caught up.</p>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th>Last Update</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($openTickets as $t): ?>
                    <tr>
                      <td class="td-secondary">#<?= $t['id'] ?></td>
                      <td>
                        <div style="font-weight:500;font-size:.9rem;"><?= htmlspecialchars($t['customer_name']) ?></div>
                        <?php if ($t['company']): ?>
                          <div class="td-secondary"><?= htmlspecialchars($t['company']) ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a href="tickets.php?id=<?= $t['id'] ?>" style="color:var(--text-primary);font-weight:500;font-size:.9rem;">
                          <?= htmlspecialchars(truncate($t['subject'], 48)) ?>
                        </a>
                      </td>
                      <td class="td-secondary"><?= ucfirst($t['category']) ?></td>
                      <td><span class="<?= getPriorityBadgeClass($t['priority']) ?>"><?= ucfirst($t['priority']) ?></span></td>
                      <td><span class="<?= getStatusBadgeClass($t['status']) ?>"><?= getStatusLabel($t['status']) ?></span></td>
                      <td class="td-secondary"><?= $t['assigned_to_name'] ? htmlspecialchars($t['assigned_to_name']) : '<span style="color:var(--text-secondary);font-size:.8rem;">Unassigned</span>' ?></td>
                      <td class="td-secondary"><?= timeAgo($t['updated_at']) ?></td>
                      <td>
                        <a href="tickets.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- New Leads -->
      <div class="card">
        <div class="card-header">
          <h3>📩 Recent Contact Inquiries</h3>
        </div>
        <div class="card-body no-pad">
          <?php if (empty($recentLeads)): ?>
            <div style="padding:32px;text-align:center;"><p>No contact submissions yet.</p></div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Email</th>
                    <th>Service Interest</th>
                    <th>Status</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentLeads as $lead): ?>
                    <tr>
                      <td style="font-weight:500;"><?= htmlspecialchars($lead['name']) ?></td>
                      <td class="td-secondary"><?= htmlspecialchars($lead['company'] ?? '—') ?></td>
                      <td><a href="mailto:<?= htmlspecialchars($lead['email']) ?>"><?= htmlspecialchars($lead['email']) ?></a></td>
                      <td class="td-secondary"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$lead['service_interest'] ?? '—'))) ?></td>
                      <td>
                        <span class="badge badge-<?= htmlspecialchars($lead['status']) ?>">
                          <?= ucfirst($lead['status']) ?>
                        </span>
                      </td>
                      <td class="td-secondary"><?= timeAgo($lead['created_at']) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Charts -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <div class="card">
          <div class="card-header"><h3>📈 Tickets Over Time</h3></div>
          <div class="card-body" style="min-height:200px;">
            <canvas id="tickets-chart" width="500" height="220" style="max-width:100%;height:auto;"
                    data-chart-type="line"
                    data-chart='<?= htmlspecialchars(json_encode($ticketsOverTime), ENT_QUOTES) ?>'></canvas>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><h3>🥧 Tickets by Category</h3></div>
          <div class="card-body" style="min-height:200px;">
            <canvas id="category-chart" width="500" height="220" style="max-width:100%;height:auto;"
                    data-chart-type="pie"
                    data-chart='<?= htmlspecialchars(json_encode(array_map(
                        fn($r) => ['label' => ucfirst($r['category']), 'value' => (int) $r['c']],
                        $ticketsByCategory
                    )), ENT_QUOTES) ?>'></canvas>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>
<script src="../assets/js/main.js"></script>
</body>
</html>
