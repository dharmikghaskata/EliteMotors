<?php
require_once 'includes/init.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = 'Please log in to perform this action';
    header('Location: login.php');
    exit;
}

// Validate input
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid message ID';
    header('Location: messages.php');
    exit;
}

$message_id = (int)$_GET['id'];

// Delete the message
$query = "DELETE FROM contact_messages WHERE message_id = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("i", $message_id);
    $result = $stmt->execute();
    
    if ($result) {
        $_SESSION['success'] = 'Message deleted successfully';
    } else {
        $_SESSION['error'] = 'Failed to delete message: ' . $conn->error;
    }
    
    $stmt->close();
} else {
    $_SESSION['error'] = 'Database error: ' . $conn->error;
}

// Redirect back to messages page
header('Location: messages.php');
exit;
?>
