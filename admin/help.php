<?php
/**
 * NexaTech Solutions — Admin User Manual
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$username = htmlspecialchars($_SESSION['admin_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Manual — NexaTech Solutions</title>
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
      <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="tickets.php"><span class="nav-icon">🎫</span> Tickets</a>
      <a href="customers.php"><span class="nav-icon">👥</span> Customers</a>
      <a href="help.php" class="active"><span class="nav-icon">📖</span> Manual</a>
    </nav>
    <div class="sidebar-footer">
      <a href="logout.php"><span>🚪</span> Log Out</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <h1>Admin Manual — Ticket Workflow</h1>
    </div>

    <div class="content-area" style="max-width:900px;">

      <div class="card">
        <div class="card-header"><h3>🎫 Ticket Lifecycle Overview</h3></div>
        <div class="card-body">
          <p>Tickets are created two ways: a customer submits one from the portal (<code>Submit Ticket</code>), or an admin can view/manage any incoming ticket here. Every ticket belongs to exactly one customer, has one <strong>status</strong>, one <strong>priority</strong>, and a <strong>category</strong>.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>🚦 Statuses</h3></div>
        <div class="card-body">
          <table class="data-table">
            <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
            <tbody>
              <tr><td><span class="badge badge-open">Open</span></td><td>New or unactioned — needs a first response.</td></tr>
              <tr><td><span class="badge badge-in_progress">In Progress</span></td><td>An admin is actively working the issue.</td></tr>
              <tr><td><span class="badge badge-waiting">Waiting</span></td><td>Blocked on the customer (e.g. waiting for info or access).</td></tr>
              <tr><td><span class="badge badge-resolved">Resolved</span></td><td>Fix applied; awaiting confirmation or auto-closing.</td></tr>
              <tr><td><span class="badge badge-closed">Closed</span></td><td>Done — no further action expected.</td></tr>
            </tbody>
          </table>
          <p style="margin-top:12px;">Change status from the dropdown on the ticket list or the single-ticket view — it saves immediately, no separate "Save" click needed.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>🔁 Reopening a Ticket</h3></div>
        <div class="card-body">
          <p>Today, reopening is simply changing the status dropdown back to <strong>Open</strong> or <strong>In Progress</strong> on a Resolved/Closed ticket — there's no restriction on the transition, and no dedicated "Reopen" button. Any admin can do this at any time.</p>
          <p><strong>Customers cannot reopen tickets themselves.</strong> The customer-facing ticket view only lets them add a reply — it has no status control at all. If a customer replies to a Closed ticket, the status does <em>not</em> change automatically; an admin has to notice the reply and manually move it back to Open.</p>
          <p class="alert alert-info" style="margin-top:12px;">
            <strong>💡 Suggested for next version:</strong> Add an explicit "Reopen" action that customers can trigger themselves from a resolved/closed ticket (rather than relying on staff to notice a reply and flip the status manually), and consider auto-reopening a ticket when a customer replies to a Resolved one.
          </p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>👤 Assignment</h3></div>
        <div class="card-body">
          <p>Each ticket has an <code>assigned_to</code> field tied to an admin user, and the assigned admin's name is shown in the ticket list, the single-ticket view, and the dashboard ("Assigned" column / field).</p>
          <p class="alert alert-info" style="margin-top:12px;">
            <strong>💡 Suggested for next version:</strong> There is currently <strong>no way to actually set or change an assignment from the GUI</strong> — every ticket shows "Unassigned" unless the value is set directly in the database. Add an "Assign to" dropdown (populated from <code>admin_users</code>) on both the ticket list row and the single-ticket view, and consider notifying the assigned admin by email when a ticket is assigned to them.
          </p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>📈 Progress / % Complete</h3></div>
        <div class="card-body">
          <p class="alert alert-info" style="margin-top:0;">
            <strong>💡 Suggested for next version:</strong> There is no percent-complete or progress tracking anywhere in the app today — only the five statuses above exist. If finer-grained progress reporting is wanted (e.g. for longer engagements/projects rather than quick support tickets), it would need a new field on the ticket and a corresponding UI control (e.g. a slider or preset milestones like 25/50/75/100%).
          </p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>💬 Replies vs. Internal Notes</h3></div>
        <div class="card-body">
          <p>From a ticket's detail view, the reply box has two submit buttons:</p>
          <ul style="margin-left:20px;line-height:1.8;">
            <li><strong>Send Reply to Customer</strong> — posts the comment to the shared thread and emails the customer a notification.</li>
            <li><strong>Save Internal Note</strong> — posts the comment marked internal-only; the customer never sees it and no email is sent.</li>
          </ul>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>📋 Bulk Actions &amp; Filters</h3></div>
        <div class="card-body">
          <p>The ticket list can be filtered by status, priority, category, or a text search on subject/customer name. Checkboxes let you select multiple tickets and apply a single status change to all of them at once via <strong>Bulk Update Status</strong>.</p>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>⚡ Priority Levels</h3></div>
        <div class="card-body">
          <p><span class="badge badge-low">Low</span> <span class="badge badge-medium">Medium</span> <span class="badge badge-high">High</span> <span class="badge badge-critical">Critical</span> — set by the customer at submission time; not currently editable by an admin after creation.</p>
          <p class="alert alert-info" style="margin-top:12px;">
            <strong>💡 Suggested for next version:</strong> Allow an admin to re-triage/override the priority a customer selected, since customers may under- or over-state urgency.
          </p>
        </div>
      </div>

    </div>
  </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
