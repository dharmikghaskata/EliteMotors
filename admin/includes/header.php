<?php
// Start output buffering to prevent any accidental output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default page title before including database connection
$page_title = $page_title ?? 'Admin Dashboard';

// Include database connection
require_once __DIR__ . '/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // Clear any output that might have been generated before this check
    ob_end_clean();
    header('Location: login.php');
    exit();
}

// Get unread notifications count
$unread_count = 0;
$notifications = [];

// Check if database connection exists
if (isset($conn) && $conn) {
    try {
        // First check if notifications table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
        
        if ($table_check && $table_check->num_rows > 0) {
            // Table exists, proceed with notifications query
            $notifications_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
            if ($notifications_result) {
                $unread_count = $notifications_result->fetch_assoc()['count'];
                $notifications_result->free();
                
                // Get recent notifications
                $notifications_result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
                if ($notifications_result) {
                    $notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);
                    $notifications_result->free();
                }
            }
        }
    } catch (Exception $e) {
        // Log the error but don't break the page
        error_log('Error in header.php: ' . $e->getMessage());
    }
}

// End output buffering and clean (discard) any output
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Ensure proper rendering -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Custom CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        /* Ensure icons are properly displayed */
        .bi {
            display: inline-block;
            vertical-align: -.125em;
        }
        
        /* Notification dropdown styles */
        .dropdown-menu.notification-dropdown {
            width: 350px;
            max-height: 400px;
            overflow-y: auto;
        }
        .notification-item.unread {
            background-color: #f8f9fa;
        }
        .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        /* Remove underline from sidebar links on focus/active */
        #sidebar-wrapper .list-group-item {
            text-decoration: none !important;
        }
        
        #sidebar-wrapper .list-group-item:focus,
        #sidebar-wrapper .list-group-item:active {
            text-decoration: none !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body>
    <?php
    // Get unread notifications count
    $unread_count = 0;
    $notifications = [];
    
    // First check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    
    if ($table_check && $table_check->num_rows > 0) {
        // Table exists, proceed with notifications query
        $notifications_result = $conn->query("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
        if ($notifications_result) {
            $unread_count = $notifications_result->fetch_assoc()['count'];
            $notifications_result->free();
            
            // Get recent notifications
            $notifications_result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
            if ($notifications_result) {
                $notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);
                $notifications_result->free();
            }
        }
    }
    ?>
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <div class="bg-dark text-white" id="sidebar-wrapper" style="width: 250px;">
            <div class="sidebar-heading text-center py-4">
                <h4>Elite Motors</h4>
                <p class="mb-0">Admin Panel</p>
            </div>
            <div class="list-group list-group-flush">
                <a href="index.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
                <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="bi bi-people me-2"></i> Users
                </a>
                <a href="cars.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="bi bi-car-front me-2"></i> Cars
                <a href="messages.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="bi bi-envelope me-2"></i> Messages
                    <?php 
                    $unread_count = 0;
                    try {
                        // Check if contact_messages table exists
                        $table_check = $conn->query("SHOW TABLES LIKE 'contact_messages'");
                        if ($table_check && $table_check->num_rows > 0) {
                            $unread_result = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0");
                            if ($unread_result && $unread_row = $unread_result->fetch_assoc()) {
                                $unread_count = $unread_row['count'];
                            }
                        }
                    } catch (Exception $e) {
                        // Table doesn't exist or other error - we'll show 0 unread
                        $unread_count = 0;
                    }
                    if ($unread_count > 0): ?>
                        <span class="badge bg-danger float-end"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="list-group-item list-group-item-action bg-dark text-white">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </div>
        </div>
        <!-- /#sidebar-wrapper -->

        <!-- Page Content -->
        <div id="page-content-wrapper" style="width: calc(100% - 250px);">
            <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
                <div class="container-fluid">
                    <button class="btn btn-link" id="menu-toggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="ms-auto d-flex align-items-center">
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="container-fluid px-4 py-4">
                <!-- Page content will be loaded here -->
