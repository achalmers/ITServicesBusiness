<?php
/**
 * NexaTech Solutions — Ticket Detail (Customer View)
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db         = Database::getInstance();
$customerId = (int) $_SESSION['customer_id'];
$ticketId   = (int) ($_GET['id'] ?? 0);

if (!$ticketId) redirect('dashboard.php');

// Fetch ticket — ensure it belongs to this customer
$ticket = $db->fetchOne(
    "SELECT t.*, c.first_name, c.last_name, c.company,
            a.username AS assigned_username
     FROM tickets t
     JOIN customers c ON c.id = t.customer_id
     LEFT JOIN admin_users a ON a.id = t.assigned_to
     WHERE t.id = ? AND t.customer_id = ?",
    [$ticketId, $customerId]
);

if (!$ticket) {
    // Ticket not found or doesn't belong to customer
    redirect('dashboard.php');
}

// Fetch customer info
$customer = $db->fetchOne("SELECT first_name, last_name, company FROM customers WHERE id = ?", [$customerId]);

// Handle reply POST
$replyError   = '';
$replySuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        $replyError = 'Invalid security token.';
    } elseif (in_array($ticket['status'], ['resolved', 'closed'])) {
        $replyError = 'This ticket is closed. Please open a new ticket if you need further help.';
    } else {
        $comment = sanitize($_POST['comment'] ?? '');
        if (empty($comment)) {
            $replyError = 'Reply cannot be empty.';
        } else {
            try {
                $db->execute(
                    "INSERT INTO ticket_comments (ticket_id, author_type, author_id, comment, is_internal)
                     VALUES (?, 'customer', ?, ?, 0)",
                    [$ticketId, $customerId, $comment]
                );

                // Update ticket updated_at and set to open if resolved
                $newStatus = in_array($ticket['status'], ['resolved','closed']) ? 'open' : $ticket['status'];
                if ($ticket['status'] === 'waiting') $newStatus = 'open';

                $db->execute(
                    "UPDATE tickets SET updated_at = NOW(), status = ? WHERE id = ?",
                    [$newStatus, $ticketId]
                );

                // Notify admin
                $body = "<p>" . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name'])
                      . " added a reply to <strong>Ticket #{$ticketId}</strong>:</p>"
                      . "<blockquote style='border-left:3px solid #00d4ff;padding-left:12px;color:#555;'>"
                      . nl2br(htmlspecialchars($comment)) . "</blockquote>"
                      . "<p><a href='" . SITE_URL . "/admin/tickets.php?id={$ticketId}' style='color:#00d4ff;'>View ticket</a></p>";
                sendEmail(ADMIN_EMAIL, "Reply on Ticket #{$ticketId}: " . htmlspecialchars($ticket['subject']), $body);

                // Reload to show new comment
                redirect("ticket-detail.php?id={$ticketId}&replied=1");
            } catch (Exception $e) {
                $replyError = 'Failed to submit reply. Please try again.';
            }
        }
    }
}

// Fetch comments (exclude internal notes from customer view)
$comments = $db->fetchAll(
    "SELECT tc.*,
            CASE tc.author_type
              WHEN 'customer' THEN CONCAT(c.first_name, ' ', c.last_name)
              WHEN 'admin'    THEN CONCAT('NexaTech — ', a.username)
            END AS author_name
     FROM ticket_comments tc
     LEFT JOIN customers   c ON tc.author_type = 'customer' AND c.id = tc.author_id
     LEFT JOIN admin_users a ON tc.author_type = 'admin'    AND a.id = tc.author_id
     WHERE tc.ticket_id = ? AND tc.is_internal = 0
     ORDER BY tc.created_at ASC",
    [$ticketId]
);

$csrfToken  = generateCsrfToken();
$isClosed   = in_array($ticket['status'], ['resolved', 'closed']);
$initials   = strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1));
$replied    = isset($_GET['replied']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Ticket #<?= $ticketId ?> — NexaTech Portal</title>
  <link rel="stylesheet" href="../assets/css/styles.css?v=3" />
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
        <h4><?= htmlspecialchars($customer['first_name']) ?></h4>
        <span><?= htmlspecialchars($customer['company'] ?? 'Customer') ?></span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
      <a href="submit-ticket.php"><span class="nav-icon">➕</span> Submit Ticket</a>
      <a href="dashboard.php#tickets" class="active"><span class="nav-icon">🎫</span> My Tickets</a>
    </nav>
    <div class="sidebar-footer">
      <a href="logout.php"><span>🚪</span> Log Out</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="topbar">
      <h1>Ticket #<?= $ticketId ?></h1>
      <div class="topbar-actions">
        <a href="dashboard.php" class="btn btn-ghost btn-sm">← My Tickets</a>
      </div>
    </div>

    <div class="content-area">

      <?php if ($replied): ?>
        <div class="alert alert-success">Your reply has been sent. We'll get back to you soon.</div>
      <?php endif; ?>

      <!-- Ticket Header Card -->
      <div class="ticket-header-card">
        <h2 style="margin-bottom:8px;"><?= htmlspecialchars($ticket['subject']) ?></h2>
        <div class="ticket-meta">
          <span class="<?= getStatusBadgeClass($ticket['status']) ?>"><?= getStatusLabel($ticket['status']) ?></span>
          <span class="<?= getPriorityBadgeClass($ticket['priority']) ?>"><?= ucfirst($ticket['priority']) ?> Priority</span>
          <span class="ticket-meta-item">📁 <?= ucfirst($ticket['category']) ?></span>
          <span class="ticket-meta-item">📅 Opened <?= formatDate($ticket['created_at']) ?></span>
          <?php if ($ticket['assigned_username']): ?>
            <span class="ticket-meta-item">👤 Assigned to <?= htmlspecialchars($ticket['assigned_username']) ?></span>
          <?php endif; ?>
          <?php if ($ticket['resolved_at']): ?>
            <span class="ticket-meta-item" style="color:var(--accent-green);">✅ Resolved <?= formatDate($ticket['resolved_at']) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Original Description -->
      <div class="card">
        <div class="card-header">
          <h3>📝 Issue Description</h3>
        </div>
        <div class="card-body">
          <p style="white-space:pre-line;line-height:1.7;"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>
        </div>
      </div>

      <!-- Comments Thread -->
      <?php if (!empty($comments)): ?>
        <div class="card">
          <div class="card-header">
            <h3>💬 Conversation (<?= count($comments) ?> <?= count($comments) === 1 ? 'message' : 'messages' ?>)</h3>
          </div>
          <div class="card-body">
            <div class="comments-thread">
              <?php foreach ($comments as $c): ?>
                <div class="comment">
                  <div class="comment-avatar <?= $c['author_type'] ?>">
                    <?= strtoupper(substr($c['author_name'], 0, 2)) ?>
                  </div>
                  <div class="comment-body">
                    <div class="comment-meta">
                      <strong><?= htmlspecialchars($c['author_name']) ?></strong>
                      <span><?= timeAgo($c['created_at']) ?></span>
                      <span style="color:var(--text-secondary);font-size:.75rem;"><?= formatDate($c['created_at'], 'M j, Y g:i A') ?></span>
                    </div>
                    <div class="comment-bubble <?= $c['author_type'] ?>-bubble">
                      <?= nl2br(htmlspecialchars($c['comment'])) ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Reply Form -->
      <?php if ($isClosed): ?>
        <div class="alert alert-info">
          This ticket has been <?= htmlspecialchars($ticket['status']) ?>.
          If you're still experiencing issues, please <a href="submit-ticket.php">open a new ticket</a>.
        </div>
      <?php else: ?>
        <div class="card">
          <div class="card-header">
            <h3>↩️ Add a Reply</h3>
          </div>
          <div class="card-body">
            <?php if ($replyError): ?>
              <div class="alert alert-error"><?= htmlspecialchars($replyError) ?></div>
            <?php endif; ?>
            <div id="reply-msg"></div>
            <form method="POST" action="ticket-detail.php?id=<?= $ticketId ?>" id="reply-form">
              <input type="hidden" name="action" value="reply" />
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
              <div class="form-group">
                <textarea name="comment" class="form-control" rows="5" required
                          placeholder="Add more details, confirm if the issue is resolved, or ask follow-up questions..."></textarea>
              </div>
              <div style="display:flex;gap:12px;align-items:center;">
                <button type="submit" class="btn btn-primary">Send Reply</button>
                <span style="font-size:.82rem;color:var(--text-secondary);">We typically respond within 2 business hours</span>
              </div>
            </form>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>
<script src="../assets/js/main.js?v=6"></script>
</body>
</html>
