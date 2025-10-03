<?php
require_once 'includes/db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Unauthorized');
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['id'])) {
        // Mark single notification as read
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
    } elseif (isset($_POST['all']) && $_POST['all'] == 1) {
        // Mark all notifications as read
        $conn->query("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0");
        echo json_encode(['success' => true]);
    } else {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Invalid request']);
    }
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed']);
}
