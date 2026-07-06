<?php
/**
 * NexaTech Solutions — Submit Support Ticket
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$db         = Database::getInstance();
$customerId = (int) $_SESSION['customer_id'];

$customer = $db->fetchOne(
    "SELECT id, first_name, last_name, email, company FROM customers WHERE id = ?",
    [$customerId]
);
if (!$customer) { session_destroy(); redirect('index.php'); }

$success  = false;
$ticketId = 0;
$error    = '';

// Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        if (isset($_POST['_ajax'])) {
            jsonError('Invalid security token.');
        }
        $error = 'Invalid security token. Please refresh the page.';
    } else {
        $subject     = sanitize($_POST['subject'] ?? '');
        $category    = sanitize($_POST['category'] ?? 'other');
        $priority    = sanitize($_POST['priority'] ?? 'medium');
        $description = sanitize($_POST['description'] ?? '');

        $validCategories = ['network','cloud','security','hardware','software','backup','remote','consulting','other'];
        $validPriorities = ['low','medium','high','critical'];

        if (empty($subject) || empty($description)) {
            $error = 'Subject and description are required.';
        } elseif (!in_array($category, $validCategories)) {
            $error = 'Invalid category selected.';
        } elseif (!in_array($priority, $validPriorities)) {
            $error = 'Invalid priority selected.';
        } else {
            try {
                $ticketId = (int) $db->execute(
                    "INSERT INTO tickets (customer_id, subject, description, category, priority, status)
                     VALUES (?, ?, ?, ?, ?, 'open')",
                    [$customerId, $subject, $description, $category, $priority]
                );

                // Handle file attachment (optional)
                if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../uploads/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

                    $originalName = basename($_FILES['attachment']['name']);
                    $safeName     = $ticketId . '_' . time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                    $targetPath   = $uploadDir . $safeName;
                    $mimeType     = mime_content_type($_FILES['attachment']['tmp_name']);
                    $fileSize     = $_FILES['attachment']['size'];

                    if ($fileSize <= UPLOAD_MAX_SIZE && in_array($mimeType, ALLOWED_TYPES)) {
                        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                            $db->execute(
                                "INSERT INTO ticket_attachments
                                 (ticket_id, filename, original_name, file_size, mime_type, uploaded_by_type, uploaded_by_id)
                                 VALUES (?, ?, ?, ?, ?, 'customer', ?)",
                                [$ticketId, $safeName, $originalName, $fileSize, $mimeType, $customerId]
                            );
                        }
                    }
                }

                // Notify admin
                $adminBody = "<p>A new support ticket has been submitted.</p>"
                    . "<table style='border-collapse:collapse;width:100%;'>"
                    . "<tr><td style='padding:8px;font-weight:bold;'>Ticket #</td><td style='padding:8px;'>{$ticketId}</td></tr>"
                    . "<tr><td style='padding:8px;font-weight:bold;'>Customer</td><td style='padding:8px;'>" . htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) . "</td></tr>"
                    . "<tr><td style='padding:8px;font-weight:bold;'>Subject</td><td style='padding:8px;'>" . htmlspecialchars($subject) . "</td></tr>"
                    . "<tr><td style='padding:8px;font-weight:bold;'>Category</td><td style='padding:8px;'>" . ucfirst($category) . "</td></tr>"
                    . "<tr><td style='padding:8px;font-weight:bold;'>Priority</td><td style='padding:8px;'>" . ucfirst($priority) . "</td></tr>"
                    . "</table>"
                    . "<p><a href='" . SITE_URL . "/admin/tickets.php?id={$ticketId}' style='color:#00d4ff;'>View ticket in admin panel</a></p>";

                sendEmail(ADMIN_EMAIL, "New Ticket #{$ticketId}: " . htmlspecialchars($subject), $adminBody);

                if (isset($_POST['_ajax'])) {
                    jsonSuccess(['ticket_id' => $ticketId], "Ticket #{$ticketId} submitted successfully.");
                }

                $success = true;
            } catch (Exception $e) {
                $error = 'Failed to submit ticket. Please try again.';
                error_log('[Ticket Submit] ' . $e->getMessage());
                if (isset($_POST['_ajax'])) {
                    jsonError('Failed to submit ticket.');
                }
            }
        }
    }
}

$csrfToken = generateCsrfToken();
$initials  = strtoupper(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Submit Ticket — NexaTech Portal</title>
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
      <a href="submit-ticket.php" class="active"><span class="nav-icon">➕</span> Submit Ticket</a>
      <a href="dashboard.php#tickets"><span class="nav-icon">🎫</span> My Tickets</a>
    </nav>
    <div class="sidebar-footer">
      <a href="logout.php"><span>🚪</span> Log Out</a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">
    <div class="topbar">
      <h1>Submit Support Ticket</h1>
      <div class="topbar-actions">
        <a href="dashboard.php" class="btn btn-ghost btn-sm">← Back to Dashboard</a>
      </div>
    </div>

    <div class="content-area">

      <?php if ($success): ?>
        <div class="alert alert-success" style="font-size:1rem;padding:20px;">
          ✅ Ticket #<?= $ticketId ?> submitted successfully! We'll respond within 2 business hours.
          <br /><br />
          <a href="ticket-detail.php?id=<?= $ticketId ?>" class="btn btn-primary btn-sm">View Ticket</a>
          &nbsp;
          <a href="dashboard.php" class="btn btn-ghost btn-sm">Go to Dashboard</a>
        </div>
      <?php else: ?>

        <?php if ($error): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div id="ticket-form-msg"></div>

        <div class="card">
          <div class="card-header">
            <h3>🎫 New Support Ticket</h3>
          </div>
          <div class="card-body">
            <form id="ticket-form" method="POST" action="submit-ticket.php" enctype="multipart/form-data">
              <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>" />
              <input type="hidden" name="_ajax" value="1" />

              <div class="form-group">
                <label class="form-label" for="subject">Subject *</label>
                <input type="text" id="subject" name="subject" class="form-control"
                       placeholder="Brief description of the issue (e.g. 'Cannot connect to VPN from home')"
                       required maxlength="255" />
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label class="form-label" for="category">Category *</label>
                  <select id="category" name="category" class="form-control" required>
                    <option value="">-- Select category --</option>
                    <option value="network">Network / Connectivity</option>
                    <option value="cloud">Microsoft 365 / Cloud</option>
                    <option value="security">Security / Access</option>
                    <option value="hardware">Hardware / Equipment</option>
                    <option value="software">Software / Applications</option>
                    <option value="backup">Backup / Data Recovery</option>
                    <option value="remote">Remote Work / VPN</option>
                    <option value="consulting">IT Strategy / Consulting</option>
                    <option value="other">Other</option>
                  </select>
                </div>

                <div class="form-group">
                  <label class="form-label" for="priority">Priority *</label>
                  <select id="priority" name="priority" class="form-control" required>
                    <option value="low">Low — Minor inconvenience, can wait</option>
                    <option value="medium" selected>Medium — Affecting productivity</option>
                    <option value="high">High — Significant disruption</option>
                    <option value="critical">Critical — Business completely stopped</option>
                  </select>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label" for="description">Description *</label>
                <textarea id="description" name="description" class="form-control" rows="7" required
                          placeholder="Please describe the issue in detail. Include:&#10;• What happened and when&#10;• Steps you've already tried&#10;• How many people are affected&#10;• Any error messages you see"></textarea>
              </div>

              <div class="form-group">
                <label class="form-label" for="attachment">Attachment (optional)</label>
                <input type="file" id="attachment" name="attachment" class="form-control"
                       style="padding:10px;"
                       accept=".jpg,.jpeg,.png,.gif,.pdf,.txt,.doc,.docx" />
                <small style="margin-top:6px;display:block;color:var(--text-secondary);">
                  Max 10 MB. Accepted: JPG, PNG, GIF, PDF, TXT, DOC, DOCX
                </small>
              </div>

              <div style="display:flex;gap:12px;margin-top:8px;">
                <button type="submit" class="btn btn-primary" style="padding:13px 32px;">
                  Submit Ticket
                </button>
                <a href="dashboard.php" class="btn btn-ghost">Cancel</a>
              </div>
            </form>
          </div>
        </div>

        <!-- Priority guide -->
        <div class="card">
          <div class="card-header"><h3>📋 Priority Guide</h3></div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
              <div style="background:var(--bg-secondary);border-radius:8px;padding:16px;border:1px solid var(--border);">
                <span class="badge badge-low" style="margin-bottom:8px;display:inline-flex;">Low</span>
                <p style="font-size:.82rem;">Minor issues, cosmetic problems, general questions</p>
              </div>
              <div style="background:var(--bg-secondary);border-radius:8px;padding:16px;border:1px solid var(--border);">
                <span class="badge badge-medium" style="margin-bottom:8px;display:inline-flex;">Medium</span>
                <p style="font-size:.82rem;">Affecting productivity but workaround exists</p>
              </div>
              <div style="background:var(--bg-secondary);border-radius:8px;padding:16px;border:1px solid var(--border);">
                <span class="badge badge-high" style="margin-bottom:8px;display:inline-flex;">High</span>
                <p style="font-size:.82rem;">No workaround, significant impact on operations</p>
              </div>
              <div style="background:var(--bg-secondary);border-radius:8px;padding:16px;border:1px solid rgba(255,77,77,.3);">
                <span class="badge badge-critical" style="margin-bottom:8px;display:inline-flex;">Critical</span>
                <p style="font-size:.82rem;">Complete outage — business cannot operate</p>
              </div>
            </div>
          </div>
        </div>

      <?php endif; ?>

    </div>
  </main>
</div>
<script src="../assets/js/main.js?v=3"></script>
</body>
</html>
