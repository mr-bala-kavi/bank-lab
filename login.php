<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php?account_id=" . $_SESSION['account_id']);
    exit;
}
require_once 'db.php';

$error = '';

// VULNERABILITY: Open Redirect via ?redirect= parameter (no validation)
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $sql = "SELECT users.*, accounts.id AS account_id
             FROM users
             JOIN accounts ON users.id = accounts.user_id
            WHERE users.username = '$username'
            LIMIT 1";  // Check user first (enumeration sink)

    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        if ($user['password'] === $password) {
            $_SESSION['user_id']      = $user['id'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['full_name']    = $user['full_name'];
            $_SESSION['email']        = $user['email'];
            $_SESSION['account_id']   = $user['account_id'];
            $_SESSION['avatar_color'] = $user['avatar_color'];
            $_SESSION['is_admin']     = ($user['username'] === 'admin');  // role flag
            // OPEN REDIRECT: $redirect used directly without validation
            $dest = !empty($redirect) ? $redirect : "dashboard.php?account_id=" . $user['account_id'];
            header("Location: $dest");
            exit;
        } else {
            $error = 'Incorrect password. Please try again.';  // username exists — enumeration
        }
    } else {
        $error = 'No account found with that username.';       // username doesn't exist — enumeration
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab – Sign In</title>
<link rel="stylesheet" href="assets/style.css"/>
<style>
.dots-bg {
  position: fixed; inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(79,142,247,0.08) 1px, transparent 0);
  background-size: 32px 32px;
  pointer-events: none; z-index: 0;
}
.auth-card { position: relative; z-index: 1; }
</style>
</head>
<body>
<div class="auth-page">
  <div class="dots-bg"></div>

  <div class="auth-card fade-in">
    <div class="auth-logo">
      <div class="logo-icon">🏦</div>
      <div class="logo-text">Bank<span>Lab</span></div>
    </div>

    <h1 class="auth-title">Welcome back!</h1>
    <p class="auth-sub">Sign in to your account to continue</p>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <span>⚠️</span> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= !empty($redirect) ? '?redirect='.urlencode($redirect) : '' ?>">
      <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>"/>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-input"
               placeholder="Enter your username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="off"/>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input"
               placeholder="Enter your password"/>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top: 8px;">
        🔑 &nbsp; Sign In
      </button>
    </form>

    <hr class="divider"/>
    <p style="text-align:center;font-size:14px;color:var(--slate-mid);margin-bottom:14px;">
      Don't have an account?
      <a href="register.php" class="auth-link">Create one →</a>
    </p>
    <p class="text-muted" style="text-align:center;">
      🏦 BankLab &nbsp;·&nbsp; Secure Internet Banking
    </p>
  </div>
</div>
</body>
</html>
