<?php
// profile.php — Edit profile page
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id    = $_SESSION['user_id'];
$account_id = $_SESSION['account_id'];
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $email     = $_POST['email']     ?? '';
    $new_pass  = $_POST['new_pass']  ?? '';
    $cur_pass  = $_POST['cur_pass']  ?? '';

    // Verify current password (raw compare — intentional)
    $chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT password FROM users WHERE id = $user_id"));
    if ($chk['password'] !== $cur_pass) {
        $error = 'Current password is incorrect.';
    } else {
        $update_fields = "full_name = '$full_name', email = '$email'";
        if (!empty($new_pass)) {
            $update_fields .= ", password = '$new_pass'";
        }
        mysqli_query($conn, "UPDATE users SET $update_fields WHERE id = $user_id");
        $_SESSION['full_name'] = $full_name;
        $_SESSION['email']     = $email;
        $success = 'Profile updated successfully!';
    }
}

$user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id"));
$acc  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM accounts WHERE id = $account_id"));
$initials = strtoupper(substr($user['full_name'], 0, 1))
          . strtoupper(explode(' ', $user['full_name'])[1][0] ?? '');

$avatar_colors = ['#FF6B9D','#43D0AE','#4F8EF7','#F5A623','#9B8FFF','#FF6B6B','#38D9A9'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Edit Profile</title>
<link rel="stylesheet" href="assets/style.css"/>
<style>
.avatar-lg {
  width: 90px; height: 90px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-head); font-weight: 900; font-size: 32px;
  color: white; box-shadow: 0 6px 24px rgba(0,0,0,0.15);
  border: 4px solid white; margin: 0 auto 16px;
}
.color-swatch {
  width: 32px; height: 32px; border-radius: 50%; cursor: pointer;
  border: 3px solid transparent; transition: transform .2s, border-color .2s;
}
.color-swatch:hover { transform: scale(1.2); }
.color-swatch.selected { border-color: var(--slate); transform: scale(1.2); }
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
    <a href="analytics.php" class="nav-link"><div class="nav-icon">📈</div> Analytics</a>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;"><div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">👤 Edit Profile</div>
        <div class="topbar-subtitle">Update your personal information and security settings</div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="window.location='notifications.php'" title="Notifications">🔔<div class="notif-dot"></div></div>
        <div class="avatar" style="background:linear-gradient(135deg,<?= htmlspecialchars($user['avatar_color']) ?>,#A8D8F8);">
          <?= $initials ?><div class="avatar-badge"></div>
        </div>
      </div>
    </div>

    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="two-col">
      <!-- Profile form -->
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">✏️</span> Personal Information</div>

        <!-- Avatar preview -->
        <div style="text-align:center;margin-bottom:24px;">
          <div class="avatar-lg" id="avatarPreview" style="background:linear-gradient(135deg,<?= htmlspecialchars($user['avatar_color']) ?>,#A8D8F8);">
            <?= $initials ?>
          </div>
          <p style="font-size:13px;color:var(--slate-light);margin-bottom:10px;">Choose avatar colour</p>
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
            <?php foreach ($avatar_colors as $col): ?>
            <div class="color-swatch <?= $col === $user['avatar_color'] ? 'selected' : '' ?>"
                 style="background:<?= $col ?>;"
                 data-color="<?= $col ?>"
                 onclick="pickColor(this)"></div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="avatar_color" id="avatarColorInput" value="<?= htmlspecialchars($user['avatar_color']) ?>"/>
        </div>

        <form method="POST" action="profile.php">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-input"
                   value="<?= htmlspecialchars($user['full_name']) ?>" required/>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-input"
                   value="<?= htmlspecialchars($user['email']) ?>" required/>
          </div>
          <hr class="divider"/>
          <div class="card-title" style="margin-bottom:16px;"><span class="card-icon">🔐</span> Change Password</div>
          <div class="form-group">
            <label class="form-label">Current Password</label>
            <input type="password" name="cur_pass" class="form-input"
                   placeholder="Enter current password" required/>
          </div>
          <div class="form-group">
            <label class="form-label">New Password <span style="color:var(--slate-light);font-weight:400;">(leave blank to keep current)</span></label>
            <input type="password" name="new_pass" class="form-input" placeholder="New password"/>
          </div>
          <button type="submit" class="btn btn-primary btn-full">💾 Save Changes</button>
        </form>
      </div>

      <!-- Account info read-only -->
      <div style="display:flex;flex-direction:column;gap:20px;">
        <div class="card fade-in">
          <div class="card-title"><span class="card-icon">🏦</span> Account Details</div>
          <div style="display:flex;flex-direction:column;gap:14px;">
            <?php $rows = [
              ['label'=>'Username',       'value'=>$user['username']],
              ['label'=>'Account Number', 'value'=>$acc['account_number']],
              ['label'=>'Account Type',   'value'=>$acc['account_type']],
              ['label'=>'Balance',        'value'=>'$'.number_format($acc['balance'],2)],
              ['label'=>'Member Since',   'value'=>date('F j, Y', strtotime($user['created_at']))],
            ]; foreach ($rows as $r): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1.5px solid var(--border);">
              <span style="font-size:13px;color:var(--slate-light);font-weight:600;"><?= $r['label'] ?></span>
              <span style="font-size:14px;font-weight:700;color:var(--slate);"><?= htmlspecialchars($r['value']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="card fade-in">
          <div class="card-title"><span class="card-icon">⚠️</span> Danger Zone</div>
          <p style="font-size:13px;color:var(--slate-mid);margin-bottom:14px;">These actions are permanent and cannot be undone.</p>
          <button class="btn btn-danger btn-full" onclick="alert('Feature not available in this version.')">
            🗑️ Close Account
          </button>
        </div>
      </div>
    </div>
  </main>
</div>
<script>
function pickColor(el) {
  document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('selected'));
  el.classList.add('selected');
  const col = el.dataset.color;
  document.getElementById('avatarPreview').style.background = `linear-gradient(135deg,${col},#A8D8F8)`;
  document.getElementById('avatarColorInput').value = col;
}
</script>
</body>
</html>
