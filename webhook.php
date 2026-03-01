<?php
// webhook.php — Webhook Notification Settings
// VULNERABILITY: SSRF — user-supplied URL is fetched server-side with no restriction
// Attack: submit http://localhost/bank-lab/admin.php or http://169.254.169.254/latest/meta-data/
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$account_id = $_SESSION['account_id'];
$acc_sql    = "SELECT accounts.*, users.full_name, users.email, users.avatar_color
                 FROM accounts JOIN users ON accounts.user_id = users.id
                WHERE accounts.id = $account_id";
$account    = mysqli_fetch_assoc(mysqli_query($conn, $acc_sql));
$initials   = strtoupper(substr($account['full_name'], 0, 1))
            . strtoupper(explode(' ', $account['full_name'])[1][0] ?? '');

$webhook_response = '';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── TEST WEBHOOK — SSRF SINK ──────────────────────────────
    if ($action === 'test') {
        $url = $_POST['webhook_url'] ?? '';  // raw user input — no validation, no allowlist

        if (empty($url)) {
            $error = 'Please enter a webhook URL.';
        } else {
            // Server fetches the URL directly (SSRF)
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,  true);
            curl_setopt($ch, CURLOPT_TIMEOUT,          5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION,   true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,   false);  // no TLS verification too
            curl_setopt($ch, CURLOPT_USERAGENT, 'BankLab-Webhook/1.0');
            $webhook_response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($webhook_response === false) {
                $error = 'Webhook test failed. Could not connect to the URL.';
            } else {
                $success = "Webhook tested successfully! Server responded with HTTP $http_code.";
            }
        }
    }

    if ($action === 'save') {
        $success = 'Webhook URL saved! You will be notified of all account activity.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Webhook Settings</title>
<link rel="stylesheet" href="assets/style.css"/>
</head>
<body>
<div class="page-wrap">
  <aside class="sidebar">
    <div class="logo"><div class="logo-icon">🏦</div><div class="logo-text">Bank<span>Lab</span></div></div>
    <p class="nav-label">Main Menu</p>
    <a href="dashboard.php?account_id=<?= $account_id ?>" class="nav-link"><div class="nav-icon">🏠</div> Dashboard</a>
    <a href="transfer.php"   class="nav-link"><div class="nav-icon">💸</div> Transfer Money</a>
    <a href="transactions.php?account_id=<?= $account_id ?>" class="nav-link"><div class="nav-icon">📋</div> Transactions</a>
    <a href="search.php"     class="nav-link"><div class="nav-icon">🔍</div> Search</a>
    <a href="statements.php" class="nav-link"><div class="nav-icon">📄</div> Statements</a>
    <a href="cards.php"      class="nav-link"><div class="nav-icon">💳</div> Cards</a>
    <a href="analytics.php"  class="nav-link"><div class="nav-icon">📈</div> Analytics</a>
    <?php if ($_SESSION['is_admin'] ?? false): ?><a href="admin.php" class="nav-link"><div class="nav-icon">👑</div> Admin</a><?php endif; ?>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;"><div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">🔗 Webhook Settings</div>
        <div class="topbar-subtitle">Get notified instantly when transactions occur</div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="window.location='notifications.php'" style="cursor:pointer;">🔔<div class="notif-dot"></div></div>
        <div class="avatar" style="background:linear-gradient(135deg,<?= htmlspecialchars($account['avatar_color']) ?>,#A8D8F8);">
          <?= $initials ?><div class="avatar-badge"></div>
        </div>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="two-col">
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">🔗</span> Configure Webhook</div>
        <p style="font-size:14px;color:var(--slate-mid);margin-bottom:20px;">
          BankLab will send a POST request to your webhook URL whenever a transaction occurs on your account.
        </p>
        <form method="POST" action="webhook.php">
          <input type="hidden" name="action" value="save"/>
          <div class="form-group">
            <label class="form-label">Webhook URL</label>
            <input type="url" name="webhook_url" id="webhookUrl" class="form-input"
                   placeholder="https://your-server.com/hook"
                   value="<?= htmlspecialchars($_POST['webhook_url'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label class="form-label">Trigger Events</label>
            <div style="display:flex;flex-direction:column;gap:10px;">
              <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;">
                <input type="checkbox" checked/> Money received
              </label>
              <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;">
                <input type="checkbox" checked/> Money sent
              </label>
              <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;">
                <input type="checkbox"/> Login detected
              </label>
              <label style="display:flex;align-items:center;gap:10px;font-size:14px;cursor:pointer;">
                <input type="checkbox"/> Card activity
              </label>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-full">💾 Save Webhook</button>
        </form>

        <hr class="divider"/>

        <!-- Test webhook — SSRF trigger -->
        <div class="card-title" style="margin-bottom:12px;"><span class="card-icon">🧪</span> Test Webhook</div>
        <p style="font-size:13px;color:var(--slate-mid);margin-bottom:14px;">
          Send a test request to verify your webhook endpoint is reachable.
        </p>
        <form method="POST" action="webhook.php">
          <input type="hidden" name="action" value="test"/>
          <div class="form-group">
            <input type="text" name="webhook_url" class="form-input"
                   placeholder="https://your-server.com/hook"
                   value="<?= htmlspecialchars($_POST['webhook_url'] ?? '') ?>"/>
          </div>
          <button type="submit" class="btn btn-mint btn-full">🚀 Send Test Request</button>
        </form>
      </div>

      <!-- Response viewer -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">📡</span> Server Response</div>
        <?php if ($webhook_response !== ''): ?>
        <div style="background:#1A202C;border-radius:12px;padding:16px;font-family:monospace;font-size:12px;color:#68D391;overflow:auto;max-height:400px;white-space:pre-wrap;word-break:break-all;">
          <div style="color:#A0AEC0;margin-bottom:6px;font-size:11px;">RESPONSE BODY</div>
          <?= htmlspecialchars(substr($webhook_response, 0, 4000)) ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:60px 20px;color:var(--slate-light);">
          <div style="font-size:48px;margin-bottom:12px;">📡</div>
          <div style="font-size:14px;">Run a test to see the server response here</div>
        </div>
        <?php endif; ?>

        <hr class="divider"/>
        <div class="card-title" style="margin-bottom:10px;"><span class="card-icon">📖</span> Payload Format</div>
        <div style="background:#F7FAFC;border-radius:12px;padding:14px;font-family:monospace;font-size:12px;color:#2D3748;">
{<br/>
&nbsp;&nbsp;"event": "transfer.received",<br/>
&nbsp;&nbsp;"account": "BA-0001-1001",<br/>
&nbsp;&nbsp;"amount": 500.00,<br/>
&nbsp;&nbsp;"from": "Bob Martinez",<br/>
&nbsp;&nbsp;"timestamp": "<?= date('c') ?>"<br/>
}
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
