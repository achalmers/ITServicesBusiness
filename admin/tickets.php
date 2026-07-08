<?php
/**
 * NexaTech Solutions — Admin Ticket Management
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = Database::getInstance();

// Handle inline status update (AJAX or form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitize($_POST['action']);

    if ($action === 'update_status') {
        $ticketId  = (int) ($_POST['ticket_id'] ?? 0);
        $newStatus = sanitize($_POST['status'] ?? '');
        $valid     = ['open','in_progress','waiting','resolved','closed'];
        if ($ticketId && in_array($newStatus, $valid)) {
            $resolvedAt = in_array($newStatus, ['resolved','closed']) ? 'NOW()' : 'NULL';
            $db->execute(
                "UPDATE tickets SET status = ?, resolved_at = {$resolvedAt}, updated_at = NOW() WHERE id = ?",
                [$newStatus, $ticketId]
            );
        }
        redirect(!empty($_POST['return_view']) ? "tickets.php?id={$ticketId}" : 'tickets.php');
    }

    if ($action === 'add_comment') {
        $ticketId  = (int) ($_POST['ticket_id'] ?? 0);
        $comment   = sanitize($_POST['comment'] ?? '');
        $internal  = (int) ($_POST['is_internal'] ?? 0);
        $adminId   = (int) $_SESSION['admin_id'];

        if ($ticketId && !empty($comment)) {
            $db->execute(
                "INSERT INTO ticket_comments (ticket_id, author_type, author_id, comment, is_internal) VALUES (?, 'admin', ?, ?, ?)",
                [$ticketId, $adminId, $comment, $internal]
            );
            $db->execute("UPDATE tickets SET updated_at = NOW() WHERE id = ?", [$ticketId]);

            // Notify customer if not internal
            if (!$internal) {
                $ticket   = $db->fetchOne("SELECT t.subject, c.email, c.first_name FROM tickets t JOIN customers c ON c.id = t.customer_id WHERE t.id = ?", [$ticketId]);
                if ($ticket) {
                    $body = "<p>Hi " . htmlspecialchars($ticket['first_name']) . ",</p>"
                          . "<p>NexaTech Solutions has replied to your support ticket:</p>"
                          . "<p><strong>Ticket #" . $ticketId . ":</strong> " . htmlspecialchars($ticket['subject']) . "</p>"
                          . "<blockquote style='border-left:3px solid #00d4ff;padding-left:12px;color:#555;'>"
                          . nl2br(htmlspecialchars($comment)) . "</blockquote>"
                          . "<p><a href='" . SITE_URL . "/portal/ticket-detail.php?id={$ticketId}' style='color:#00d4ff;'>View &amp; Reply in Portal</a></p>";
                    sendEmail($ticket['email'], "Reply to Ticket #{$ticketId}: " . htmlspecialchars($ticket['subject']), $body);
                }
            }
        }
        redirect("tickets.php?id={$ticketId}");
    }

    if ($action === 'bulk_update') {
        $ids       = array_map('intval', $_POST['ticket_ids'] ?? []);
        $newStatus = sanitize($_POST['bulk_status'] ?? '');
        $valid     = ['open','in_progress','waiting','resolved','closed'];
        if (!empty($ids) && in_array($newStatus, $valid)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$newStatus], $ids);
            $db->execute("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})", $params);
        }
        redirect('tickets.php');
    }
}

// Filters
$filterStatus   = sanitize($_GET['status'] ?? '');
$filterPriority = sanitize($_GET['priority'] ?? '');
$filterCategory = sanitize($_GET['category'] ?? '');
$filterSearch   = sanitize($_GET['search'] ?? '');
$viewId         = (int) ($_GET['id'] ?? 0);

// Build query
$where  = ['1=1'];
$params = [];

if ($filterStatus && in_array($filterStatus, ['open','in_progress','waiting','resolved','closed'])) {
    $where[] = 't.status = ?'; $params[] = $filterStatus;
}
if ($filterPriority && in_array($filterPriority, ['low','medium','high','critical'])) {
    $where[] = 't.priority = ?'; $params[] = $filterPriority;
}
if ($filterCategory) {
    $where[] = 't.category = ?'; $params[] = $filterCategory;
}
if ($filterSearch) {
    $where[] = '(t.subject LIKE ? OR CONCAT(c.first_name," ",c.last_name) LIKE ?)';
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
}

$whereSQL = implode(' AND ', $where);

$tickets = $db->fetchAll(
    "SELECT t.id, t.subject, t.category, t.priority, t.status, t.created_at, t.updated_at,
            CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.company, c.email,
            a.username AS assigned_to_name
     FROM tickets t
     JOIN customers c ON c.id = t.customer_id
     LEFT JOIN admin_users a ON a.id = t.assigned_to
     WHERE {$whereSQL}
     ORDER BY FIELD(t.priority,'critical','high','medium','low'), t.updated_at ASC
     LIMIT 100",
    $params
);

// If viewing a specific ticket
$ticket   = null;
$comments = [];
if ($viewId) {
    $ticket = $db->fetchOne(
        "SELECT t.*, CONCAT(c.first_name,' ',c.last_name) AS customer_name, c.company, c.email,
                a.username AS assigned_to_name
         FROM tickets t
         JOIN customers c ON c.id = t.customer_id
         LEFT JOIN admin_users a ON a.id = t.assigned_to
         WHERE t.id = ?",
        [$viewId]
    );
    if ($ticket) {
        // Auto-assign to the first admin who opens an unassigned ticket
        if ($ticket['assigned_to'] === null) {
            $adminId = (int) $_SESSION['admin_id'];
            $db->execute("UPDATE tickets SET assigned_to = ? WHERE id = ?", [$adminId, $viewId]);
            $ticket['assigned_to']      = $adminId;
            $ticket['assigned_to_name'] = $_SESSION['admin_username'];
        }

        $comments = $db->fetchAll(
            "SELECT tc.*,
                    CASE tc.author_type
                      WHEN 'customer' THEN CONCAT(c.first_name,' ',c.last_name)
                      WHEN 'admin'    THEN CONCAT('Admin: ',a.username)
                    END AS author_name
             FROM ticket_comments tc
             LEFT JOIN customers c   ON tc.author_type='customer' AND c.id=tc.author_id
             LEFT JOIN admin_users a ON tc.author_type='admin'    AND a.id=tc.author_id
             WHERE tc.ticket_id = ?
             ORDER BY tc.created_at ASC",
            [$viewId]
        );
    }
}

$adminUsers = $db->fetchAll("SELECT id, username FROM admin_users ORDER BY username", []);
$csrfToken  = generateCsrfToken();
$username   = htmlspecialchars($_SESSION['admin_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Tickets — NexaTech Admin</title>
  <meta name="csrf-token" content="<?= $csrfToken ?>" />
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
      <a href="tickets.php" class="active"><span class="nav-icon">🎫</span> Tickets</a>
      <a href="leads.php"><span class="nav-icon">📩</span> Leads</a>
      <a href="customers.php"><span class="nav-icon">👥</span> Customers</a>
      <a href="staff.php"><span class="nav-icon">🔑</span> Staff</a>
      <a href="automation-setup.php"><span class="nav-icon">🤖</span> New Client Setup</a>
      <a href="help.php"><span class="nav-icon">📖</span> Manual</a>
    </nav>
    <div class="sidebar-footer"><a href="logout.php"><span>🚪</span> Log Out</a></div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <h1><?= $ticket ? 'Ticket #' . $viewId : 'All Tickets' ?></h1>
      <div class="topbar-actions">
        <?php if ($ticket): ?>
          <a href="tickets.php" class="btn btn-ghost btn-sm">← All Tickets</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="content-area">

      <?php if ($ticket): ?>
        <!-- ======= SINGLE TICKET VIEW ======= -->
        <div class="ticket-header-card">
          <h2 style="margin-bottom:8px;"><?= htmlspecialchars($ticket['subject']) ?></h2>
          <div class="ticket-meta">
            <span class="<?= getStatusBadgeClass($ticket['status']) ?>"><?= getStatusLabel($ticket['status']) ?></span>
            <span class="<?= getPriorityBadgeClass($ticket['priority']) ?>"><?= ucfirst($ticket['priority']) ?></span>
            <span class="ticket-meta-item">👤 <?= htmlspecialchars($ticket['customer_name']) ?></span>
            <?php if ($ticket['company']): ?>
              <span class="ticket-meta-item">🏢 <?= htmlspecialchars($ticket['company']) ?></span>
            <?php endif; ?>
            <span class="ticket-meta-item">📅 <?= formatDate($ticket['created_at']) ?></span>
            <span class="ticket-meta-item">📁 <?= ucfirst($ticket['category']) ?></span>
            <span class="ticket-meta-item">🧑‍💼 <?= $ticket['assigned_to_name'] ? htmlspecialchars($ticket['assigned_to_name']) : 'Unassigned' ?></span>
          </div>

          <!-- Quick controls -->
          <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <form method="POST" action="tickets.php" style="display:flex;gap:8px;align-items:center;">
              <input type="hidden" name="action" value="update_status" />
              <input type="hidden" name="ticket_id" value="<?= $viewId ?>" />
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
              <input type="hidden" name="return_view" value="1" />
              <label style="font-size:.8rem;color:var(--text-secondary);">Status:</label>
              <select name="status" class="inline-select auto-submit">
                <?php foreach (['open','in_progress','waiting','resolved','closed'] as $s): ?>
                  <option value="<?= $s ?>" <?= $ticket['status']===$s?'selected':'' ?>><?= getStatusLabel($s) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
            <a href="mailto:<?= htmlspecialchars($ticket['email']) ?>" class="btn btn-ghost btn-sm">✉️ Email Customer</a>
          </div>
        </div>

        <!-- Description -->
        <div class="card">
          <div class="card-header"><h3>📝 Description</h3></div>
          <div class="card-body">
            <p style="white-space:pre-line;line-height:1.7;"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
          </div>
        </div>

        <!-- Comments -->
        <?php if (!empty($comments)): ?>
          <div class="card">
            <div class="card-header"><h3>💬 Thread (<?= count($comments) ?>)</h3></div>
            <div class="card-body">
              <div class="comments-thread">
                <?php foreach ($comments as $c): ?>
                  <div class="comment">
                    <div class="comment-avatar <?= $c['author_type'] ?><?= $c['is_internal']?' internal':'' ?>">
                      <?= strtoupper(substr($c['author_name'],0,2)) ?>
                    </div>
                    <div class="comment-body">
                      <div class="comment-meta">
                        <strong><?= htmlspecialchars($c['author_name']) ?></strong>
                        <?php if ($c['is_internal']): ?><span class="internal-label">Internal Note</span><?php endif; ?>
                        <span><?= timeAgo($c['created_at']) ?></span>
                      </div>
                      <div class="comment-bubble <?= $c['author_type'] ?>-bubble <?= $c['is_internal']?'internal-bubble':'' ?>">
                        <?= nl2br(htmlspecialchars($c['comment'])) ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Admin Reply Form -->
        <div class="card">
          <div class="card-header"><h3>↩️ Add Reply / Internal Note</h3></div>
          <div class="card-body">
            <form method="POST" action="tickets.php">
              <input type="hidden" name="action" value="add_comment" />
              <input type="hidden" name="ticket_id" value="<?= $viewId ?>" />
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
              <div class="form-group">
                <textarea name="comment" class="form-control" rows="5" required
                          placeholder="Write a reply to the customer, or an internal note for your team..."></textarea>
              </div>
              <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary" name="is_internal" value="0">Send Reply to Customer</button>
                <button type="submit" class="btn btn-outline" name="is_internal" value="1"
                        style="border-color:#fbbf24;color:#fbbf24;">📌 Save Internal Note</button>
              </div>
            </form>
          </div>
        </div>

      <?php else: ?>
        <!-- ======= TICKET LIST VIEW ======= -->

        <!-- Filter Bar -->
        <form method="GET" action="tickets.php">
          <div class="filter-bar">
            <div class="filter-group">
              <label>Status</label>
              <select name="status" class="form-control auto-submit">
                <option value="">All Statuses</option>
                <?php foreach (['open','in_progress','waiting','resolved','closed'] as $s): ?>
                  <option value="<?=$s?>" <?=$filterStatus===$s?'selected':''?>><?= getStatusLabel($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-group">
              <label>Priority</label>
              <select name="priority" class="form-control auto-submit">
                <option value="">All Priorities</option>
                <?php foreach (['low','medium','high','critical'] as $p): ?>
                  <option value="<?=$p?>" <?=$filterPriority===$p?'selected':''?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-group">
              <label>Category</label>
              <select name="category" class="form-control auto-submit">
                <option value="">All Categories</option>
                <?php foreach (['network','cloud','security','hardware','software','backup','remote','consulting','other'] as $cat): ?>
                  <option value="<?=$cat?>" <?=$filterCategory===$cat?'selected':''?>><?= ucfirst($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-group flex-1">
              <label>Search</label>
              <input type="text" name="search" class="form-control" placeholder="Subject or customer name..." value="<?= htmlspecialchars($filterSearch) ?>" />
            </div>
            <div class="filter-group" style="justify-content:flex-end;">
              <button type="submit" class="btn btn-primary btn-sm">Search</button>
              <a href="tickets.php" class="btn btn-ghost btn-sm" style="margin-top:4px;">Clear</a>
            </div>
          </div>
        </form>

        <!-- Bulk Update -->
        <form method="POST" action="tickets.php" id="bulk-form">
          <input type="hidden" name="action" value="bulk_update" />
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />

          <div class="card">
            <div class="card-header">
              <h3>🎫 Tickets (<?= count($tickets) ?>)</h3>
              <div style="display:flex;gap:8px;align-items:center;">
                <select name="bulk_status" class="inline-select">
                  <option value="">Bulk Update Status…</option>
                  <?php foreach (['open','in_progress','waiting','resolved','closed'] as $s): ?>
                    <option value="<?=$s?>"><?= getStatusLabel($s) ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-ghost btn-sm">Apply</button>
              </div>
            </div>
            <div class="card-body no-pad">
              <?php if (empty($tickets)): ?>
                <div style="padding:40px;text-align:center;"><p>No tickets match the current filters.</p></div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="data-table">
                    <thead>
                      <tr>
                        <th><input type="checkbox" id="select-all" title="Select all" /></th>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned</th>
                        <th>Updated</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($tickets as $t): ?>
                        <tr>
                          <td><input type="checkbox" name="ticket_ids[]" value="<?= $t['id'] ?>" /></td>
                          <td class="td-secondary">#<?= $t['id'] ?></td>
                          <td>
                            <div style="font-weight:500;font-size:.88rem;"><?= htmlspecialchars($t['customer_name']) ?></div>
                            <?php if ($t['company']): ?>
                              <div class="td-secondary" style="font-size:.78rem;"><?= htmlspecialchars($t['company']) ?></div>
                            <?php endif; ?>
                          </td>
                          <td>
                            <a href="tickets.php?id=<?= $t['id'] ?>" style="color:var(--text-primary);font-size:.9rem;font-weight:500;">
                              <?= htmlspecialchars(truncate($t['subject'], 45)) ?>
                            </a>
                          </td>
                          <td class="td-secondary"><?= ucfirst($t['category']) ?></td>
                          <td><span class="<?= getPriorityBadgeClass($t['priority']) ?>"><?= ucfirst($t['priority']) ?></span></td>
                          <td>
                            <form method="POST" action="tickets.php">
                              <input type="hidden" name="action" value="update_status" />
                              <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>" />
                              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
                              <select name="status" class="inline-select auto-submit">
                                <?php foreach (['open','in_progress','waiting','resolved','closed'] as $s): ?>
                                  <option value="<?=$s?>" <?=$t['status']===$s?'selected':''?>><?= getStatusLabel($s) ?></option>
                                <?php endforeach; ?>
                              </select>
                            </form>
                          </td>
                          <td class="td-secondary"><?= $t['assigned_to_name'] ? htmlspecialchars($t['assigned_to_name']) : '—' ?></td>
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
        </form>

      <?php endif; ?>

    </div>
  </main>
</div>

<script src="../assets/js/main.js?v=7"></script>
</body>
</html>
