<?php
// search.php — Search Transactions & Users
// VULNERABILITY 1: Reflected XSS — ?q= echoed directly without escaping
// VULNERABILITY 2: SQLi — search term used in LIKE query without sanitization
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

// ── SEARCH ────────────────────────────────────────────────────
$q           = $_GET['q'] ?? '';   // raw — reflected XSS & SQLi sink
$results_tx  = [];
$results_usr = [];

if ($q !== '') {
    // Transaction search — SQLi via LIKE
    $tx_sql = "SELECT t.*, u_from.full_name AS sender, u_to.full_name AS recipient,
                       af.account_number AS from_acc, at2.account_number AS to_acc
                 FROM transactions t
                 JOIN accounts af  ON t.from_account_id = af.id
                 JOIN accounts at2 ON t.to_account_id   = at2.id
                 JOIN users u_from ON af.user_id  = u_from.id
                 JOIN users u_to   ON at2.user_id = u_to.id
                WHERE t.memo LIKE '%$q%'
                   OR u_from.full_name LIKE '%$q%'
                   OR u_to.full_name LIKE '%$q%'
                ORDER BY t.transaction_date DESC";
    $res_tx = mysqli_query($conn, $tx_sql);
    if ($res_tx) $results_tx = mysqli_fetch_all($res_tx, MYSQLI_ASSOC);

    // User search — no auth restriction
    $usr_sql = "SELECT id, username, full_name, email FROM users WHERE full_name LIKE '%$q%' OR username LIKE '%$q%'";
    $res_usr = mysqli_query($conn, $usr_sql);
    if ($res_usr) $results_usr = mysqli_fetch_all($res_usr, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Search</title>
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
    <a href="search.php"     class="nav-link active"><div class="nav-icon">🔍</div> Search</a>
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
        <div class="topbar-title">🔍 Search</div>
        <div class="topbar-subtitle">Find transactions, users, and more</div>
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

    <!-- SEARCH BAR -->
    <div class="card fade-in" style="margin-bottom:24px;">
      <form method="GET" action="search.php" style="display:flex;gap:12px;align-items:flex-end;">
        <div class="form-group" style="flex:1;margin-bottom:0;">
          <label class="form-label">Search BankLab</label>
          <input type="text" name="q" class="form-input"
                 placeholder="Search by name, memo, username..."
                 value="<?= $_GET['q'] ?? '' /* REFLECTED — no htmlspecialchars */ ?>"/>
        </div>
        <button type="submit" class="btn btn-primary" style="height:48px;padding:0 28px;">🔍 Search</button>
      </form>
      <?php if ($q !== ''): ?>
      <!-- REFLECTED XSS: $q echoed directly without escaping -->
      <p style="margin-top:12px;font-size:14px;color:var(--slate-mid);">
        Showing results for: <strong><?= $q ?></strong>
      </p>
      <?php endif; ?>
    </div>

    <?php if ($q !== ''): ?>
    <!-- Transactions results -->
    <div class="card fade-in" style="margin-bottom:24px;">
      <div class="card-title">
        <span class="card-icon">💸</span> Transactions
        <span class="badge badge-info" style="margin-left:auto;"><?= count($results_tx) ?> results</span>
      </div>
      <?php if (empty($results_tx)): ?>
      <div style="text-align:center;padding:30px;color:var(--slate-light);">No transactions found.</div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>From</th><th>To</th><th>Memo</th><th>Amount</th></tr></thead>
          <tbody>
          <?php foreach ($results_tx as $t): ?>
          <tr>
            <td><?= date('M j, Y', strtotime($t['transaction_date'])) ?></td>
            <td><?= htmlspecialchars($t['sender']) ?><br/><span style="font-size:11px;color:var(--slate-light);"><?= htmlspecialchars($t['from_acc']) ?></span></td>
            <td><?= htmlspecialchars($t['recipient']) ?><br/><span style="font-size:11px;color:var(--slate-light);"><?= htmlspecialchars($t['to_acc']) ?></span></td>
            <td><em><?= $t['memo'] /* raw — Stored XSS renders here too */ ?></em></td>
            <td><span class="tx-amount"><?= number_format($t['amount'],2) ?></span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- User results -->
    <div class="card fade-in">
      <div class="card-title">
        <span class="card-icon">👤</span> Users
        <span class="badge badge-info" style="margin-left:auto;"><?= count($results_usr) ?> results</span>
      </div>
      <?php if (empty($results_usr)): ?>
      <div style="text-align:center;padding:30px;color:var(--slate-light);">No users found.</div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:2px;">
      <?php foreach ($results_usr as $u): ?>
      <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1.5px solid var(--border);">
        <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#4F8EF7,#A8D8F8);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;">
          <?= strtoupper(substr($u['full_name'],0,1)) ?>
        </div>
        <div>
          <div style="font-weight:700;color:var(--slate);"><?= htmlspecialchars($u['full_name']) ?></div>
          <div style="font-size:12px;color:var(--slate-light);">@<?= htmlspecialchars($u['username']) ?> &middot; <?= htmlspecialchars($u['email']) ?></div>
        </div>
        <a href="transactions.php?account_id=<?= $u['id'] + 100 ?>" class="btn btn-outline btn-sm" style="margin-left:auto;">View Transactions</a>
      </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <!-- Empty state -->
    <div style="text-align:center;padding:80px 40px;color:var(--slate-light);">
      <div style="font-size:64px;margin-bottom:16px;">🔍</div>
      <div style="font-size:20px;font-weight:700;color:var(--slate);margin-bottom:8px;">Search BankLab</div>
      <div style="font-size:14px;">Enter a name, memo, or keyword above to find transactions and users.</div>
    </div>
    <?php endif; ?>
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
