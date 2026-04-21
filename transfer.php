<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$sender_acc_id = $_SESSION['account_id'];
$sender_sql    = "SELECT * FROM accounts WHERE id = $sender_acc_id LIMIT 1";
$sender_res    = mysqli_query($conn, $sender_sql);
$sender        = mysqli_fetch_assoc($sender_res);

$all_acc_sql = "SELECT accounts.id, accounts.account_number, users.full_name
                  FROM accounts JOIN users ON accounts.user_id = users.id
                 WHERE accounts.id != $sender_acc_id";
$all_acc_res = mysqli_query($conn, $all_acc_sql);
$accounts    = mysqli_fetch_all($all_acc_res, MYSQLI_ASSOC);

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF token check — CSRF vulnerability (hidden)
    $to_id  = (int)($_POST['to_account'] ?? 0);
    $amount = (float)($_POST['amount']   ?? 0);
    $memo   = $_POST['memo'] ?? ''; // stored raw — XSS vulnerability (hidden)

    // BUSINESS LOGIC FLAW: no check that amount > 0 — negative amounts reverse money flow
    if ($to_id <= 0) {
        $error = 'Please select a valid recipient.';
    } elseif (abs($amount) > $sender['balance'] && $amount > 0) {
        $error = 'Insufficient balance for this transfer.';
    } else {
        mysqli_query($conn, "UPDATE accounts SET balance = balance - $amount WHERE id = $sender_acc_id");
        mysqli_query($conn, "UPDATE accounts SET balance = balance + $amount WHERE id = $to_id");
        mysqli_query($conn,
            "INSERT INTO transactions (from_account_id, to_account_id, amount, memo)
             VALUES ($sender_acc_id, $to_id, $amount, '$memo')"
        );
        $success = 'Transfer of $' . number_format($amount, 2) . ' completed successfully!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Transfer Money</title>
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
    <a href="dashboard.php?account_id=<?= $sender_acc_id ?>" class="nav-link">
      <div class="nav-icon">🏠</div> Dashboard
    </a>
    <a href="transfer.php" class="nav-link active">
      <div class="nav-icon">💸</div> Transfer Money
    </a>
    <a href="transactions.php?account_id=<?= $sender_acc_id ?>" class="nav-link">
      <div class="nav-icon">📋</div> Transactions
    </a>
    <a href="cards.php"      class="nav-link"><div class="nav-icon">💳</div> Cards</a>
    <a href="analytics.php"  class="nav-link"><div class="nav-icon">📈</div> Analytics</a>
    <a href="search.php"     class="nav-link"><div class="nav-icon">🔍</div> Search</a>
    <a href="statements.php" class="nav-link"><div class="nav-icon">📄</div> Statements</a>
    <?php if ($_SESSION['is_admin'] ?? false): ?><a href="admin.php" class="nav-link"><div class="nav-icon">👑</div> Admin</a><?php endif; ?>
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
        <div class="topbar-title">💸 Transfer Money</div>
        <div class="topbar-subtitle">Send funds to any BankLab account instantly</div>
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

    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="two-col">

      <!-- TRANSFER FORM -->
      <div class="card fade-in">
        <div class="card-title">
          <span class="card-icon">🏧</span> New Transfer
        </div>

        <!-- No CSRF token in this form -->
        <form method="POST" action="transfer.php">
          <div class="form-group">
            <label class="form-label">From Account</label>
            <input type="text" class="form-input"
                   value="<?= htmlspecialchars($sender['account_number']) ?> · $<?= number_format($sender['balance'], 2) ?>"
                   readonly style="background:#F0F6FF; opacity:.8;"/>
          </div>
          <div class="form-group">
            <label class="form-label">Recipient Account</label>
            <select name="to_account" class="form-input">
              <option value="">— Select recipient —</option>
              <?php foreach ($accounts as $a): ?>
              <option value="<?= $a['id'] ?>">
                <?= htmlspecialchars($a['full_name']) ?> (<?= htmlspecialchars($a['account_number']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Amount ($)</label>
            <input type="number" name="amount" class="form-input"
                   placeholder="0.00" step="0.01"/>
          </div>
          <div class="form-group">
            <label class="form-label">Transfer Memo</label>
            <input type="text" name="memo" class="form-input"
                   placeholder="e.g. Lunch split, Rent, Freelance payment"/>
          </div>
          <button type="submit" class="btn btn-mint btn-full" style="margin-top: 6px;">
            💸 &nbsp; Send Transfer
          </button>
        </form>
      </div>

      <!-- RIGHT COLUMN -->
      <div style="display:flex; flex-direction:column; gap:20px;">

        <!-- Sender balance -->
        <div class="balance-card fade-in">
          <div class="balance-label">💳 Available Balance</div>
          <div class="balance-amount">
            <span class="currency">$</span><?= number_format($sender['balance'], 2) ?>
          </div>
          <div class="balance-meta" style="margin-top:14px;">
            <div class="balance-chip"><?= htmlspecialchars($sender['account_type']) ?></div>
            <div class="balance-meta-item">🔢 <?= htmlspecialchars($sender['account_number']) ?></div>
          </div>
        </div>

        <!-- Transfer tips -->
        <div class="card fade-in">
          <div class="card-title"><span class="card-icon">ℹ️</span> Transfer Info</div>
          <div style="display:flex;flex-direction:column;gap:14px;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
              <div style="font-size:22px;">⚡</div>
              <div>
                <div style="font-weight:700;font-size:14px;color:var(--slate);">Instant Transfer</div>
                <div style="font-size:13px;color:var(--slate-light);">Funds are transferred immediately to the recipient's account.</div>
              </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
              <div style="font-size:22px;">🔒</div>
              <div>
                <div style="font-weight:700;font-size:14px;color:var(--slate);">Secure Transfers</div>
                <div style="font-size:13px;color:var(--slate-light);">All transfers are logged and visible in your transaction history.</div>
              </div>
            </div>
            <div style="display:flex;align-items:flex-start;gap:12px;">
              <div style="font-size:22px;">📋</div>
              <div>
                <div style="font-weight:700;font-size:14px;color:var(--slate);">Use Memo Field</div>
                <div style="font-size:13px;color:var(--slate-light);">Add a reference note to help you track your transfers later.</div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </main>
</div>
</body>
</html>
