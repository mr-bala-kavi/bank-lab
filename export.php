<?php
// export.php — Export Transactions to CSV / Report
// VULNERABILITY: Command Injection via report_name parameter
// exec() called with unsanitized user input — append & whoami or | dir etc.
// Windows: use & or | as separator. Linux: use ; or | or &&
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id    = $_SESSION['user_id'];
$account_id = $_SESSION['account_id'];
$acc_sql    = "SELECT accounts.*, users.full_name, users.email, users.avatar_color
                 FROM accounts JOIN users ON accounts.user_id = users.id
                WHERE accounts.id = $account_id";
$account    = mysqli_fetch_assoc(mysqli_query($conn, $acc_sql));
$initials   = strtoupper(substr($account['full_name'], 0, 1))
            . strtoupper(explode(' ', $account['full_name'])[1][0] ?? '');

$cmd_output = '';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch transactions from DB (real data)
    $res  = mysqli_query($conn,
        "SELECT t.transaction_date, u_from.full_name AS sender,
                u_to.full_name AS recipient, t.amount, t.memo
           FROM transactions t
           JOIN accounts af ON t.from_account_id = af.id
           JOIN accounts at2 ON t.to_account_id  = at2.id
           JOIN users u_from ON af.user_id = u_from.id
           JOIN users u_to   ON at2.user_id = u_to.id
          WHERE af.id = $account_id OR at2.id = $account_id
          ORDER BY transaction_date DESC"
    );
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);

    // ── VULNERABLE: report_name used directly in exec() ──────
    $report_name = $_POST['report_name'] ?? 'my_statement';
    $export_path = __DIR__ . '\\exports\\' . $report_name . '.csv';

    // Build CSV content
    $csv = "Date,Sender,Recipient,Amount,Memo\n";
    foreach ($rows as $r) {
        $csv .= implode(',', array_map(fn($v) => '"'.addslashes($v).'"', $r)) . "\n";
    }
    file_put_contents($export_path, $csv);

    // Command injection sink — uses report_name in a system command
    $cmd    = "echo Export complete: $report_name 2>&1";   // Windows-compatible
    $output = [];
    exec($cmd, $output);
    $cmd_output = implode("\n", $output);
    $success = "Report exported! File: exports/$report_name.csv";
}

$format = $_POST['format'] ?? 'csv';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Export Transactions</title>
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
        <div class="topbar-title">⬇️ Export Transactions</div>
        <div class="topbar-subtitle">Download a copy of your transaction history</div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="window.location='notifications.php'" style="cursor:pointer;">🔔<div class="notif-dot"></div></div>
        <div class="avatar" style="background:linear-gradient(135deg,<?= htmlspecialchars($account['avatar_color']) ?>,#A8D8F8);">
          <?= $initials ?><div class="avatar-badge"></div>
        </div>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="two-col">
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">📊</span> Export Options</div>
        <form method="POST" action="export.php">
          <div class="form-group">
            <label class="form-label">Report Name</label>
            <input type="text" name="report_name" class="form-input"
                   placeholder="e.g. march_2026_statement"
                   value="<?= htmlspecialchars($_POST['report_name'] ?? 'my_statement') ?>"/>
            <span style="font-size:11px;color:var(--slate-light);">Used as the filename for your exported report</span>
          </div>
          <div class="form-group">
            <label class="form-label">Format</label>
            <select name="format" class="form-input">
              <option value="csv">CSV (.csv)</option>
              <option value="json">JSON (.json)</option>
              <option value="pdf">PDF (.pdf)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date Range</label>
            <select class="form-input">
              <option>Last 30 days</option>
              <option>Last 3 months</option>
              <option>Last 6 months</option>
              <option>This year</option>
              <option>All time</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-full">📥 Generate &amp; Export</button>
        </form>
      </div>

      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">⚙️</span> Export Log</div>
        <?php if ($cmd_output): ?>
        <div style="background:#1A202C;border-radius:12px;padding:16px;font-family:monospace;font-size:13px;color:#68D391;overflow-x:auto;margin-bottom:16px;">
          <div style="color:#A0AEC0;margin-bottom:6px;font-size:11px;">SYSTEM OUTPUT</div>
          <?= nl2br(htmlspecialchars($cmd_output)) ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:40px 0;color:var(--slate-light);">
          <div style="font-size:40px;margin-bottom:8px;">📭</div>
          <div style="font-size:14px;">No exports yet. Generate a report to see the log.</div>
        </div>
        <?php endif; ?>

        <hr class="divider"/>
        <div class="card-title" style="margin-bottom:12px;"><span class="card-icon">📂</span> Recent Exports</div>
        <?php
          $exp_files = glob(__DIR__ . '/exports/*.csv') ?: [];
          foreach (array_slice(array_reverse($exp_files), 0, 5) as $ef):
            $fn = basename($ef);
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1.5px solid var(--border);">
          <span style="font-size:13px;color:var(--slate);">📄 <?= htmlspecialchars($fn) ?></span>
          <a href="exports/<?= urlencode($fn) ?>" class="btn btn-outline btn-sm">⬇️</a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>
</div>
</body>
</html>
