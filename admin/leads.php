<?php
/**
 * NexaTech Solutions — Admin Contact Inquiry (Lead) Tracking
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdmin();

$db = Database::getInstance();

$validStatuses = ['new', 'contacted', 'converted', 'closed'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $leadId    = (int) ($_POST['lead_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    if ($leadId && in_array($newStatus, $validStatuses)) {
        $db->execute("UPDATE contact_submissions SET status = ? WHERE id = ?", [$newStatus, $leadId]);
    }
    redirect('leads.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// Handle sending a reply email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_reply') {
    $leadId  = (int) ($_POST['lead_id'] ?? 0);
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');
    $sent    = 0;

    $lead = $leadId ? $db->fetchOne("SELECT * FROM contact_submissions WHERE id = ?", [$leadId]) : null;

    if ($lead && $subject && $message) {
        $body = "<p>Hi " . htmlspecialchars($lead['name']) . ",</p>"
              . "<p>" . nl2br(htmlspecialchars($message)) . "</p>"
              . "<p>— " . ADMIN_NAME . "<br />NexaTech Solutions</p>";

        $sent = sendEmail($lead['email'], $subject, $body) ? 1 : 0;

        if ($sent && $lead['status'] === 'new') {
            $db->execute("UPDATE contact_submissions SET status = 'contacted' WHERE id = ?", [$leadId]);
        }
    }

    redirect("leads.php?id={$leadId}&sent={$sent}");
}

// Single lead view
$viewId = (int) ($_GET['id'] ?? 0);
$lead   = null;
if ($viewId) {
    $lead = $db->fetchOne("SELECT * FROM contact_submissions WHERE id = ?", [$viewId]);
}

// Filters (list view)
$filterStatus = sanitize($_GET['status'] ?? '');
$filterSearch = sanitize($_GET['search'] ?? '');

$where  = ['1=1'];
$params = [];

if ($filterStatus && in_array($filterStatus, $validStatuses)) {
    $where[] = 'status = ?';
    $params[] = $filterStatus;
} elseif (!$filterStatus) {
    // Hide closed leads from the default view; still reachable via the Closed filter
    $where[] = "status != 'closed'";
}
if ($filterSearch) {
    $where[] = '(name LIKE ? OR company LIKE ? OR email LIKE ?)';
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
    $params[] = '%' . $filterSearch . '%';
}

$whereSQL = implode(' AND ', $where);

$leads = $db->fetchAll(
    "SELECT * FROM contact_submissions WHERE {$whereSQL} ORDER BY created_at DESC LIMIT 200",
    $params
);

$csrfToken = generateCsrfToken();
$username  = htmlspecialchars($_SESSION['admin_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Leads — NexaTech Admin</title>
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
      <a href="tickets.php"><span class="nav-icon">🎫</span> Tickets</a>
      <a href="leads.php" class="active"><span class="nav-icon">📩</span> Leads</a>
      <a href="customers.php"><span class="nav-icon">👥</span> Customers</a>
      <a href="help.php"><span class="nav-icon">📖</span> Manual</a>
    </nav>
    <div class="sidebar-footer"><a href="logout.php"><span>🚪</span> Log Out</a></div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <h1><?= $lead ? 'Inquiry from ' . htmlspecialchars($lead['name']) : 'Contact Inquiries' ?></h1>
      <div class="topbar-actions">
        <?php if ($lead): ?>
          <a href="leads.php" class="btn btn-ghost btn-sm">← All Inquiries</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="content-area">

      <?php if ($lead): ?>
        <!-- ======= SINGLE LEAD VIEW ======= -->

        <?php if (isset($_GET['sent'])): ?>
          <div class="alert alert-<?= $_GET['sent'] === '1' ? 'success' : 'error' ?>">
            <?= $_GET['sent'] === '1' ? 'Reply sent successfully.' : 'Failed to send reply. Please try again.' ?>
          </div>
        <?php endif; ?>

        <div class="ticket-header-card">
          <h2 style="margin-bottom:8px;"><?= htmlspecialchars($lead['name']) ?></h2>
          <div class="ticket-meta">
            <span class="badge badge-<?= htmlspecialchars($lead['status']) ?>"><?= ucfirst($lead['status']) ?></span>
            <span class="ticket-meta-item">✉️ <a href="mailto:<?= htmlspecialchars($lead['email']) ?>"><?= htmlspecialchars($lead['email']) ?></a></span>
            <?php if ($lead['company']): ?>
              <span class="ticket-meta-item">🏢 <?= htmlspecialchars($lead['company']) ?></span>
            <?php endif; ?>
            <?php if ($lead['phone']): ?>
              <span class="ticket-meta-item">📞 <?= htmlspecialchars($lead['phone']) ?></span>
            <?php endif; ?>
            <span class="ticket-meta-item">📅 <?= formatDate($lead['created_at']) ?></span>
            <?php if ($lead['service_interest']): ?>
              <span class="ticket-meta-item">📁 <?= htmlspecialchars(ucfirst(str_replace('_',' ',$lead['service_interest']))) ?></span>
            <?php endif; ?>
          </div>

          <!-- Quick status control -->
          <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
            <form method="POST" action="leads.php?id=<?= $viewId ?>" style="display:flex;gap:8px;align-items:center;">
              <input type="hidden" name="action" value="update_status" />
              <input type="hidden" name="lead_id" value="<?= $viewId ?>" />
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
              <label style="font-size:.8rem;color:var(--text-secondary);">Status:</label>
              <select name="status" class="inline-select auto-submit">
                <?php foreach ($validStatuses as $s): ?>
                  <option value="<?=$s?>" <?=$lead['status']===$s?'selected':''?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>

        <!-- Message -->
        <div class="card">
          <div class="card-header"><h3>📝 Message</h3></div>
          <div class="card-body">
            <p style="white-space:pre-line;line-height:1.7;"><?= nl2br(htmlspecialchars($lead['message'])) ?></p>
          </div>
        </div>

        <!-- Reply -->
        <div class="card">
          <div class="card-header"><h3>↩️ Send Reply</h3></div>
          <div class="card-body">
            <form method="POST" action="leads.php">
              <input type="hidden" name="action" value="send_reply" />
              <input type="hidden" name="lead_id" value="<?= $viewId ?>" />
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
              <div class="form-group">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" required
                       value="Re: Your inquiry to NexaTech Solutions" />
              </div>
              <div class="form-group">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="6" required
                          placeholder="Write a reply to send by email..."></textarea>
              </div>
              <button type="submit" class="btn btn-primary">Send Reply</button>
            </form>
          </div>
        </div>

      <?php else: ?>
        <!-- ======= LIST VIEW ======= -->

        <!-- Filter Bar -->
        <form method="GET" action="leads.php">
          <div class="filter-bar">
            <div class="filter-group">
              <label>Status</label>
              <select name="status" class="form-control auto-submit">
                <option value="">All (except Closed)</option>
                <?php foreach ($validStatuses as $s): ?>
                  <option value="<?=$s?>" <?=$filterStatus===$s?'selected':''?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="filter-group flex-1">
              <label>Search</label>
              <input type="text" name="search" class="form-control" placeholder="Name, company, or email..." value="<?= htmlspecialchars($filterSearch) ?>" />
            </div>
            <div class="filter-group" style="justify-content:flex-end;">
              <button type="submit" class="btn btn-primary btn-sm">Search</button>
              <a href="leads.php" class="btn btn-ghost btn-sm" style="margin-top:4px;">Clear</a>
            </div>
          </div>
        </form>

        <div class="card">
          <div class="card-header">
            <h3>📩 Inquiries (<?= count($leads) ?>)</h3>
          </div>
          <div class="card-body no-pad">
            <?php if (empty($leads)): ?>
              <div style="padding:40px;text-align:center;"><p>No inquiries match the current filters.</p></div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Company</th>
                      <th>Email</th>
                      <th>Phone</th>
                      <th>Service Interest</th>
                      <th>Message</th>
                      <th>Status</th>
                      <th>Submitted</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($leads as $l): ?>
                      <tr>
                        <td style="font-weight:500;font-size:.88rem;">
                          <a href="leads.php?id=<?= $l['id'] ?>" style="color:var(--text-primary);font-weight:500;">
                            <?= htmlspecialchars($l['name']) ?>
                          </a>
                        </td>
                        <td class="td-secondary"><?= htmlspecialchars($l['company'] ?: '—') ?></td>
                        <td><a href="mailto:<?= htmlspecialchars($l['email']) ?>"><?= htmlspecialchars($l['email']) ?></a></td>
                        <td class="td-secondary"><?= htmlspecialchars($l['phone'] ?: '—') ?></td>
                        <td class="td-secondary"><?= htmlspecialchars(ucfirst(str_replace('_',' ',$l['service_interest'] ?: '—'))) ?></td>
                        <td class="td-secondary" style="max-width:260px;" title="<?= htmlspecialchars($l['message']) ?>">
                          <?= htmlspecialchars(truncate($l['message'], 60)) ?>
                        </td>
                        <td>
                          <form method="POST" action="leads.php<?= $_SERVER['QUERY_STRING'] ? '?' . htmlspecialchars($_SERVER['QUERY_STRING']) : '' ?>">
                            <input type="hidden" name="action" value="update_status" />
                            <input type="hidden" name="lead_id" value="<?= $l['id'] ?>" />
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
                            <select name="status" class="inline-select auto-submit">
                              <?php foreach ($validStatuses as $s): ?>
                                <option value="<?=$s?>" <?=$l['status']===$s?'selected':''?>><?= ucfirst($s) ?></option>
                              <?php endforeach; ?>
                            </select>
                          </form>
                        </td>
                        <td class="td-secondary"><?= timeAgo($l['created_at']) ?></td>
                        <td>
                          <a href="leads.php?id=<?= $l['id'] ?>" class="btn btn-ghost btn-sm">View / Reply</a>
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
<script src="../assets/js/main.js?v=3"></script>
</body>
</html>
