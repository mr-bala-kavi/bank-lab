<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// No authorization check on account_id — IDOR vulnerability (hidden)
$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : $_SESSION['account_id'];

$acc_sql = "SELECT accounts.*, users.full_name, users.email, users.username, users.avatar_color
              FROM accounts JOIN users ON accounts.user_id = users.id
             WHERE accounts.id = $account_id";
$acc_res = mysqli_query($conn, $acc_sql);
$account = mysqli_fetch_assoc($acc_res);

if (!$account) {
    die("<p style='padding:40px;font-family:sans-serif;'>Account not found.</p>");
}

$tx_sql = "SELECT t.*,
                  u_from.full_name AS from_name,
                  u_to.full_name   AS to_name
             FROM transactions t
             JOIN accounts a_from ON t.from_account_id = a_from.id
             JOIN accounts a_to   ON t.to_account_id   = a_to.id
             JOIN users u_from    ON a_from.user_id = u_from.id
             JOIN users u_to      ON a_to.user_id   = u_to.id
            WHERE t.from_account_id = $account_id
               OR t.to_account_id   = $account_id
            ORDER BY t.transaction_date DESC
            LIMIT 6";
$tx_res = mysqli_query($conn, $tx_sql);
$transactions = mysqli_fetch_all($tx_res, MYSQLI_ASSOC);

$initials = strtoupper(substr($account['full_name'], 0, 1))
          . strtoupper(explode(' ', $account['full_name'])[1][0] ?? '');

$months = ['Aug','Sep','Oct','Nov','Dec','Jan'];
$spends = [2100, 1850, 3200, 2600, 1400, 2900];
$maxS   = max($spends);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Dashboard</title>
<link rel="stylesheet" href="assets/style.css"/>
</head>
<body>
<div class="page-wrap">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">
      <div class="logo-icon">🏦</div>
      <div class="logo-text">Bank<span>Lab</span></div>
    </div>

    <p class="nav-label">Main Menu</p>
    <a href="dashboard.php?account_id=<?= $_SESSION['account_id'] ?>" class="nav-link active">
      <div class="nav-icon">🏠</div> Dashboard
    </a>
    <a href="transfer.php" class="nav-link">
      <div class="nav-icon">💸</div> Transfer Money
    </a>
    <a href="transactions.php?account_id=<?= $account_id ?>" class="nav-link">
      <div class="nav-icon">📋</div> Transactions
    </a>
    <a href="cards.php" class="nav-link">
      <div class="nav-icon">💳</div> Cards
    </a>
    <a href="analytics.php" class="nav-link">
      <div class="nav-icon">📈</div> Analytics
    </a>
    <a href="search.php"     class="nav-link"><div class="nav-icon">🔍</div> Search</a>
    <a href="statements.php" class="nav-link"><div class="nav-icon">📄</div> Statements</a>
    <a href="webhook.php"    class="nav-link"><div class="nav-icon">🔗</div> Webhooks</a>
    <?php if ($_SESSION['is_admin'] ?? false): ?><a href="admin.php" class="nav-link"><div class="nav-icon">👑</div> Admin</a><?php endif; ?>

    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;">
        <div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN CONTENT -->
  <main class="main-content">

    <!-- TOPBAR -->
    <div class="topbar">
      <div>
        <div class="topbar-title">
          Good afternoon, <?= htmlspecialchars(explode(' ', $account['full_name'])[0]) ?>! 👋
        </div>
        <div class="topbar-subtitle">
          <?= date('l, F j, Y') ?> &nbsp;·&nbsp; Account <?= htmlspecialchars($account['account_number']) ?>
        </div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="window.location='notifications.php'" title="Notifications" style="cursor:pointer;">
          🔔<div class="notif-dot"></div>
        </div>
        <div style="position:relative;">
          <div class="avatar"
               style="background: linear-gradient(135deg, <?= htmlspecialchars($account['avatar_color']) ?>, #A8D8F8);"
               onclick="document.getElementById('avatarMenu').classList.toggle('show')">
            <?= $initials ?>
            <div class="avatar-badge"></div>
          </div>
          <div class="avatar-menu" id="avatarMenu">
            <div class="avatar-menu-header">
              <div class="avatar-menu-name"><?= htmlspecialchars($account['full_name']) ?></div>
              <div class="avatar-menu-email"><?= htmlspecialchars($account['email']) ?></div>
            </div>
            <a href="profile.php" class="avatar-menu-item">👤 Edit Profile</a>
            <a href="dashboard.php?account_id=<?= $_SESSION['account_id'] ?>" class="avatar-menu-item">🏠 My Dashboard</a>
            <a href="transfer.php" class="avatar-menu-item">💸 Transfer Money</a>
            <hr class="divider" style="margin:0;">
            <a href="logout.php" class="avatar-menu-item danger">🚪 Sign Out</a>
          </div>
        </div>
      </div>
    </div>

    <!-- BALANCE CARD -->
    <div class="balance-card fade-in">
      <div class="balance-label">💰 Total Balance</div>
      <div class="balance-amount">
        <span class="currency">$</span><?= number_format($account['balance'], 2) ?>
      </div>
      <div class="balance-meta">
        <div class="balance-chip"><?= htmlspecialchars($account['account_type']) ?></div>
        <div class="balance-meta-item">
          <span>🔢</span> <?= htmlspecialchars($account['account_number']) ?>
        </div>
        <div class="balance-change">📈 +3.2% this month</div>
      </div>
    </div>

    <!-- STATS ROW -->
    <div class="stats-grid stagger" style="margin-top:26px;">
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--mint-soft);">💸</div>
        <div>
          <div class="stat-val">$1,240</div>
          <div class="stat-lbl">Spent this month</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--primary-soft);">📥</div>
        <div>
          <div class="stat-val">$3,870</div>
          <div class="stat-lbl">Income this month</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrap" style="background:var(--gold-soft);">🎯</div>
        <div>
          <div class="stat-val">74%</div>
          <div class="stat-lbl">Savings goal</div>
        </div>
      </div>
    </div>

    <!-- QUICK ACTIONS -->
    <div class="quick-actions stagger" style="margin-top:24px;">
      <a href="transfer.php" class="quick-action-btn">
        <div class="quick-action-icon" style="background:var(--primary-soft);color:var(--primary);">💸</div> Send Money
      </a>
      <a href="transactions.php?account_id=<?= $account_id ?>" class="quick-action-btn">
        <div class="quick-action-icon" style="background:var(--purple-soft);color:var(--purple);">📋</div> History
      </a>
      <a href="cards.php" class="quick-action-btn">
        <div class="quick-action-icon" style="background:var(--mint-soft);color:var(--mint);">💳</div> My Cards
      </a>
      <a href="analytics.php" class="quick-action-btn">
        <div class="quick-action-icon" style="background:var(--gold-soft);color:#C09000;">📊</div> Analytics
      </a>
      <a href="search.php" class="quick-action-btn">
        <div class="quick-action-icon" style="background:var(--accent-soft);color:var(--accent);">🔍</div> Search
      </a>
      <a href="export.php" class="quick-action-btn">
        <div class="quick-action-icon" style="background:var(--primary-soft);color:var(--primary);">⬇️</div> Export
      </a>
    </div>

    <!-- TWO COLUMNS -->
    <div class="two-col">

      <!-- RECENT TRANSACTIONS -->
      <div class="card fade-in">
        <div class="card-title">
          <span class="card-icon">🕒</span> Recent Transactions
          <a href="transactions.php?account_id=<?= $account_id ?>"
             style="margin-left:auto;font-size:13px;color:var(--primary);font-weight:700;text-decoration:none;">
             View all →
          </a>
        </div>
        <ul class="tx-list">
          <?php foreach ($transactions as $tx):
            $is_debit = ($tx['from_account_id'] == $account_id);
            $party    = $is_debit ? $tx['to_name'] : $tx['from_name'];
            $icon_bg  = $is_debit ? 'var(--accent-soft)' : 'var(--mint-soft)';
            $icon     = $is_debit ? '🔴' : '🟢';
          ?>
          <li class="tx-item">
            <div class="tx-icon" style="background:<?= $icon_bg ?>;"><?= $icon ?></div>
            <div class="tx-info">
              <div class="tx-party"><?= htmlspecialchars($party) ?></div>
              <div class="tx-memo"><?= $tx['memo'] ?></div>
              <div class="tx-date"><?= date('M j, g:i A', strtotime($tx['transaction_date'])) ?></div>
            </div>
            <div class="tx-amount <?= $is_debit ? 'debit' : 'credit' ?>">
              <?= $is_debit ? '-' : '+' ?>$<?= number_format($tx['amount'], 2) ?>
            </div>
          </li>
          <?php endforeach; ?>
          <?php if (empty($transactions)): ?>
          <li style="padding:30px 0;text-align:center;color:var(--slate-light);font-size:14px;">
            No transactions yet.
          </li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- SPENDING CHART -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">📊</span> Monthly Spending</div>
        <div class="bar-chart" style="align-items:flex-end; margin-bottom: 16px;">
          <?php foreach ($months as $i => $m):
            $ht = round(($spends[$i] / $maxS) * 80);
          ?>
          <div class="bar-col">
            <div class="bar-fill <?= $m === 'Jan' ? 'mint' : '' ?>"
                 style="height:<?= $ht ?>px;" title="$<?= number_format($spends[$i]) ?>"></div>
            <div class="bar-label"><?= $m ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <hr class="divider"/>
        <div class="card-title" style="margin-bottom:12px;"><span class="card-icon">⚡</span> Quick Stats</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:14px;color:var(--slate-mid);">Highest spending month</span>
            <span class="badge badge-danger">Oct — $3,200</span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:14px;color:var(--slate-mid);">Lowest spending month</span>
            <span class="badge badge-success">Jan — $1,400</span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:14px;color:var(--slate-mid);">6-month average</span>
            <span class="badge badge-info">$2,358 / mo</span>
          </div>
        </div>
      </div>

    </div><!-- end two-col -->
  </main>
</div>

<script>
document.addEventListener('click', function(e) {
  const menu = document.getElementById('avatarMenu');
  if (!e.target.closest('.avatar') && !e.target.closest('.avatar-menu')) {
    menu.classList.remove('show');
  }
});
</script>
</body>
</html>
