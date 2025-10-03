<?php
require_once 'includes/init.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate input
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

$message_id = (int)$_POST['id'];

// Update the message as read
$query = "UPDATE contact_messages SET is_read = 1 WHERE message_id = ?";
$stmt = $conn->prepare($query);

if ($stmt) {
    $stmt->bind_param("i", $message_id);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Message marked as read',
            'message_id' => $message_id
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to update message status: ' . $conn->error
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $conn->error
    ]);
}

$conn->close();
?>
