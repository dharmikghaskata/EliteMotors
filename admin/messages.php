<?php
require_once 'includes/header.php';

// Set page title
$page_title = 'Messages - Admin Panel';

// Get all messages from the database
$messages = [];
$error = '';
$success = '';

// Handle success/error messages from other actions
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

try {
    // Check if contact_messages table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'contact_messages'");
    
    if (!$table_check) {
        throw new Exception('Error checking for messages table: ' . $conn->error);
    }

    if ($table_check->num_rows > 0) {
        // Table exists, fetch messages with proper error handling
        $result = $conn->query("
            SELECT * 
            FROM contact_messages 
            ORDER BY is_read ASC, created_at DESC
        ");
        
        if ($result) {
            $messages = $result->fetch_all(MYSQLI_ASSOC);
            
            // Mark all messages as read
            $conn->query("UPDATE contact_messages SET is_read = 1 WHERE is_read = 0");
        } else {
            throw new Exception('Error fetching messages: ' . $conn->error);
        }
    } else {
        $error = 'No messages have been received yet. The table will be created automatically when the first message is sent.';
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log('Messages Page Error: ' . $e->getMessage());
}
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Messages</h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Contact Messages</h6>
                    <?php if (!empty($messages)): ?>
                        <div>
                            <span class="badge bg-primary">Total: <?php echo count($messages); ?></span>
                            <?php 
                            $unread_count = array_reduce($messages, function($carry, $item) {
                                return $carry + ($item['is_read'] ? 0 : 1);
                            }, 0);
                            ?>
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-warning">Unread: <?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <p class="text-muted text-center my-3">No messages found</p>
                    <?php else: ?>
                        <div class="activity-feed">
                            <?php foreach ($messages as $message): ?>
                                <div class="feed-item mb-4 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                                            <?php if (!$message['is_read']): ?>
                                                <span class="badge bg-warning">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="mb-1">
                                        <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>">
                                            <?php echo htmlspecialchars($message['email']); ?>
                                        </a>
                                        <?php if (!empty($message['phone'])): ?>
                                            <span class="mx-2">|</span>
                                            <a href="tel:<?php echo htmlspecialchars($message['phone']); ?>">
                                                <?php echo htmlspecialchars($message['phone']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($message['subject'])): ?>
                                        <div class="fw-bold mb-1">
                                            <?php echo htmlspecialchars($message['subject']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <p class="mb-1 text-muted">
                                        <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
