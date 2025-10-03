<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base URL
define('BASE_URL', '/elitemotors/');

// Include database connection
require_once __DIR__ . '/db_connect.php';
?>
