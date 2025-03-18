<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mcq_system');

// Application settings
define('SITE_NAME', 'MCQ Management System');
define('BASE_URL', 'http://localhost/mcq_system/');

// Session settings
ini_set('session.gc_maxlifetime', 900); // 15 minutes in seconds
session_start();

// Session timeout settings
define('SESSION_TIMEOUT', 900); // 15 minutes in seconds

// Check for session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    // Last activity was more than 15 minutes ago
    session_unset();     // Unset $_SESSION variables
    session_destroy();   // Destroy the session
    
    // Store timeout message in a cookie to display after redirect
    setcookie('session_timeout', 'true', time() + 30, '/');
    
    // Redirect to login page
    header("Location: " . BASE_URL . "login.php");
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();
?> 