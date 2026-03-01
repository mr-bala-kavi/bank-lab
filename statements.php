<?php
// statements.php — Download Bank Statements
// VULNERABILITY: Local File Inclusion / Path Traversal
// ?file= parameter passed directly to readfile() with no path sanitization
// Attack: ?file=../db.php  or  ?file=../../../windows/win.ini
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

// ── FILE DOWNLOAD ─────────────────────────────────────────────
// VULNERABLE: No path sanitization — allows directory traversal
if (isset($_GET['file']) && !empty($_GET['file'])) {
    $file = $_GET['file'];                              // raw user input
    $path = __DIR__ . '/statements/' . $file;          // base dir + user input (traversable)

    if (file_exists($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    } else {
        // fallback — try absolute path (even worse — no restriction at all)
        if (file_exists($file)) {
            header('Content-Type: text/plain');
            readfile($file);
            exit;
        }
        $dl_error = "File not found: $file";
    }
}

// Demo statement list
$statements = [
    ['month'=>'February 2026', 'file'=>'statement_feb_2026.pdf', 'size'=>'142 KB', 'icon'=>'📄'],
    ['month'=>'January 2026',  'file'=>'statement_jan_2026.pdf', 'size'=>'138 KB', 'icon'=>'📄'],
    ['month'=>'December 2025', 'file'=>'statement_dec_2025.pdf', 'size'=>'155 KB', 'icon'=>'📄'],
    ['month'=>'November 2025', 'file'=>'statement_nov_2025.pdf', 'size'=>'129 KB', 'icon'=>'📄'],
    ['month'=>'October 2025',  'file'=>'statement_oct_2025.pdf', 'size'=>'147 KB', 'icon'=>'📄'],
    ['month'=>'September 2025','file'=>'statement_sep_2025.pdf', 'size'=>'133 KB', 'icon'=>'📄'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Statements</title>
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
    <a href="statements.php" class="nav-link active"><div class="nav-icon">📄</div> Statements</a>
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
        <div class="topbar-title">📄 Statements</div>
        <div class="topbar-subtitle">Download your monthly account statements</div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="window.location='notifications.php'" style="cursor:pointer;">🔔<div class="notif-dot"></div></div>
        <div style="position:relative;">
          <div class="avatar" style="background:linear-gradient(135deg,<?= htmlspecialchars($account['avatar_color']) ?>,#A8D8F8);"
               onclick="document.getElementById('avatarMenu').classList.toggle('show')">
            <?= $initials ?><div class="avatar-badge"></div>
          </div>
          <div class="avatar-menu" id="avatarMenu">
            <div class="avatar-menu-header">
              <div class="avatar-menu-name"><?= htmlspecialchars($account['full_name']) ?></div>
              <div class="avatar-menu-email"><?= htmlspecialchars($account['email']) ?></div>
            </div>
            <a href="profile.php" class="avatar-menu-item">👤 Edit Profile</a>
            <hr class="divider" style="margin:0;">
            <a href="logout.php" class="avatar-menu-item danger">🚪 Sign Out</a>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($dl_error)): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($dl_error) ?></div>
    <?php endif; ?>

    <div class="two-col">
      <!-- Statement list -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">📋</span> Available Statements</div>
        <div style="display:flex;flex-direction:column;gap:2px;">
          <?php foreach ($statements as $s): ?>
          <div style="display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1.5px solid var(--border);">
            <div style="font-size:28px;"><?= $s['icon'] ?></div>
            <div style="flex:1;">
              <div style="font-weight:700;color:var(--slate);font-size:15px;"><?= $s['month'] ?></div>
              <div style="font-size:12px;color:var(--slate-light);"><?= $s['file'] ?> &middot; <?= $s['size'] ?></div>
            </div>
            <a href="statements.php?file=<?= urlencode($s['file']) ?>"
               class="btn btn-outline btn-sm">
              ⬇️ Download
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Custom file download -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">🗂️</span> Custom File Download</div>
        <p style="font-size:14px;color:var(--slate-mid);margin-bottom:16px;">
          Enter a filename to download a specific statement or document from our records.
        </p>
        <form method="GET" action="statements.php">
          <div class="form-group">
            <label class="form-label">Filename</label>
            <input type="text" name="file" class="form-input"
                   placeholder="e.g. statement_jan_2026.pdf"
                   value="<?= htmlspecialchars($_GET['file'] ?? '') ?>"/>
            <span style="font-size:11px;color:var(--slate-light);">
              Tip: Files are stored in the <code>statements/</code> directory.
            </span>
          </div>
          <button type="submit" class="btn btn-primary btn-full">⬇️ Download File</button>
        </form>
        <hr class="divider"/>
        <div class="card-title" style="margin-bottom:12px;"><span class="card-icon">📬</span> Email a Statement</div>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-input" value="<?= htmlspecialchars($account['email']) ?>"/>
        </div>
        <div class="form-group">
          <label class="form-label">Period</label>
          <select class="form-input">
            <option>February 2026</option>
            <option>January 2026</option>
            <option>Q4 2025</option>
          </select>
        </div>
        <button class="btn btn-mint btn-full" onclick="alert('Statement sent to ' + document.querySelectorAll('.form-input')[1].value)">
          📧 Send Statement
        </button>
      </div>
    </div>
  </main>
</div>
<script>
document.addEventListener('click', function(e) {
  const menu = document.getElementById('avatarMenu');
  if (menu && !e.target.closest('.avatar') && !e.target.closest('.avatar-menu')) menu.classList.remove('show');
});
</script>
</body>
</html>
