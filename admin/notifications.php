<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'includes/db_connect.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit();
}

// Mark all notifications as read if requested
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
    $_SESSION['success'] = 'All notifications marked as read';
    header('Location: notifications.php');
    exit();
}

// Include header after all header modifications
require_once 'includes/header.php';

// Get all notifications, newest first
$notifications = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC");
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">Notifications</h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-bell me-1"></i>
                All Notifications
            </div>
            <a href="?mark_all_read" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-check2-all me-1"></i> Mark all as read
            </a>
        </div>
        <div class="card-body">
            <?php if ($notifications->num_rows === 0): ?>
                <div class="text-center py-4">
                    <i class="bi bi-bell-slash fs-1 text-muted"></i>
                    <p class="mt-2 mb-0">No notifications found</p>
                </div>
            <?php else: ?>
                <div class="list-group">
                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                        <a href="#" class="list-group-item list-group-item-action <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>" 
                           data-notification-id="<?php echo $notification['notification_id']; ?>">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <?php if (!$notification['is_read']): ?>
                                <small class="text-primary">New</small>
                            <?php endif; ?>
                        </a>
                    <?php endwhile; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
