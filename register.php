<?php
// register.php — User Registration
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php?account_id=" . $_SESSION['account_id']);
    exit;
}
require_once 'db.php';

$error   = '';
$success = '';

// Avatar colour palette (random pick on registration)
$colours = ['#FF6B9D','#43D0AE','#F5A623','#7C83FD','#4F8EF7','#9B8FFF','#38D9A9','#FFD166'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']       ?? '';
    $confirm   = $_POST['confirm']        ?? '';

    // Basic validation
    if (!$username || !$full_name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check username / email uniqueness
        $check = mysqli_query($conn,
            "SELECT id FROM users WHERE username='$username' OR email='$email' LIMIT 1"
        );
        if (mysqli_num_rows($check) > 0) {
            $error = 'Username or email already taken. Please choose another.';
        } else {
            // Pick a random avatar colour
            $colour = $colours[array_rand($colours)];
            $colour_s = mysqli_real_escape_string($conn, $colour);
            $un_s  = mysqli_real_escape_string($conn, $username);
            $fn_s  = mysqli_real_escape_string($conn, $full_name);
            $em_s  = mysqli_real_escape_string($conn, $email);
            $pw_s  = mysqli_real_escape_string($conn, $password); // stored plaintext (intentional – lab)

            // Insert user
            mysqli_query($conn,
                "INSERT INTO users (username, password, full_name, email, avatar_color)
                 VALUES ('$un_s', '$pw_s', '$fn_s', '$em_s', '$colour_s')"
            );
            $user_id = mysqli_insert_id($conn);

            // Create a savings account (sequential ID = IDOR target, intentional)
            $acc_num = 'BA-' . str_pad($user_id, 4, '0', STR_PAD_LEFT) . '-' . rand(1000, 9999);
            $acc_num_s = mysqli_real_escape_string($conn, $acc_num);
            mysqli_query($conn,
                "INSERT INTO accounts (user_id, account_number, account_type, balance)
                 VALUES ($user_id, '$acc_num_s', 'Savings', 1000.00)"
            );
            $account_id = mysqli_insert_id($conn);

            // Create a default Visa Debit card
            $last4    = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $expiry   = date('m/y', strtotime('+3 years'));
            $gradients = [
                'linear-gradient(135deg,#4F8EF7,#9B8FFF)',
                'linear-gradient(135deg,#38D9A9,#4F8EF7)',
                'linear-gradient(135deg,#FF6B9D,#FF8E53)',
                'linear-gradient(135deg,#F5A623,#F76B1C)',
                'linear-gradient(135deg,#7C83FD,#48CAE4)',
            ];
            $gradient = $gradients[array_rand($gradients)];
            $label_s    = mysqli_real_escape_string($conn, $full_name . "'s Card");
            $gradient_s = mysqli_real_escape_string($conn, $gradient);
            mysqli_query($conn,
                "INSERT INTO cards (user_id, label, last4, expiry, card_type, card_brand, gradient, status)
                 VALUES ($user_id, '$label_s', '$last4', '$expiry', 'Debit', 'Visa', '$gradient_s', 'active')"
            );

            // Auto-login
            $_SESSION['user_id']      = $user_id;
            $_SESSION['username']     = $username;
            $_SESSION['full_name']    = $full_name;
            $_SESSION['email']        = $email;
            $_SESSION['account_id']   = $account_id;
            $_SESSION['avatar_color'] = $colour;
            $_SESSION['is_admin']     = false;

            header("Location: dashboard.php?account_id=$account_id");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab – Create Account</title>
<link rel="stylesheet" href="assets/style.css"/>
<style>
.dots-bg {
  position: fixed; inset: 0;
  background-image: radial-gradient(circle at 1px 1px, rgba(79,142,247,0.08) 1px, transparent 0);
  background-size: 32px 32px;
  pointer-events: none; z-index: 0;
}
.auth-card { position: relative; z-index: 1; max-width: 480px; }

/* two-column name row */
.two-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* strength bar */
.strength-wrap { margin-top: 6px; height: 5px; border-radius: 10px; background: var(--border); overflow: hidden; }
.strength-bar  { height: 100%; border-radius: 10px; width: 0; transition: width .3s, background .3s; }

/* divider with text */
.or-divider {
  display: flex; align-items: center; gap: 12px;
  color: var(--slate-light); font-size: 13px; font-weight: 600;
  margin: 20px 0;
}
.or-divider::before, .or-divider::after {
  content: ''; flex: 1; border-top: 2px solid var(--border);
}

/* input icon wrapper */
.input-wrap { position: relative; }
.input-wrap .input-icon {
  position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
  font-size: 16px; pointer-events: none;
}
.input-wrap .form-input { padding-left: 44px; }

/* password toggle */
.toggle-pw {
  position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; font-size: 17px; color: var(--slate-light);
  padding: 4px;
}
.toggle-pw:hover { color: var(--primary); }

/* step indicator */
.steps {
  display: flex; justify-content: center; gap: 8px; margin-bottom: 28px;
}
.step-dot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--border); transition: all .3s;
}
.step-dot.done { background: var(--mint); }
.step-dot.active { background: var(--primary); width: 24px; border-radius: 10px; }

/* bonus tag */
.bonus-tag {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--mint-soft); color: #1A7A5E;
  border: 1.5px solid #B2E8D8; border-radius: 20px;
  padding: 5px 14px; font-size: 12px; font-weight: 700;
  margin-bottom: 22px;
}
</style>
</head>
<body>
<div class="auth-page">
  <div class="dots-bg"></div>

  <div class="auth-card card fade-in" style="padding:48px 44px;">
    <!-- Logo -->
    <div class="auth-logo">
      <div class="logo-icon" style="width:60px;height:60px;margin:0 auto 14px;font-size:28px;">🏦</div>
      <div class="logo-text" style="font-size:22px;text-align:center;">Bank<span>Lab</span></div>
    </div>

    <!-- Steps indicator -->
    <div class="steps">
      <div class="step-dot done" id="dot1"></div>
      <div class="step-dot active" id="dot2"></div>
      <div class="step-dot" id="dot3"></div>
    </div>

    <h1 class="auth-title">Create your account</h1>
    <p class="auth-sub">Join BankLab and get started in seconds</p>

    <!-- Welcome bonus badge -->
    <div style="text-align:center;">
      <span class="bonus-tag">🎁 $1,000 welcome bonus on signup</span>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <span>⚠️</span> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="register.php" id="regForm" novalidate>

      <!-- Full name + Username -->
      <div class="two-fields">
        <div class="form-group">
          <label class="form-label" for="full_name">Full Name</label>
          <div class="input-wrap">
            <span class="input-icon">👤</span>
            <input type="text" id="full_name" name="full_name" class="form-input"
                   placeholder="Alice Johnson"
                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required/>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <div class="input-wrap">
            <span class="input-icon">🪪</span>
            <input type="text" id="username" name="username" class="form-input"
                   placeholder="alice99" autocomplete="off"
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required/>
          </div>
        </div>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon">✉️</span>
          <input type="email" id="email" name="email" class="form-input"
                 placeholder="alice@example.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required/>
        </div>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" id="password" name="password" class="form-input"
                 placeholder="Min. 6 characters" required
                 oninput="updateStrength(this.value)"/>
          <button type="button" class="toggle-pw" onclick="togglePw('password',this)" title="Show/hide password">👁️</button>
        </div>
        <!-- Strength meter -->
        <div class="strength-wrap"><div class="strength-bar" id="strengthBar"></div></div>
        <div style="font-size:11px;color:var(--slate-light);margin-top:4px;" id="strengthLabel"></div>
      </div>

      <!-- Confirm Password -->
      <div class="form-group">
        <label class="form-label" for="confirm">Confirm Password</label>
        <div class="input-wrap">
          <span class="input-icon">🔑</span>
          <input type="password" id="confirm" name="confirm" class="form-input"
                 placeholder="Re-enter your password" required/>
          <button type="button" class="toggle-pw" onclick="togglePw('confirm',this)" title="Show/hide password">👁️</button>
        </div>
        <div style="font-size:11px;margin-top:4px;" id="matchLabel"></div>
      </div>

      <button type="submit" class="btn btn-primary btn-full" style="margin-top:4px;" id="submitBtn">
        🚀 &nbsp; Create Account
      </button>
    </form>

    <div class="or-divider">or</div>

    <p style="text-align:center;font-size:14px;color:var(--slate-mid);">
      Already have an account?
      <a href="login.php" class="auth-link">Sign in →</a>
    </p>

    <hr class="divider"/>
    <p class="text-muted" style="text-align:center;">
      🏦 BankLab &nbsp;·&nbsp; Secure Internet Banking
    </p>
  </div>
</div>

<script>
// ── Password strength meter ──────────────────────────
function updateStrength(val) {
  const bar   = document.getElementById('strengthBar');
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (val.length >= 6)  score++;
  if (val.length >= 10) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = [
    { w:'0%',   bg:'transparent', txt:'' },
    { w:'20%',  bg:'#FF6B6B',     txt:'Very weak' },
    { w:'40%',  bg:'#FFD166',     txt:'Weak' },
    { w:'60%',  bg:'#F5A623',     txt:'Fair' },
    { w:'80%',  bg:'#43D0AE',     txt:'Strong' },
    { w:'100%', bg:'#20C997',     txt:'Very strong 💪' },
  ];
  const lv = levels[Math.min(score, 5)];
  bar.style.width      = lv.w;
  bar.style.background = lv.bg;
  label.textContent    = lv.txt;
  label.style.color    = lv.bg;

  checkMatch();
}

// ── Password match hint ───────────────────────────────
document.getElementById('confirm').addEventListener('input', checkMatch);
function checkMatch() {
  const pw  = document.getElementById('password').value;
  const cfm = document.getElementById('confirm').value;
  const lbl = document.getElementById('matchLabel');
  if (!cfm) { lbl.textContent = ''; return; }
  if (pw === cfm) {
    lbl.textContent = '✅ Passwords match';
    lbl.style.color = '#20A080';
  } else {
    lbl.textContent = '❌ Passwords do not match';
    lbl.style.color = '#C0392B';
  }
}

// ── Toggle show/hide password ─────────────────────────
function togglePw(id, btn) {
  const inp = document.getElementById(id);
  if (inp.type === 'password') {
    inp.type  = 'text';
    btn.textContent = '🙈';
  } else {
    inp.type  = 'password';
    btn.textContent = '👁️';
  }
}

// ── Animate step dots as user fills in fields ─────────
const fields = ['full_name','username','email','password','confirm'];
fields.forEach(f => {
  const el = document.getElementById(f);
  if (el) el.addEventListener('input', updateDots);
});
function updateDots() {
  const filled = fields.filter(f => document.getElementById(f)?.value.trim()).length;
  const dots = document.querySelectorAll('.step-dot');
  dots.forEach((d,i) => {
    d.classList.remove('active','done');
    const threshold = [0,2,4][i] ?? 99;
    if (filled > threshold + 1)       d.classList.add('done');
    else if (filled >= threshold)     d.classList.add('active');
  });
}
</script>
</body>
</html>
