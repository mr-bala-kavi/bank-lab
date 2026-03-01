<?php
// notifications.php
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

$notifications = [
  ['icon'=>'💸','title'=>'Transfer received',          'body'=>'You received $500.00 from Carol White.',              'time'=>'2 min ago',  'read'=>false,'color'=>'var(--mint-soft)'],
  ['icon'=>'🔔','title'=>'New login detected',         'body'=>'New sign-in from Windows / Chrome on Mar 1.',         'time'=>'14 min ago', 'read'=>false,'color'=>'var(--primary-soft)'],
  ['icon'=>'💳','title'=>'Card payment successful',    'body'=>'Visa ending 4821 used at Food Court: $42.50.',        'time'=>'1 hr ago',   'read'=>true, 'color'=>'var(--gold-soft)'],
  ['icon'=>'📊','title'=>'Monthly statement ready',    'body'=>'Your February 2026 statement is now available.',      'time'=>'3 hr ago',   'read'=>true, 'color'=>'var(--purple-soft)'],
  ['icon'=>'⚠️','title'=>'Low balance alert',          'body'=>'Your account balance is below $500. Top up now.',     'time'=>'Yesterday',  'read'=>true, 'color'=>'var(--accent-soft)'],
  ['icon'=>'🎉','title'=>'Cashback credited',          'body'=>'$12.50 cashback from your Mastercard has been added.','time'=>'2 days ago', 'read'=>true, 'color'=>'var(--mint-soft)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Notifications</title>
<link rel="stylesheet" href="assets/style.css"/>
</head>
<body>
<div class="page-wrap">
  <aside class="sidebar">
    <div class="logo"><div class="logo-icon">🏦</div><div class="logo-text">Bank<span>Lab</span></div></div>
    <p class="nav-label">Main Menu</p>
    <a href="dashboard.php?account_id=<?= $account_id ?>" class="nav-link"><div class="nav-icon">🏠</div> Dashboard</a>
    <a href="transfer.php" class="nav-link"><div class="nav-icon">💸</div> Transfer Money</a>
    <a href="transactions.php?account_id=<?= $account_id ?>" class="nav-link"><div class="nav-icon">📋</div> Transactions</a>
    <a href="cards.php" class="nav-link"><div class="nav-icon">💳</div> Cards</a>
    <a href="analytics.php" class="nav-link"><div class="nav-icon">📈</div> Analytics</a>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;"><div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">🔔 Notifications</div>
        <div class="topbar-subtitle">Stay up to date with your account activity</div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" style="position:relative;">🔔</div>
        <div class="avatar" style="background:linear-gradient(135deg,<?= htmlspecialchars($account['avatar_color']) ?>,#A8D8F8);">
          <?= $initials ?><div class="avatar-badge"></div>
        </div>
      </div>
    </div>

    <div class="card fade-in" style="max-width:720px;">
      <div class="card-title" style="margin-bottom:6px;">
        <span class="card-icon">🔔</span> All Notifications
        <span class="badge badge-danger" style="margin-left:auto;">2 unread</span>
        <button class="btn btn-outline btn-sm" style="margin-left:10px;" onclick="alert('All notifications marked as read.')">Mark all read</button>
      </div>
      <hr class="divider"/>
      <?php foreach ($notifications as $n): ?>
      <div style="display:flex;align-items:flex-start;gap:16px;padding:16px 0;border-bottom:1.5px solid var(--border);<?= !$n['read'] ? 'background:transparent;' : '' ?>">
        <div style="width:46px;height:46px;border-radius:13px;background:<?= $n['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
          <?= $n['icon'] ?>
        </div>
        <div style="flex:1;">
          <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-weight:<?= !$n['read'] ? '800' : '700' ?>;font-size:15px;color:var(--slate);"><?= htmlspecialchars($n['title']) ?></span>
            <?php if (!$n['read']): ?><span class="badge badge-info" style="font-size:10px;">New</span><?php endif; ?>
          </div>
          <div style="font-size:13px;color:var(--slate-mid);margin-top:3px;"><?= htmlspecialchars($n['body']) ?></div>
          <div style="font-size:12px;color:var(--slate-light);margin-top:4px;">⏰ <?= $n['time'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </main>
</div>
</body>
</html>
