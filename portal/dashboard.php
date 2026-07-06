<?php
/**
 * NexaTech Solutions — Customer Dashboard
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db         = Database::getInstance();
$customerId = (int) $_SESSION['customer_id'];

// Fetch customer info
$customer = $db->fetchOne(
    "SELECT id, first_name, last_name, email, company, plan, status FROM customers WHERE id = ?",
    [$customerId]
);

if (!$customer) {
    session_destroy();
    redirect('index.php');
}

// Ticket stats
$stats = $db->fetchOne(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'open') AS open_count,
        SUM(status = 'in_progress') AS in_progress_count,
        SUM(status IN ('resolved','closed')) AS resolved_count
     FROM tickets WHERE customer_id = ?",
    [$customerId]
);

// Recent tickets (last 10)
$tickets = $db->fetchAll(
    "SELECT id, subject, category, priority, status, created_at, updated_at
     FROM tickets
     WHERE customer_id = ?
     ORDER BY updated_at DESC
     LIMIT 10",
    [$customerId]
);

$firstName   = htmlspecialchars($customer['first_name']);
$initials    = strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1));
$companyName = $customer['company'] ? htmlspecialchars($customer['company']) : '';
$plan        = ucfirst($customer['plan'] ?? 'none');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — NexaTech Portal</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet" />
</head>
<body class="portal-body">
<div class="app-layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <a href="../index.html">
        <span style="font-size:1.1rem;">⚡</span>
        <span style="background:linear-gradient(135deg,var(--accent-cyan),var(--accent-green));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">NexaTech Solutions</span>
      </a>
    </div>

    <div class="sidebar-user flex gap-2" style="align-items:center;">
      <div class="avatar"><?= $initials ?></div>
      <div class="user-info">
        <h4><?= $firstName ?></h4>
        <span><?= $companyName ?: 'Customer' ?></span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="dashboard.php" class="active">
        <span class="nav-icon">📊</span> Dashboard
      </a>
      <a href="submit-ticket.php">
        <span class="nav-icon">➕</span> Submit Ticket
      </a>
      <a href="dashboard.php#tickets">
        <span class="nav-icon">🎫</span> My Tickets
      </a>
    </nav>

    <div class="sidebar-footer">
      <a href="logout.php">
        <span>🚪</span> Log Out
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="topbar">
      <h1>Customer Dashboard</h1>
      <div class="topbar-actions">
        <span style="font-size:.85rem;color:var(--text-secondary);">
          Plan: <span class="<?= getPlanBadgeClass($customer['plan']) ?>"><?= $plan ?></span>
        </span>
        <a href="submit-ticket.php" class="btn btn-primary btn-sm">+ New Ticket</a>
      </div>
    </div>

    <div class="content-area">

      <!-- Welcome Header -->
      <div style="margin-bottom:28px;">
        <h2>Welcome back, <?= $firstName ?>! 👋</h2>
        <p style="margin-top:4px;">Here's an overview of your support tickets and account status.</p>
      </div>

      <!-- Stats Row -->
      <div class="dash-stats">
        <div class="dash-stat-card">
          <div class="stat-label">Open Tickets</div>
          <div class="stat-value cyan"><?= (int)($stats['open_count'] ?? 0) ?></div>
          <div class="stat-sub">Awaiting action</div>
        </div>
        <div class="dash-stat-card">
          <div class="stat-label">In Progress</div>
          <div class="stat-value yellow"><?= (int)($stats['in_progress_count'] ?? 0) ?></div>
          <div class="stat-sub">Being worked on</div>
        </div>
        <div class="dash-stat-card">
          <div class="stat-label">Resolved</div>
          <div class="stat-value green"><?= (int)($stats['resolved_count'] ?? 0) ?></div>
          <div class="stat-sub">Completed tickets</div>
        </div>
        <div class="dash-stat-card">
          <div class="stat-label">Current Plan</div>
          <div class="stat-value" style="font-size:1.4rem;"><?= $plan ?></div>
          <div class="stat-sub"><a href="../contact.html" style="color:var(--accent-cyan);font-size:.8rem;">Upgrade plan</a></div>
        </div>
      </div>

      <!-- Recent Tickets -->
      <div class="card" id="tickets">
        <div class="card-header">
          <h3>🎫 Recent Tickets</h3>
          <a href="submit-ticket.php" class="btn btn-primary btn-sm">+ New Ticket</a>
        </div>
        <div class="card-body no-pad">
          <?php if (empty($tickets)): ?>
            <div style="padding:40px;text-align:center;">
              <div style="font-size:2.5rem;margin-bottom:12px;">🎉</div>
              <h4 style="margin-bottom:8px;">No tickets yet</h4>
              <p style="margin-bottom:20px;font-size:.9rem;">Everything running smoothly? Great! Submit a ticket whenever you need help.</p>
              <a href="submit-ticket.php" class="btn btn-primary btn-sm">Submit Your First Ticket</a>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Subject</th>
                    <th>Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Last Update</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($tickets as $ticket): ?>
                    <tr>
                      <td class="td-secondary">#<?= $ticket['id'] ?></td>
                      <td>
                        <a href="ticket-detail.php?id=<?= $ticket['id'] ?>" style="color:var(--text-primary);font-weight:500;">
                          <?= htmlspecialchars(truncate($ticket['subject'], 55)) ?>
                        </a>
                      </td>
                      <td class="td-secondary"><?= ucfirst($ticket['category']) ?></td>
                      <td><span class="<?= getPriorityBadgeClass($ticket['priority']) ?>"><?= ucfirst($ticket['priority']) ?></span></td>
                      <td><span class="<?= getStatusBadgeClass($ticket['status']) ?>"><?= getStatusLabel($ticket['status']) ?></span></td>
                      <td class="td-secondary"><?= timeAgo($ticket['updated_at']) ?></td>
                      <td>
                        <a href="ticket-detail.php?id=<?= $ticket['id'] ?>" class="btn btn-ghost btn-sm">View</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Urgent Help Card -->
      <div class="urgent-card">
        <h4>🚨 Need Urgent Help?</h4>
        <p>For critical issues impacting your entire team, don't wait — call us directly.</p>
        <div class="phone"><a href="tel:+16077655410" style="color:var(--text-primary);">(607) 765-5410</a></div>
        <p style="margin-top:6px;font-size:.8rem;">Available Mon–Fri 8am–6pm ET. Emergency support available for Growth & Enterprise plans.</p>
      </div>

    </div><!-- /content-area -->
  </main>

</div><!-- /app-layout -->
<script src="../assets/js/main.js"></script>
</body>
</html>
