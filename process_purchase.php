<?php
header('Content-Type: application/json');
session_start();
require_once 'includes/db_connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'debug' => []
];

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('You must be logged in to make a purchase.');
    }

    // Log received POST data for debugging
    $response['debug']['post_data'] = $_POST;
    $response['debug']['session'] = $_SESSION;

    // Validate required fields
    $required_fields = ['car_id', 'name', 'email', 'phone', 'address', 'payment_method'];
    $missing_fields = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception("Missing required fields: " . implode(', ', $missing_fields));
    }

    $car_id = intval($_POST['car_id']);
    $user_id = intval($_SESSION['user_id']);
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = preg_replace('/[^0-9+]/', '', $_POST['phone']);
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];

    if (!$email) {
        throw new Exception('Please provide a valid email address.');
    }

    // Start transaction
    // Start transaction
    $conn->begin_transaction();
    $response['debug']['transaction_started'] = true;

    try {
        // Check if car is still available
        $stmt = $conn->prepare("SELECT car_id, price, user_id, make, model, year FROM cars WHERE car_id = ? AND is_sold = 0");
        if (!$stmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $car_id);
        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $response['debug']['car_query'] = ['car_id' => $car_id, 'rows' => $result->num_rows];
        
        if ($result->num_rows === 0) {
            // Check if the car exists but is sold
            $check_sold = $conn->query("SELECT car_id FROM cars WHERE car_id = $car_id AND is_sold = 1");
            if ($check_sold && $check_sold->num_rows > 0) {
                throw new Exception('Sorry, this car has already been sold.');
            } else {
                throw new Exception('Sorry, this car is no longer available for purchase.');
            }
        }

        $car = $result->fetch_assoc();
        $response['debug']['car_data'] = $car;

        // Prevent users from buying their own cars
        if ($car['user_id'] == $user_id) {
            throw new Exception('You cannot purchase your own car listing.');
        }

        try {
            // Insert purchase record
            $stmt = $conn->prepare("
                INSERT INTO purchases (car_id, buyer_id, seller_id, price, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("iiid", $car_id, $user_id, $car['user_id'], $car['price']);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to process purchase: ' . $stmt->error);
            }

            $purchase_id = $conn->insert_id;
            $response['debug']['purchase_id'] = $purchase_id;

            // Mark car as sold
            $stmt = $conn->prepare("UPDATE cars SET is_sold = 1, sold_at = NOW() WHERE car_id = ?");
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $car_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to update car status: ' . $stmt->error);
            }

            // Insert buyer information
            $stmt = $conn->prepare("
                INSERT INTO purchase_details 
                (purchase_id, full_name, email, phone, address, payment_method, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("isssss", $purchase_id, $name, $email, $phone, $address, $payment_method);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to save purchase details: ' . $stmt->error);
            }

            // Commit transaction
            $conn->commit();
            $response['debug']['transaction_committed'] = true;

            // Send email notification (you'll need to implement this function)
            // sendPurchaseConfirmationEmail($email, $name, $car_id, $purchase_id);
            $response['success'] = true;
            $response['message'] = 'Your purchase has been processed successfully! Our team will contact you shortly.';
            $response['purchase_id'] = $purchase_id;

    } catch (Exception $e) {
        // Handle any exceptions
        $response['message'] = $e->getMessage();
        $response['error'] = [
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
        
        // Rollback transaction if it was started
        if (isset($response['debug']['transaction_started']) && $response['debug']['transaction_started'] === true) {
            $conn->rollback();
            $response['debug']['transaction_rolled_back'] = true;
        }
        
        // Log the error for debugging
        error_log('Purchase Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    }

    // Add database error if any
    if ($conn->error) {
        $response['database_error'] = $conn->error;
    }

    // Output the response
    header('Content-Type: application/json');
    echo json_encode($response);
?>
