<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'errors' => []
];

try {
    // Include database connection
    require_once 'includes/db_connect.php';
    
    // Check if database connection was successful
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Set character set
    if (!$conn->set_charset('utf8mb4')) {
        throw new Exception('Error loading character set utf8mb4: ' . $conn->error);
    }
    
    // Set content type header
    header('Content-Type: application/json');
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get form data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? 'No Subject');
    $message = trim($_POST['message'] ?? '');

    // Validate inputs
    $errors = [];

    if (empty($name)) {
        $errors['name'] = 'Please enter your name';
    }

    if (empty($email)) {
        $errors['email'] = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }

    if (empty($message)) {
        $errors['message'] = 'Please enter your message';
    } elseif (strlen($message) < 10) {
        $errors['message'] = 'Message should be at least 10 characters long';
    }

    // If there are validation errors
    if (!empty($errors)) {
        $response['errors'] = $errors;
        throw new Exception('Please fix the errors in the form');
    }

    // Check if contact_messages table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'contact_messages'");
    if (!$table_check) {
        throw new Exception('Error checking for table: ' . $conn->error);
    }
    
    if ($table_check->num_rows == 0) {
        // Create the table with explicit error handling
        $create_table = "CREATE TABLE `contact_messages` (
            `message_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `subject` VARCHAR(255) DEFAULT NULL,
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($create_table)) {
            throw new Exception('Error creating table: ' . $conn->error);
        }
    }

    // Check if contact_messages table exists, if not create it
    $table_check = $conn->query("SHOW TABLES LIKE 'contact_messages'");
    if (!$table_check) {
        throw new Exception('Error checking for table: ' . $conn->error);
    }
    
    if ($table_check->num_rows == 0) {
        // Create the table with explicit error handling
        $create_table = "CREATE TABLE IF NOT EXISTS `contact_messages` (
            `message_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `phone` VARCHAR(20) DEFAULT NULL,
            `subject` VARCHAR(255) DEFAULT 'No Subject',
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_email` (`email`),
            INDEX `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($create_table)) {
            throw new Exception('Error creating table: ' . $conn->error);
        }
    }

    // Prepare the SQL statement
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }

    // Bind parameters and execute
    $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save your message. Please try again later.');
    }

    // Success response
    $response['success'] = true;
    $response['message'] = 'Your message has been sent successfully!';

} catch (Exception $e) {
    // Log detailed error information
    $error_message = 'Contact Form Error: ' . $e->getMessage() . "\n";
    $error_message .= 'File: ' . $e->getFile() . ' (Line: ' . $e->getLine() . ")\n";
    $error_message .= 'Request Data: ' . json_encode($_POST) . "\n";
    $error_message .= 'Backtrace: ' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) . "\n";
    
    // Log to error log
    error_log($error_message);
    
    // Set response
    $response['message'] = $e->getMessage();
    $response['debug'] = $error_message; // Only for debugging, remove in production
}

// Clear any previous output
ob_clean();

// Send the JSON response
echo json_encode($response);

// End output buffering and flush
ob_end_flush();
exit();?>

<!-- Add this to the end of the file to help with debugging -->
<?php if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'): ?>
<!-- This is an AJAX request, no HTML output needed -->
<?php else: ?>
{{ ... }}
<html>
<head>
    <title>Form Submission Result</title>
    <script>
        // If JavaScript is enabled, redirect back to contact page
        window.location.href = 'contact.php' + (window.location.search || '') + (window.location.hash || '');
    </script>
</head>
<body>
    <noscript>
        <h1>Form Submission Result</h1>
        <?php if ($response['success']): ?>
            <p style="color: green;"><?php echo htmlspecialchars($response['message']); ?></p>
            <p><a href="contact.php">Back to contact form</a></p>
        <?php else: ?>
            <p style="color: red;">Error: <?php echo htmlspecialchars($response['message']); ?></p>
            <p><a href="contact.php">Back to contact form</a></p>
        <?php endif; ?>
    </noscript>
</body>
</html>
<?php endif; ?>
