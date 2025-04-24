<?php
session_start();

// Check if user is not logged in
if (!isset($_SESSION['user'])) {
    header("Location: pages/auth/login.php");
    exit();
}

// Get user data from session
$user = $_SESSION['user'];
$level = $user['level'];

// Redirect to appropriate dashboard based on user level
switch ($level) {
    case 'admin':
        header("Location: pages/admin/dashboard.php");
        break;
    case 'kasir':
        header("Location: pages/kasir/dashboard.php");
        break;
    case 'owner':
        header("Location: pages/owner/dashboard.php");
        break;
    case 'waiter':
        header("Location: pages/waiter/dashboard.php");
        break;
    default:
        header("Location: pages/auth/login.php");
        break;
}
exit();