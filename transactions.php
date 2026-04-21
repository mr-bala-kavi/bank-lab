<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// No authorization check on account_id — IDOR vulnerability (hidden)
$account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : $_SESSION['account_id'];

$acc_sql = "SELECT accounts.*, users.full_name, users.email, users.avatar_color
              FROM accounts JOIN users ON accounts.user_id = users.id
             WHERE accounts.id = $account_id";
$acc_res = mysqli_query($conn, $acc_sql);
$account = mysqli_fetch_assoc($acc_res);

if (!$account) {
    die("<p style='padding:40px;'>Account not found.</p>");
}

$tx_sql = "SELECT t.*,
                  u_from.full_name AS from_name,
                  u_to.full_name   AS to_name,
                  af.account_number AS from_acc_num,
                  at2.account_number AS to_acc_num
             FROM transactions t
             JOIN accounts af   ON t.from_account_id = af.id
             JOIN accounts at2  ON t.to_account_id   = at2.id
             JOIN users u_from  ON af.user_id  = u_from.id
             JOIN users u_to    ON at2.user_id = u_to.id
            WHERE t.from_account_id = $account_id
               OR t.to_account_id   = $account_id
            ORDER BY t.transaction_date DESC";
$tx_res = mysqli_query($conn, $tx_sql);
$transactions = mysqli_fetch_all($tx_res, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Transactions</title>
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
    <a href="dashboard.php?account_id=<?= $_SESSION['account_id'] ?>" class="nav-link">
      <div class="nav-icon">🏠</div> Dashboard
    </a>
    <a href="transfer.php" class="nav-link">
      <div class="nav-icon">💸</div> Transfer Money
    </a>
    <a href="transactions.php?account_id=<?= $account_id ?>" class="nav-link active">
      <div class="nav-icon">📋</div> Transactions
    </a>
    <a href="cards.php" class="nav-link"><div class="nav-icon">💳</div> Cards</a>
    <a href="analytics.php" class="nav-link"><div class="nav-icon">📈</div> Analytics</a>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;">
        <div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out
      </a>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">📋 Transaction History</div>
        <div class="topbar-subtitle">
          Account <?= htmlspecialchars($account['account_number']) ?>
          &nbsp;·&nbsp; <?= htmlspecialchars($account['full_name']) ?>
        </div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="window.location='notifications.php'" title="Notifications" style="cursor:pointer;">🔔<div class="notif-dot"></div></div>
        <div class="avatar"
             style="background: linear-gradient(135deg, <?= htmlspecialchars($_SESSION['avatar_color'] ?? '#4F8EF7') ?>, #A8D8F8);">
          <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
          <div class="avatar-badge"></div>
        </div>
      </div>
    </div>

    <div class="card fade-in">
      <div class="card-title">
        <span class="card-icon">🗒️</span> All Transactions
        <span style="margin-left:auto;font-size:13px;color:var(--slate-light);">
          <?= count($transactions) ?> records
        </span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>From</th>
              <th>To</th>
              <th>Memo</th>
              <th>Amount</th>
              <th>Type</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($transactions as $tx):
            $is_debit = ($tx['from_account_id'] == $account_id);
          ?>
          <tr>
            <td style="font-weight:700;color:var(--slate-light);">#<?= $tx['id'] ?></td>
            <td><?= date('M j, Y', strtotime($tx['transaction_date'])) ?></td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($tx['from_name']) ?></div>
              <div style="font-size:12px;color:var(--slate-light);"><?= htmlspecialchars($tx['from_acc_num']) ?></div>
            </td>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($tx['to_name']) ?></div>
              <div style="font-size:12px;color:var(--slate-light);"><?= htmlspecialchars($tx['to_acc_num']) ?></div>
            </td>
            <!-- Memo echoed raw — Stored XSS vulnerability (hidden) -->
            <td style="font-style:italic;color:var(--slate-mid);"><?= $tx['memo'] ?></td>
            <td>
              <span class="tx-amount <?= $is_debit ? 'debit' : 'credit' ?>">
                <?= $is_debit ? '-' : '+' ?>$<?= number_format($tx['amount'], 2) ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $is_debit ? 'badge-danger' : 'badge-success' ?>">
                <?= $is_debit ? 'Debit' : 'Credit' ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($transactions)): ?>
          <tr>
            <td colspan="7" style="text-align:center;padding:40px;color:var(--slate-light);">
              No transactions found.
            </td>
          </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
