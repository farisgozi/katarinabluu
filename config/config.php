<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kasir_resto');

// Establish database connection
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Set timezone
date_default_timezone_set('Asia/Jakarta');

// Common functions
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Check session timeout
function check_session_timeout() {
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        header("Location: /pages/auth/login.php");
        exit();
    }
    $_SESSION['last_activity'] = time();
}