<?php
// Database connection for both admin and main application
$admin_db = new mysqli("localhost", "root", "", "elitemotors_db");

// Check connection
if ($admin_db->connect_error) {
    die("Database Connection failed: " . $admin_db->connect_error);
}

// Set charset to utf8
$admin_db->set_charset("utf8mb4");

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Set the global connection variable
$conn = $admin_db;
?>
