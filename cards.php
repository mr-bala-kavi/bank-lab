<?php
// cards.php — My Cards with real DB-backed actions
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id    = $_SESSION['user_id'];
$account_id = $_SESSION['account_id'];

// ── Handle POST actions ──────────────────────────────
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $card_id = (int)($_POST['card_id'] ?? 0);

    // Freeze / Unblock
    if (in_array($action, ['freeze','unblock']) && $card_id > 0) {
        $new_status = ($action === 'freeze') ? 'frozen' : 'active';
        mysqli_query($conn,
            "UPDATE cards SET status='$new_status'
              WHERE id=$card_id AND user_id=$user_id"
        );
        $success = $action === 'freeze'
            ? 'Card has been frozen. No transactions will be processed.'
            : 'Card has been unblocked and is now active.';
    }

    // Request new card
    if ($action === 'request_card') {
        $type = $_POST['card_type'] ?? 'Visa Debit';
        mysqli_query($conn,
            "INSERT INTO card_requests (user_id, card_type) VALUES ($user_id, '$type')"
        );
        $success = "Card request for \"$type\" submitted! Processing takes 3–5 business days. We'll email you at " . htmlspecialchars($_SESSION['email'] ?? '');
    }

    // Details — just flag to show the modal
    if ($action === 'details') {
        // handled client-side via JS modal below
    }
}

// ── Fetch account & user ─────────────────────────────
$acc_sql = "SELECT accounts.*, users.full_name, users.email, users.avatar_color
              FROM accounts JOIN users ON accounts.user_id = users.id
             WHERE accounts.id = $account_id";
$account  = mysqli_fetch_assoc(mysqli_query($conn, $acc_sql));
$initials = strtoupper(substr($account['full_name'], 0, 1))
          . strtoupper(explode(' ', $account['full_name'])[1][0] ?? '');

// ── Fetch cards for this user ─────────────────────────
$cards_res = mysqli_query($conn, "SELECT * FROM cards WHERE user_id = $user_id ORDER BY id");
$cards     = mysqli_fetch_all($cards_res, MYSQLI_ASSOC);

// ── Totals ────────────────────────────────────────────
$total_limit = array_sum(array_column(array_filter($cards, fn($c) => $c['credit_limit'] > 0), 'credit_limit'));
$total_spent  = array_sum(array_column(array_filter($cards, fn($c) => $c['credit_limit'] > 0), 'spent'));
$active_count = count(array_filter($cards, fn($c) => $c['status'] === 'active'));

// ── Pending requests ─────────────────────────────────
$req_count = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM card_requests WHERE user_id=$user_id AND status='pending'"
))['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — My Cards</title>
<link rel="stylesheet" href="assets/style.css"/>
<style>
/* ── Card Visual ── */
.card-visual {
  border-radius: 22px; padding: 28px 26px;
  color: #fff; position: relative; overflow: hidden;
  min-height: 175px; display: flex; flex-direction: column;
  justify-content: space-between;
  box-shadow: 0 12px 36px rgba(0,0,0,0.18);
  transition: transform .25s, box-shadow .25s;
}
.card-visual:hover { transform: translateY(-4px); box-shadow: 0 18px 48px rgba(0,0,0,0.22); }
.card-visual.frozen  { filter: saturate(0.3) brightness(0.8); }
.card-visual.blocked { filter: saturate(0) brightness(0.6); }
.card-visual::before {
  content:''; position:absolute; top:-40px; right:-40px;
  width:170px; height:170px; border-radius:50%;
  background:rgba(255,255,255,0.10);
}
.card-number { font-size:16px; font-weight:700; letter-spacing:3px; font-family:monospace; }
.card-row    { display:flex; justify-content:space-between; align-items:flex-end; }
.card-exp-label { font-size:10px; opacity:.75; }
.card-exp-val   { font-size:13px; font-weight:700; }
.progress-bar-wrap { background:rgba(0,0,0,0.15); border-radius:10px; height:7px; margin-top:7px; }
.progress-bar-fill { height:7px; border-radius:10px; background:rgba(255,255,255,0.65); }
.frozen-banner {
  position:absolute; inset:0; background:rgba(0,0,0,0.45);
  display:flex; align-items:center; justify-content:center;
  font-size:36px; border-radius:22px; letter-spacing:2px; font-weight:900;
}

/* ── Cards grid ── */
.cards-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:24px; }
.card-tile  { display:flex; flex-direction:column; gap:10px; }

/* ── Modal ── */
.modal-overlay {
  position:fixed; inset:0; background:rgba(30,42,59,0.55);
  display:none; align-items:center; justify-content:center; z-index:1000;
  backdrop-filter:blur(4px);
}
.modal-overlay.show { display:flex; animation:fadeIn .2s ease; }
.modal-box {
  background:#fff; border-radius:28px; padding:36px 40px;
  max-width:400px; width:90%; box-shadow:0 20px 60px rgba(0,0,0,0.2);
  position:relative;
}
.modal-close {
  position:absolute; top:16px; right:18px;
  background:none; border:none; font-size:22px;
  cursor:pointer; color:var(--slate-light);
}
.modal-close:hover { color:var(--slate); }
.detail-row {
  display:flex; justify-content:space-between; padding:10px 0;
  border-bottom:1.5px solid var(--border); font-size:14px;
}
.detail-row:last-child { border-bottom:none; }
.detail-label { color:var(--slate-light); font-weight:600; }
.detail-val   { color:var(--slate); font-weight:700; }
</style>
</head>
<body>
<div class="page-wrap">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="logo"><div class="logo-icon">🏦</div><div class="logo-text">Bank<span>Lab</span></div></div>
    <p class="nav-label">Main Menu</p>
    <a href="dashboard.php?account_id=<?= $account_id ?>" class="nav-link"><div class="nav-icon">🏠</div> Dashboard</a>
    <a href="transfer.php"   class="nav-link"><div class="nav-icon">💸</div> Transfer Money</a>
    <a href="transactions.php?account_id=<?= $account_id ?>" class="nav-link"><div class="nav-icon">📋</div> Transactions</a>
    <a href="cards.php"      class="nav-link active"><div class="nav-icon">💳</div> Cards</a>
    <a href="analytics.php"  class="nav-link"><div class="nav-icon">📈</div> Analytics</a>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;"><div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out</a>
    </div>
  </aside>

  <!-- CARD DETAILS MODAL -->
  <div class="modal-overlay" id="detailModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal()">✕</button>
      <div class="card-title" style="margin-bottom:20px;"><span class="card-icon">💳</span> <span id="modalTitle">Card Details</span></div>
      <div id="modalBody"></div>
    </div>
  </div>

  <main class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">💳 My Cards</div>
        <div class="topbar-subtitle">Manage your debit and credit cards</div>
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
            <a href="dashboard.php?account_id=<?= $account_id ?>" class="avatar-menu-item">🏠 Dashboard</a>
            <hr class="divider" style="margin:0;">
            <a href="logout.php" class="avatar-menu-item danger">🚪 Sign Out</a>
          </div>
        </div>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($req_count > 0): ?>
    <div class="alert alert-info">📮 You have <?= $req_count ?> pending card request(s).</div>
    <?php endif; ?>

    <!-- CARDS GRID -->
    <div class="cards-grid stagger">
      <?php foreach ($cards as $c):
        $frozen  = $c['status'] === 'frozen';
        $blocked = $c['status'] === 'blocked';
        $class   = $frozen ? 'frozen' : ($blocked ? 'blocked' : '');
        $last4   = htmlspecialchars($c['last4']);
        $label   = htmlspecialchars($c['label']);
        $exp     = htmlspecialchars($c['expiry']);
        $ctype   = htmlspecialchars($c['card_type']);
        $cbrand  = htmlspecialchars($c['card_brand']);
        $fullnum = "**** **** **** $last4";
        $statusLabel = ucfirst($c['status']);
        $statusBadge = $c['status'] === 'active' ? 'badge-success' : ($frozen ? 'badge-warning' : 'badge-danger');
      ?>
      <div class="card-tile">
        <!-- Visual card face -->
        <div class="card-visual <?= $class ?>" style="background:<?= htmlspecialchars($c['gradient']) ?>;">
          <?php if ($frozen): ?><div class="frozen-banner">❄️ FROZEN</div><?php endif; ?>
          <?php if ($blocked): ?><div class="frozen-banner" style="font-size:22px;letter-spacing:1px;">🔴 BLOCKED</div><?php endif; ?>
          <div style="font-size:26px;">💳</div>
          <div>
            <div class="card-number"><?= $fullnum ?></div>
            <div style="font-size:12px;opacity:.8;margin-top:4px;"><?= htmlspecialchars($account['full_name']) ?></div>
          </div>
          <div class="card-row">
            <div>
              <div class="card-exp-label">EXPIRES</div>
              <div class="card-exp-val"><?= $exp ?></div>
            </div>
            <div style="font-size:12px;font-weight:700;opacity:.9;"><?= $cbrand ?> <?= $ctype ?></div>
          </div>
          <?php if ($c['credit_limit'] > 0): ?>
          <div style="margin-top:6px;">
            <div style="font-size:11px;opacity:.8;">$<?= number_format($c['spent'],0) ?> of $<?= number_format($c['credit_limit'],0) ?> used</div>
            <div class="progress-bar-wrap">
              <div class="progress-bar-fill" style="width:<?= round($c['spent']/$c['credit_limit']*100) ?>%;"></div>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Status badges -->
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <span class="badge <?= $statusBadge ?>">
            <?= $c['status']==='active' ? '🟢' : ($frozen ? '❄️' : '🔴') ?> <?= $statusLabel ?>
          </span>
          <span class="badge badge-info"><?= $cbrand ?></span>
          <span class="badge badge-info"><?= $ctype ?></span>
        </div>

        <!-- Action buttons — real POST forms -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php if ($c['status'] === 'active'): ?>
          <!-- FREEZE -->
          <form method="POST" action="cards.php" style="display:inline;">
            <input type="hidden" name="action"  value="freeze"/>
            <input type="hidden" name="card_id" value="<?= $c['id'] ?>"/>
            <button type="submit" class="btn btn-outline btn-sm"
                    onclick="return confirm('Freeze card ending in <?= $last4 ?>? No payments will go through.')">
              ❄️ Freeze
            </button>
          </form>
          <?php elseif ($frozen): ?>
          <!-- UNBLOCK/UNFREEZE -->
          <form method="POST" action="cards.php" style="display:inline;">
            <input type="hidden" name="action"  value="unblock"/>
            <input type="hidden" name="card_id" value="<?= $c['id'] ?>"/>
            <button type="submit" class="btn btn-mint btn-sm"
                    onclick="return confirm('Unfreeze card ending in <?= $last4 ?>?')">
              ✅ Unfreeze
            </button>
          </form>
          <?php elseif ($blocked): ?>
          <!-- UNBLOCK from permanently blocked -->
          <form method="POST" action="cards.php" style="display:inline;">
            <input type="hidden" name="action"  value="unblock"/>
            <input type="hidden" name="card_id" value="<?= $c['id'] ?>"/>
            <button type="submit" class="btn btn-mint btn-sm"
                    onclick="return confirm('Unblock card ending in <?= $last4 ?>?')">
              ✅ Unblock
            </button>
          </form>
          <?php endif; ?>

          <!-- DETAILS (modal) -->
          <button type="button" class="btn btn-outline btn-sm"
                  onclick="showModal(
                    '<?= $label ?>',
                    '<?= $fullnum ?>',
                    '<?= $exp ?>',
                    '<?= $cbrand ?>',
                    '<?= $ctype ?>',
                    '<?= $statusLabel ?>',
                    '<?= $c['credit_limit'] ? number_format($c['credit_limit'],2) : 'N/A' ?>',
                    '<?= $c['credit_limit'] ? number_format($c['spent'],2) : 'N/A' ?>'
                  )">
            📋 Details
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- BOTTOM: Summary + Request -->
    <div class="two-col" style="margin-top:28px;">
      <!-- Usage summary (live from DB) -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">📊</span> Card Usage Summary</div>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:14px;color:var(--slate-mid);">Total credit limit</span>
            <span class="badge badge-info">$<?= number_format($total_limit,2) ?></span>
          </div>
          <?php if ($total_limit > 0): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:14px;color:var(--slate-mid);">Credit used</span>
            <span class="badge badge-warning">$<?= number_format($total_spent,2) ?> (<?= $total_limit>0?round($total_spent/$total_limit*100):0 ?>%)</span>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:14px;color:var(--slate-mid);">Available credit</span>
            <span class="badge badge-success">$<?= number_format($total_limit-$total_spent,2) ?></span>
          </div>
          <?php endif; ?>
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:14px;color:var(--slate-mid);">Active cards</span>
            <span class="badge badge-success"><?= $active_count ?> of <?= count($cards) ?></span>
          </div>
        </div>
      </div>

      <!-- Request new card -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">➕</span> Request New Card</div>
        <p style="font-size:14px;color:var(--slate-mid);margin-bottom:18px;">
          Apply for a new debit or credit card. Processing takes 3–5 business days.
        </p>
        <form method="POST" action="cards.php">
          <input type="hidden" name="action" value="request_card"/>
          <div class="form-group">
            <label class="form-label">Card Type</label>
            <select name="card_type" class="form-input">
              <option>Visa Debit</option>
              <option>Mastercard Debit</option>
              <option>Visa Credit</option>
              <option>Mastercard Credit</option>
              <option>Visa Virtual</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-full">
            📮 Submit Request
          </button>
        </form>
      </div>
    </div>
  </main>
</div>

<!-- DETAILS MODAL -->
<script>
function showModal(label, num, exp, brand, type, status, limit, spent) {
  document.getElementById('modalTitle').textContent = label;
  document.getElementById('modalBody').innerHTML = `
    <div class="detail-row"><span class="detail-label">Card Number</span><span class="detail-val" style="font-family:monospace">${num}</span></div>
    <div class="detail-row"><span class="detail-label">Expires</span><span class="detail-val">${exp}</span></div>
    <div class="detail-row"><span class="detail-label">Network</span><span class="detail-val">${brand}</span></div>
    <div class="detail-row"><span class="detail-label">Type</span><span class="detail-val">${type}</span></div>
    <div class="detail-row"><span class="detail-label">Status</span><span class="detail-val">${status}</span></div>
    ${limit !== 'N/A' ? `
    <div class="detail-row"><span class="detail-label">Credit Limit</span><span class="detail-val">$${limit}</span></div>
    <div class="detail-row"><span class="detail-label">Amount Spent</span><span class="detail-val">$${spent}</span></div>
    ` : ''}
  `;
  document.getElementById('detailModal').classList.add('show');
}
function closeModal() {
  document.getElementById('detailModal').classList.remove('show');
}
document.getElementById('detailModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
document.addEventListener('click', function(e) {
  const menu = document.getElementById('avatarMenu');
  if (!e.target.closest('.avatar') && !e.target.closest('.avatar-menu')) menu.classList.remove('show');
});
</script>
</body>
</html>
