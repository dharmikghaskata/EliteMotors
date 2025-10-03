<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if required parameters are provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['car_id'], $_POST['status'], $_POST['csrf_token'])) {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: index.php');
    exit();
}

// Verify CSRF token
if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'Invalid security token. Please try again.';
    header('Location: ' . $_SERVER['HTTP_REFERER'] ?? 'index.php');
    exit();
}

$car_id = (int)$_POST['car_id'];
$status = $_POST['status'];

// Get car details to verify ownership
$stmt = $conn->prepare("SELECT user_id FROM cars WHERE car_id = ?");
$stmt->bind_param("i", $car_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $_SESSION['error'] = 'Car not found.';
    header('Location: index.php');
    exit();
}

$car = $result->fetch_assoc();

// Check if user is the owner or admin
if ($car['user_id'] != $_SESSION['user_id'] && !isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = 'You do not have permission to update this listing.';
    header('Location: car_details.php?id=' . $car_id);
    exit();
}

// Update car status
$success = false;
$redirect_url = 'car_details.php?id=' . $car_id;

try {
    if ($status === 'sold') {
        // Mark as sold
        $update = $conn->prepare("UPDATE cars SET is_sold = 1, updated_at = NOW() WHERE car_id = ?");
        $update->bind_param("i", $car_id);
        $success = $update->execute();
        $update->close();
        
        if ($success) {
            $_SESSION['success'] = 'Car marked as sold successfully.';
        } else {
            throw new Exception('Failed to update car status.');
        }
    } else if ($status === 'active') {
        // Mark as available (admin only)
        if (isset($_SESSION['admin_id'])) {
            $update = $conn->prepare("UPDATE cars SET is_sold = 0, updated_at = NOW() WHERE car_id = ?");
            $update->bind_param("i", $car_id);
            $success = $update->execute();
            $update->close();
            
            if ($success) {
                $_SESSION['success'] = 'Car marked as available successfully.';
            } else {
                throw new Exception('Failed to update car status.');
            }
        } else {
            $_SESSION['error'] = 'You do not have permission to perform this action.';
        }
    } else {
        $_SESSION['error'] = 'Invalid status specified.';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'An error occurred: ' . $e->getMessage();
}

// Redirect back
header('Location: ' . $redirect_url);
exit();
