<?php
// admin.php — Admin Control Panel
// VULNERABILITY: Broken Access Control — checks login (session) but NOT role
// Any authenticated user can access this page directly via /admin.php
// No admin flag, no role column check — just session_user_id must exist
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
// ^^^^ ONLY CHECK: logged in. No role check. Any user == admin access.

$account_id = $_SESSION['account_id'];

// ── Admin actions ──────────────────────────────────────────────
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['admin_action'] ?? '';

    if ($act === 'delete_user') {
        $uid = (int)$_POST['uid'];
        mysqli_query($conn, "DELETE FROM accounts WHERE user_id = $uid");
        mysqli_query($conn, "DELETE FROM users WHERE id = $uid");
        $success = "User #$uid deleted.";
    }
    if ($act === 'reset_balance') {
        $aid = (int)$_POST['aid'];
        $bal = (float)$_POST['balance'];
        mysqli_query($conn, "UPDATE accounts SET balance = $bal WHERE id = $aid");
        $success = "Balance reset to $$bal for account #$aid.";
    }
    if ($act === 'toggle_user') {
        $success = "User status updated.";
    }
}

// ── Fetch all data (mass info disclosure) ─────────────────────
$users    = mysqli_fetch_all(mysqli_query($conn,
    "SELECT users.*, accounts.id AS acc_id, accounts.account_number,
            accounts.balance, accounts.account_type
       FROM users LEFT JOIN accounts ON accounts.user_id = users.id
      ORDER BY users.id"), MYSQLI_ASSOC);
$tx_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM transactions"))['c'];
$total_bal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(balance) AS t FROM accounts"))['t'];
$user_count = count($users);

$acc_sql = "SELECT accounts.*, users.full_name, users.email, users.avatar_color
              FROM accounts JOIN users ON accounts.user_id = users.id
             WHERE accounts.id = $account_id";
$account = mysqli_fetch_assoc(mysqli_query($conn, $acc_sql));
$initials = strtoupper(substr($account['full_name'], 0, 1))
          . strtoupper(explode(' ', $account['full_name'])[1][0] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Admin Panel</title>
<link rel="stylesheet" href="assets/style.css"/>
<style>
.admin-badge {
  background: linear-gradient(135deg,#FF6B6B,#FF8E53);
  color: white; font-size: 11px; font-weight: 800;
  padding: 3px 10px; border-radius: 20px; letter-spacing: .5px;
}
</style>
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
    <a href="admin.php"      class="nav-link active"><div class="nav-icon">👑</div> Admin</a>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;"><div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div style="display:flex;align-items:center;gap:12px;">
        <div>
          <div class="topbar-title" style="display:flex;align-items:center;gap:10px;">
            👑 Admin Panel <span class="admin-badge">ADMIN</span>
          </div>
          <div class="topbar-subtitle">System administration and user management</div>
        </div>
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

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- STATS OVERVIEW -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:26px;" class="stagger">
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--primary-soft);">👥</div>
        <div><div class="stat-val"><?= $user_count ?></div><div class="stat-lbl">Total Users</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--mint-soft);">💸</div>
        <div><div class="stat-val"><?= $tx_count ?></div><div class="stat-lbl">Transactions</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--gold-soft);">💰</div>
        <div><div class="stat-val">$<?= number_format($total_bal,0) ?></div><div class="stat-lbl">Total Balance</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--accent-soft);">🖥️</div>
        <div><div class="stat-val">Online</div><div class="stat-lbl">System Status</div></div>
      </div>
    </div>

    <!-- USER MANAGEMENT TABLE (shows plaintext passwords) -->
    <div class="card fade-in">
      <div class="card-title"><span class="card-icon">👥</span> User Management
        <span class="badge badge-info" style="margin-left:auto;"><?= $user_count ?> accounts</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th><th>Username</th><th>Full Name</th><th>Email</th>
              <th>Password</th><th>Account #</th><th>Type</th><th>Balance</th><th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
            <td><?= htmlspecialchars($u['full_name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><code style="font-size:12px;background:#FFF5F5;padding:2px 8px;border-radius:6px;color:#C53030;"><?= htmlspecialchars($u['password']) ?></code></td>
            <td><?= htmlspecialchars($u['account_number'] ?? '—') ?></td>
            <td><?= htmlspecialchars($u['account_type'] ?? '—') ?></td>
            <td><strong>$<?= number_format($u['balance'] ?? 0, 2) ?></strong></td>
            <td>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user?');">
                <input type="hidden" name="admin_action" value="delete_user"/>
                <input type="hidden" name="uid" value="<?= $u['id'] ?>"/>
                <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- BALANCE RESET -->
    <div class="two-col" style="margin-top:24px;">
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">💰</span> Reset Account Balance</div>
        <form method="POST" action="admin.php">
          <input type="hidden" name="admin_action" value="reset_balance"/>
          <div class="form-group">
            <label class="form-label">Account ID</label>
            <input type="number" name="aid" class="form-input" placeholder="101, 102, 103..."/>
          </div>
          <div class="form-group">
            <label class="form-label">New Balance ($)</label>
            <input type="number" name="balance" step="0.01" class="form-input" placeholder="0.00"/>
          </div>
          <button type="submit" class="btn btn-primary btn-full">💾 Update Balance</button>
        </form>
      </div>
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">⚙️</span> System Info</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <?php $info = [
            'PHP Version'      => phpversion(),
            'Server OS'        => PHP_OS,
            'Server Software'  => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'Document Root'    => $_SERVER['DOCUMENT_ROOT'] ?? 'N/A',
            'MySQL Version'    => mysqli_get_server_info($conn),
            'Current User'     => get_current_user(),
          ]; foreach ($info as $k => $v): ?>
          <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1.5px solid var(--border);font-size:13px;">
            <span style="color:var(--slate-light);font-weight:600;"><?= $k ?></span>
            <code style="color:var(--slate);font-size:12px;"><?= htmlspecialchars($v) ?></code>
          </div>
          <?php endforeach; ?>
        </div>
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
