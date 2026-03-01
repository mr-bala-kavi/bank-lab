<?php
// analytics.php — spending analytics page
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$account_id = $_SESSION['account_id'];
$acc_sql = "SELECT accounts.*, users.full_name, users.email, users.avatar_color
              FROM accounts JOIN users ON accounts.user_id = users.id
             WHERE accounts.id = $account_id";
$account = mysqli_fetch_assoc(mysqli_query($conn, $acc_sql));
$initials = strtoupper(substr($account['full_name'], 0, 1))
          . strtoupper(explode(' ', $account['full_name'])[1][0] ?? '');

// Fetch total sent / received
$sent_sql = "SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE from_account_id = $account_id";
$recv_sql = "SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE to_account_id   = $account_id";
$total_sent = mysqli_fetch_assoc(mysqli_query($conn, $sent_sql))['total'];
$total_recv = mysqli_fetch_assoc(mysqli_query($conn, $recv_sql))['total'];
$tx_count_sql = "SELECT COUNT(*) AS c FROM transactions WHERE from_account_id=$account_id OR to_account_id=$account_id";
$tx_count = mysqli_fetch_assoc(mysqli_query($conn, $tx_count_sql))['c'];

// Simulated category breakdown
$categories = [
  ['label'=>'Food & Dining',    'icon'=>'🍔', 'amount'=>420,  'pct'=>34, 'color'=>'#FF6B6B'],
  ['label'=>'Shopping',         'icon'=>'🛍️', 'amount'=>310,  'pct'=>25, 'color'=>'#F5A623'],
  ['label'=>'Transport',        'icon'=>'🚗', 'amount'=>190,  'pct'=>15, 'color'=>'#4F8EF7'],
  ['label'=>'Entertainment',    'icon'=>'🎬', 'amount'=>155,  'pct'=>12, 'color'=>'#9B8FFF'],
  ['label'=>'Utilities',        'icon'=>'💡', 'amount'=>100,  'pct'=>8,  'color'=>'#38D9A9'],
  ['label'=>'Others',           'icon'=>'📦', 'amount'=>65,   'pct'=>5,  'color'=>'#A0AEC0'],
];

$months = ['Aug','Sep','Oct','Nov','Dec','Jan'];
$income  = [3200, 3500, 4100, 3800, 3600, 3870];
$expense = [2100, 1850, 3200, 2600, 1400, 1240];
$maxVal  = max(array_merge($income, $expense));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Analytics</title>
<link rel="stylesheet" href="assets/style.css"/>
<style>
.chart-wrap { display:flex; align-items:flex-end; gap:10px; height:120px; }
.bar-group  { display:flex; align-items:flex-end; gap:4px; flex:1; }
.bar-g-income  { background:linear-gradient(180deg,#4F8EF7,#A8C8FF); border-radius:6px 6px 0 0; min-height:6px; flex:1; }
.bar-g-expense { background:linear-gradient(180deg,#FF6B6B,#FFAAAA); border-radius:6px 6px 0 0; min-height:6px; flex:1; }
.cat-row { display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1.5px solid var(--border); }
.cat-row:last-child { border-bottom:none; }
.cat-bar-wrap { flex:1; background:var(--border); border-radius:8px; height:8px; }
.cat-bar-fill { height:8px; border-radius:8px; }
.legend-dot { width:12px; height:12px; border-radius:50%; flex-shrink:0; }
.overview-cards { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:26px; }
</style>
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
    <a href="analytics.php" class="nav-link active"><div class="nav-icon">📈</div> Analytics</a>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;"><div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">📈 Analytics</div>
        <div class="topbar-subtitle">Your financial insights at a glance</div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="window.location='notifications.php'" title="Notifications">🔔<div class="notif-dot"></div></div>
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
            <a href="dashboard.php?account_id=<?= $account_id ?>" class="avatar-menu-item">🏠 Dashboard</a>
            <hr class="divider" style="margin:0;">
            <a href="logout.php" class="avatar-menu-item danger">🚪 Sign Out</a>
          </div>
        </div>
      </div>
    </div>

    <!-- OVERVIEW STAT CARDS -->
    <div class="overview-cards stagger">
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--primary-soft);">📥</div>
        <div><div class="stat-val">$<?= number_format($total_recv, 0) ?></div><div class="stat-lbl">Total received</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--accent-soft);">📤</div>
        <div><div class="stat-val">$<?= number_format($total_sent, 0) ?></div><div class="stat-lbl">Total sent</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--mint-soft);">🔄</div>
        <div><div class="stat-val"><?= $tx_count ?></div><div class="stat-lbl">Transactions</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--gold-soft);">📊</div>
        <div><div class="stat-val">$<?= number_format($account['balance'], 0) ?></div><div class="stat-lbl">Current balance</div></div>
      </div>
    </div>

    <div class="two-col">
      <!-- Income vs Expense Chart -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">📊</span> Income vs Expense (6 months)</div>
        <div style="display:flex;gap:16px;margin-bottom:16px;">
          <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:var(--primary);">
            <span style="width:12px;height:12px;background:var(--primary);border-radius:3px;display:inline-block;"></span> Income
          </span>
          <span style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#FF6B6B;">
            <span style="width:12px;height:12px;background:#FF6B6B;border-radius:3px;display:inline-block;"></span> Expense
          </span>
        </div>
        <div class="chart-wrap">
          <?php foreach ($months as $i => $m): ?>
          <div style="display:flex;flex-direction:column;align-items:center;gap:6px;flex:1;">
            <div class="bar-group">
              <div class="bar-g-income"  style="height:<?= round($income[$i]/ $maxVal * 120) ?>px;" title="Income $<?= number_format($income[$i]) ?>"></div>
              <div class="bar-g-expense" style="height:<?= round($expense[$i]/$maxVal * 120) ?>px;" title="Expense $<?= number_format($expense[$i]) ?>"></div>
            </div>
            <div class="bar-label"><?= $m ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Category Breakdown -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">🗂️</span> Spending by Category</div>
        <?php foreach ($categories as $cat): ?>
        <div class="cat-row">
          <span style="font-size:20px;"><?= $cat['icon'] ?></span>
          <div style="flex:1;">
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
              <span style="font-size:13px;font-weight:700;color:var(--slate);"><?= $cat['label'] ?></span>
              <span style="font-size:13px;font-weight:700;color:var(--slate-mid);">$<?= number_format($cat['amount']) ?> (<?= $cat['pct'] ?>%)</span>
            </div>
            <div class="cat-bar-wrap"><div class="cat-bar-fill" style="width:<?= $cat['pct'] ?>%;background:<?= $cat['color'] ?>;"></div></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Monthly Summary Table -->
    <div class="card fade-in" style="margin-top:24px;">
      <div class="card-title"><span class="card-icon">📅</span> Monthly Summary</div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Month</th><th>Income</th><th>Expense</th><th>Net</th><th>Savings Rate</th></tr>
          </thead>
          <tbody>
          <?php foreach ($months as $i => $m):
            $net  = $income[$i] - $expense[$i];
            $rate = round($net / $income[$i] * 100);
          ?>
          <tr>
            <td><strong><?= $m ?></strong></td>
            <td><span class="tx-amount credit">+$<?= number_format($income[$i]) ?></span></td>
            <td><span class="tx-amount debit">-$<?= number_format($expense[$i]) ?></span></td>
            <td><span class="tx-amount <?= $net>=0 ? 'credit' : 'debit' ?>"><?= $net>=0?'+':'' ?>$<?= number_format($net) ?></span></td>
            <td><span class="badge <?= $rate>=30?'badge-success':($rate>=0?'badge-warning':'badge-danger') ?>"><?= $rate ?>%</span></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
<script>
document.addEventListener('click', function(e) {
  const menu = document.getElementById('avatarMenu');
  if (!e.target.closest('.avatar') && !e.target.closest('.avatar-menu')) menu.classList.remove('show');
});
</script>
</body>
</html>
