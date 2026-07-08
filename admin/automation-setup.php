<?php
/**
 * NexaTech Solutions — New Client Setup Checklist (Automated Actions planning)
 *
 * Intake checklist for a new client's initial M365/OneDrive/website setup.
 * Not yet wired to any backend automation — this is the data-gathering
 * step that a future automation phase would consume.
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
  <title>New Client Setup — NexaTech Admin</title>
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
      <a href="staff.php"><span class="nav-icon">🔑</span> Staff</a>
      <a href="automation-setup.php" class="active"><span class="nav-icon">🤖</span> New Client Setup</a>
      <a href="help.php"><span class="nav-icon">📖</span> Manual</a>
    </nav>
    <div class="sidebar-footer"><a href="logout.php"><span>🚪</span> Log Out</a></div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <h1>New Client Setup Checklist</h1>
      <div class="topbar-actions">
        <button type="button" id="print-checklist" class="btn btn-ghost btn-sm">🖨️ Print / Save PDF</button>
      </div>
    </div>

    <div class="content-area" style="max-width:1000px;">

      <div class="alert alert-info">
        <strong>Planning tool, not yet automated.</strong> This gathers everything needed for a new client's Microsoft 365 / OneDrive / website setup during an onboarding call. Filling it out doesn't create any accounts yet — that requires the automation backend (Graph API + a decided CSP/GDAP access model) to be built first.
      </div>

      <form id="setup-checklist">

        <div class="card">
          <div class="card-header"><h3>🏢 Company &amp; Domain</h3></div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div class="form-group">
                <label class="form-label">Company legal name</label>
                <input type="text" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Preferred trading / display name</label>
                <input type="text" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Primary contact name</label>
                <input type="text" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Primary contact email / phone</label>
                <input type="text" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Existing domain name (if any)</label>
                <input type="text" class="form-control" placeholder="e.g. companyname.com" />
              </div>
              <div class="form-group">
                <label class="form-label">If no domain yet, desired name</label>
                <input type="text" class="form-control" />
              </div>
            </div>
            <label style="display:flex;gap:8px;align-items:center;margin-top:8px;">
              <input type="checkbox" /> DNS / domain registrar access confirmed (needed to verify domain in M365 and route mail)
            </label>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3>🔑 Microsoft 365 Tenant &amp; Licensing</h3></div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Does the company already have a Microsoft 365 tenant?</label>
              <select class="form-control">
                <option value="">— Select —</option>
                <option>Yes — existing tenant, will grant delegated admin access (GDAP)</option>
                <option>No — new tenant needs to be created / purchased</option>
              </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div class="form-group">
                <label class="form-label">Existing tenant admin contact (if applicable)</label>
                <input type="text" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Who owns billing for licenses?</label>
                <select class="form-control">
                  <option value="">— Select —</option>
                  <option>Client bills directly with Microsoft</option>
                  <option>Via NexaTech as CSP reseller</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Default license tier</label>
                <select class="form-control">
                  <option value="">— Select —</option>
                  <option>Business Basic (~$6/user/mo — web/mobile Outlook only)</option>
                  <option>Business Standard (~$12.50/user/mo — desktop Outlook included)</option>
                  <option>Business Premium (~$22/user/mo — adds advanced security)</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"># of staff needing accounts</label>
                <input type="number" class="form-control" min="0" />
              </div>
            </div>
            <label style="display:flex;gap:8px;align-items:center;margin-top:8px;">
              <input type="checkbox" /> Client understands license costs are recurring, per user, per month
            </label>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3>👥 Staff to Create</h3></div>
          <div class="card-body no-pad">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Full Name</th>
                    <th>Job Title / Dept</th>
                    <th>Preferred Email / Username</th>
                    <th>License Tier</th>
                    <th>Start Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php for ($i = 0; $i < 8; $i++): ?>
                    <tr>
                      <td><input type="text" class="form-control" /></td>
                      <td><input type="text" class="form-control" /></td>
                      <td><input type="text" class="form-control" /></td>
                      <td><input type="text" class="form-control" /></td>
                      <td><input type="date" class="form-control" /></td>
                    </tr>
                  <?php endfor; ?>
                </tbody>
              </table>
            </div>
            <p style="padding:12px 16px;" class="td-secondary">List additional staff on a continuation sheet if there are more than 8.</p>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3>📁 OneDrive / SharePoint Folder Structure</h3></div>
          <div class="card-body">
            <p style="margin-bottom:10px;">Suggested default folders — check the ones to create:</p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
              <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" checked /> 01 - Admin &amp; Finance</label>
              <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" checked /> 02 - HR</label>
              <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" checked /> 03 - Projects</label>
              <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" checked /> 04 - Marketing</label>
              <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" checked /> 05 - Templates &amp; Branding</label>
              <label style="display:flex;gap:8px;align-items:center;"><input type="checkbox" checked /> 06 - Shared / Company-Wide</label>
            </div>
            <div class="form-group" style="margin-top:12px;">
              <label class="form-label">Additional folders requested</label>
              <textarea class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Permission model</label>
              <select class="form-control">
                <option value="">— Select —</option>
                <option>Company-wide shared access</option>
                <option>Department-restricted access</option>
              </select>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3>🌐 Default Company Website</h3></div>
          <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div class="form-group">
                <label class="form-label">Site name / tagline</label>
                <input type="text" class="form-control" />
              </div>
              <div class="form-group">
                <label class="form-label">Brand color (if any)</label>
                <input type="text" class="form-control" placeholder="e.g. #00d4ff" />
              </div>
              <div class="form-group">
                <label class="form-label">Logo provided?</label>
                <select class="form-control">
                  <option value="">— Select —</option>
                  <option>Yes, attached</option>
                  <option>No, needs one designed</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Hosting preference</label>
                <select class="form-control">
                  <option value="">— Select —</option>
                  <option>New hosting/subdomain via NexaTech</option>
                  <option>Client's own existing hosting</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Public contact info for site (phone / email / address)</label>
              <textarea class="form-control" rows="2"></textarea>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3>🔒 Security &amp; Authorization Sign-off</h3></div>
          <div class="card-body">
            <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
              <input type="checkbox" /> Client has formally authorized NexaTech to create accounts and data on their behalf
            </label>
            <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
              <input type="checkbox" /> Temporary passwords will be delivered out-of-band (not plain email) and forced to change at first login
            </label>
            <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
              <input type="checkbox" /> MFA will be enabled for every account created
            </label>
            <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
              <input type="checkbox" /> Payment method on file for ongoing license billing
            </label>
          </div>
        </div>

      </form>

    </div>
  </main>
</div>
<script src="../assets/js/main.js?v=6"></script>
</body>
</html>
