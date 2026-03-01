<?php
// upload.php — Profile Photo Upload
// VULNERABILITY: No file type/extension/MIME validation
// Any file including PHP webshells can be uploaded and executed via /uploads/shell.php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id    = $_SESSION['user_id'];
$account_id = $_SESSION['account_id'];
$success = $error = '';

$acc_sql = "SELECT accounts.*, users.full_name, users.email, users.avatar_color
              FROM accounts JOIN users ON accounts.user_id = users.id
             WHERE accounts.id = $account_id";
$account = mysqli_fetch_assoc(mysqli_query($conn, $acc_sql));
$initials = strtoupper(substr($account['full_name'], 0, 1))
          . strtoupper(explode(' ', $account['full_name'])[1][0] ?? '');

$current_photo = $_SESSION['photo'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file     = $_FILES['photo'];
    $filename = basename($file['name']);           // original name kept — no sanitization
    $dest     = __DIR__ . '/uploads/' . $filename; // saved directly to uploads/

    if ($file['error'] === UPLOAD_ERR_OK) {
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            // Store path in session (no DB column for photo — just session)
            $_SESSION['photo'] = 'uploads/' . $filename;
            $success = "Photo \"$filename\" uploaded successfully! View it at: "
                     . "<a href='uploads/$filename' target='_blank'>uploads/$filename</a>";
        } else {
            $error = 'Upload failed. Check folder permissions.';
        }
    } else {
        $error = 'File error: ' . $file['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>BankLab — Upload Profile Photo</title>
<link rel="stylesheet" href="assets/style.css"/>
<style>
.upload-zone {
  border: 2.5px dashed var(--border);
  border-radius: 20px;
  padding: 50px 30px;
  text-align: center;
  background: var(--bg);
  transition: border-color .2s, background .2s;
  cursor: pointer;
}
.upload-zone:hover { border-color: var(--primary); background: var(--primary-soft); }
.upload-zone input[type=file] { display:none; }
.upload-icon { font-size: 52px; margin-bottom: 12px; }
.upload-hint { font-size: 13px; color: var(--slate-light); margin-top: 8px; }
.preview-img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover;
               border: 4px solid white; box-shadow: 0 6px 24px rgba(0,0,0,0.12); }
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
    <?php if ($_SESSION['is_admin'] ?? false): ?><a href="admin.php" class="nav-link"><div class="nav-icon">👑</div> Admin</a><?php endif; ?>
    <div class="sidebar-footer">
      <a href="logout.php" class="nav-link" style="color:#E53E3E;"><div class="nav-icon" style="background:#FFF0F3;">🚪</div> Sign Out</a>
    </div>
  </aside>

  <main class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">📷 Upload Profile Photo</div>
        <div class="topbar-subtitle">Update your profile picture</div>
      </div>
      <div class="topbar-right">
        <div class="notif-btn" onclick="window.location='notifications.php'" style="cursor:pointer;">🔔<div class="notif-dot"></div></div>
        <div class="avatar" style="background:linear-gradient(135deg,<?= htmlspecialchars($account['avatar_color']) ?>,#A8D8F8);">
          <?= $initials ?><div class="avatar-badge"></div>
        </div>
      </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="two-col">
      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">📷</span> Choose Photo</div>
        <form method="POST" action="upload.php" enctype="multipart/form-data" id="uploadForm">
          <label for="photoInput">
            <div class="upload-zone" onclick="document.getElementById('photoInput').click()">
              <div class="upload-icon">☁️</div>
              <div style="font-weight:700;color:var(--slate);font-size:16px;">Click to browse files</div>
              <div class="upload-hint">Supports JPG, PNG, GIF — Max 5MB</div>
            </div>
          </label>
          <input type="file" name="photo" id="photoInput" onchange="previewFile(this); document.getElementById('uploadForm').submit()"/>
        </form>

        <?php if ($current_photo): ?>
        <div style="margin-top:20px;text-align:center;">
          <p style="font-size:13px;color:var(--slate-mid);margin-bottom:12px;">Current photo:</p>
          <img src="<?= htmlspecialchars($current_photo) ?>" class="preview-img" id="previewImg" alt="Profile photo"/>
        </div>
        <?php else: ?>
        <div style="margin-top:20px;text-align:center;display:none;" id="previewWrap">
          <img src="" class="preview-img" id="previewImg" alt="Preview"/>
        </div>
        <?php endif; ?>
      </div>

      <div class="card fade-in">
        <div class="card-title"><span class="card-icon">ℹ️</span> Upload Guidelines</div>
        <div style="display:flex;flex-direction:column;gap:14px;">
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <span style="font-size:22px;">📐</span>
            <div><strong>Recommended size</strong><br/><span style="font-size:13px;color:var(--slate-mid);">400×400 pixels or larger for best quality</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <span style="font-size:22px;">🖼️</span>
            <div><strong>File formats</strong><br/><span style="font-size:13px;color:var(--slate-mid);">JPEG, PNG, and GIF are all accepted</span></div>
          </div>
          <div style="display:flex;gap:12px;align-items:flex-start;">
            <span style="font-size:22px;">💾</span>
            <div><strong>File size limit</strong><br/><span style="font-size:13px;color:var(--slate-mid);">Maximum 5MB per upload</span></div>
          </div>
        </div>
        <hr class="divider"/>
        <a href="profile.php" class="btn btn-outline btn-full">← Back to Profile</a>
      </div>
    </div>
  </main>
</div>
<script>
function previewFile(input) {
  const file = input.files[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('previewImg').src = e.target.result;
      document.getElementById('previewWrap') && (document.getElementById('previewWrap').style.display='block');
    };
    reader.readAsDataURL(file);
  }
}
</script>
</body>
</html>
