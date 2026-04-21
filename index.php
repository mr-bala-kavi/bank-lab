<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php?account_id=" . $_SESSION['account_id']);
} else {
    header("Location: login.php");
}
exit;
?>
