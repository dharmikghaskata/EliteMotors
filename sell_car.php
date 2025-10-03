<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and include database connection
session_start();
require_once 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'sell_car.php';
    $_SESSION['error'] = 'Please login to list your car for sale.';
    header('Location: login.php');
    exit;
}

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Page configuration
$page_title = 'Sell Your Car | Elite Motors';
$success = '';
$error = '';
// Initialize form data
$form_data = [
    'make' => '',
    'model' => '',
    'year' => '',
    'mileage' => '',
    'engine_size' => '',
    'transmission' => '',
    'fuel_type' => '',
    'color' => '',
    'description' => '',
    'phone' => '',
    'owner_email' => '',
    'price' => ''
];

// File upload configuration
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/EliteMotors/uploads/cars/';
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_file_size = 5 * 1024 * 1024; // 5MB

// Create uploads directory if it doesn't exist
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0777, true)) {
        die('Failed to create upload directory. Please check permissions.');
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'sell_car.php';
    header('Location: login.php');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate form data
    $form_data = [
        'make' => trim($_POST['make'] ?? ''),
        'model' => trim($_POST['model'] ?? ''),
        'year' => trim($_POST['year'] ?? ''),
        'mileage' => isset($_POST['mileage']) ? intval(str_replace(',', '', $_POST['mileage'])) : 0,
        'engine_size' => isset($_POST['engine_size']) ? floatval($_POST['engine_size']) : 0.0,
        'transmission' => $_POST['transmission'] ?? '',
        'fuel_type' => $_POST['fuel_type'] ?? '',
        'color' => trim($_POST['color'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'owner_email' => trim($_POST['owner_email'] ?? ''),
        'price' => isset($_POST['price']) ? str_replace(',', '', trim($_POST['price'])) : '0'
    ];

    $image_path = '';
    $target_file = '';

    // Validate required fields
    $required_fields = ['make', 'model', 'year', 'price', 'transmission', 'fuel_type', 'color', 'phone', 'owner_email'];
    foreach ($required_fields as $field) {
        if (empty($form_data[$field])) {
            $error = "Please fill in all required fields.";
            break;
        }
    }

    // Validate email
    if (empty($error) && !filter_var($form_data['owner_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    }

    // Process file upload
    if (empty($error) && isset($_FILES['car_image']) && $_FILES['car_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['car_image'];
        $file_type = mime_content_type($file['tmp_name']);
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $file_name = uniqid('car_', true) . '.' . $file_extension;
        $target_file = $upload_dir . $file_name;

        // Validate file type
        if (!in_array($file_type, $allowed_types)) {
            $error = 'Only JPG, PNG, GIF, and WebP files are allowed.';
        }
        // Validate file size
        elseif ($file['size'] > $max_file_size) {
            $error = 'File is too large. Maximum size is 5MB.';
        }
        // Move uploaded file
        elseif (!move_uploaded_file($file['tmp_name'], $target_file)) {
            $error = 'Failed to upload file. Please try again.';
        } else {
            $image_path = 'uploads/cars/' . $file_name;
        }
    } elseif (empty($error) && (!isset($_FILES['car_image']) || $_FILES['car_image']['error'] === UPLOAD_ERR_NO_FILE)) {
        $error = 'Please upload a car photo.';
    } elseif (empty($error)) {
        $error = 'File upload error. Please try again.';
    }

    // If no errors, proceed with database operations
    if (empty($error) && !empty($image_path)) {
        $conn->begin_transaction();
        
        try {
            // Check if user has too many pending listings
            $stmt = $conn->prepare("SELECT COUNT(*) as listing_count FROM cars WHERE user_id = ? AND status = 'pending'");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row && $row['listing_count'] >= 5) {
                throw new Exception('You have reached the maximum number of pending listings (5). Please wait for your current listings to be approved.');
            }
            
            // Get user information
            $user_stmt = $conn->prepare("SELECT username, email, phone FROM users WHERE user_id = ?");
            $user_stmt->bind_param("i", $_SESSION['user_id']);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_info = $user_result->fetch_assoc();
            $user_stmt->close();

            // Insert car details
            $sql = "INSERT INTO cars (
                make, model, year, price, mileage, transmission, 
                fuel_type, engine_size, color, description, image_path, 
                user_id, is_approved, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";

            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Failed to prepare statement: ' . $conn->error);
            }

            // Create variables for binding to avoid reference issues
            $make = $form_data['make'];
            $model = $form_data['model'];
            $year = $form_data['year'];
            $mileage = $form_data['mileage'];
            $engine_size = $form_data['engine_size'];
            $transmission = $form_data['transmission'];
            $fuel_type = $form_data['fuel_type'];
            $color = $form_data['color'];
            $description = $form_data['description'];
            $price = $form_data['price'];
            $user_id = $_SESSION['user_id'];
            $phone = $form_data['phone'];
            $seller_name = $user_info['username'] ?? '';
            $seller_email = $form_data['owner_email'];

            $stmt->bind_param(
                'ssidsdssdssi',
                $make,
                $model,
                $year,
                $price,
                $mileage,
                $transmission,
                $fuel_type,
                $engine_size,
                $color,
                $description,
                $image_path,
                $user_id
            );

            if (!$stmt->execute()) {
                throw new Exception('Failed to execute statement: ' . $stmt->error);
            }
            
            $car_id = $conn->insert_id;
            
            // Add notification for admin
            $notification_message = "A new car ({$form_data['year']} {$form_data['make']} {$form_data['model']}) has been listed for ".number_format($form_data['price'])."";
            
            // Check if notifications table exists
            $notifications_table = $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0;
            if ($notifications_table) {
                try {
                    // Try the simplest possible insert first
                    $conn->query("INSERT INTO notifications (type, message) VALUES 
                                ('new_car', '".$conn->real_escape_string($notification_message)."')");
                } catch (Exception $e) {
                    // If simple insert fails, try to determine the table structure
                    try {
                        $columns = [];
                        $result = $conn->query("SHOW COLUMNS FROM notifications");
                        while($row = $result->fetch_assoc()) {
                            $columns[] = $row['Field'];
                        }
                        
                        $fields = ['type', 'message'];
                        $values = ["'new_car'", "'" . $conn->real_escape_string($notification_message) . "'"];
                        
                        // Add optional fields if they exist
                        if (in_array('title', $columns)) {
                            $fields[] = 'title';
                            $values[] = "'New Car Listing'";
                        }
                        
                        if (in_array('related_id', $columns) && in_array('related_type', $columns)) {
                            $fields[] = 'related_id';
                            $fields[] = 'related_type';
                            $values[] = $car_id;
                            $values[] = "'car'";
                        }
                        
                        $sql = "INSERT INTO notifications (" . implode(', ', $fields) . ") 
                                VALUES (" . implode(', ', $values) . ")";
                        $conn->query($sql);
                        
                    } catch (Exception $e) {
                        // Log the error but don't stop the process
                        error_log("Failed to add notification: " . $e->getMessage());
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear form data
            $form_data = array_fill_keys(array_keys($form_data), '');
            
            // Set success flag for JavaScript
            $show_success_popup = true;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            
            // Delete uploaded file if there was an error
            if (!empty($image_path) && file_exists($target_file)) {
                @unlink($target_file);
            }
            
            // Set error message
            $error = 'An error occurred while processing your request: ' . $e->getMessage();
        }
    }
}

include 'includes/header.php';
?>
<!-- Add SweetAlert2 CSS and JS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Validation styles */
    .required-field::after {
        content: ' *';
        color: #dc3545;
    }
    .is-invalid {
        border-color: #dc3545 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    .is-valid {
        border-color: #198754 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
        background-repeat: no-repeat;
        background-position: right calc(0.375em + 0.1875rem) center;
        background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
    }
    .invalid-feedback {
        display: none;
        width: 100%;
        margin-top: 0.25rem;
        font-size: 0.875em;
        color: #dc3545;
    }
    .is-invalid ~ .invalid-feedback {
        display: block;
    }
    .form-text {
        font-size: 0.875em;
        color: #6c757d;
    }
</style>

<div class="container my-5" style="min-height: 80vh;">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <h1 class="mb-4 text-center">Sell Your Car</h1>
            
            <?php if (!empty($error)): ?>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: '<?php echo addslashes($error); ?>',
                        confirmButtonColor: '#0d6efd',
                    });
                });
                </script>
            <?php endif; ?>
            
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Car Details</h5>
                </div>
                <div class="card-body">
                    <form id="sellCarForm" method="post" action="sell_car.php" enctype="multipart/form-data" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="make" class="form-label">Make <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="make" name="make" 
                                       value="<?php echo htmlspecialchars($form_data['make']); ?>"
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="model" name="model" 
                                       value="<?php echo htmlspecialchars($form_data['model']); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="year" name="year" 
                                       min="1900" max="<?php echo date('Y') + 1; ?>" 
                                       value="<?php echo htmlspecialchars($form_data['year']); ?>"
                                       required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="mileage" class="form-label">Mileage (km)</label>
                                <input type="text" class="form-control" id="mileage" name="mileage" 
                                       value="<?php echo htmlspecialchars($form_data['mileage']); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="engine_size" class="form-label">Engine Size (L)</label>
                                <input type="number" step="0.1" class="form-control" id="engine_size" 
                                       name="engine_size" value="<?php echo htmlspecialchars($form_data['engine_size']); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="transmission" class="form-label">Transmission <span class="text-danger">*</span></label>
                                <select class="form-select" id="transmission" name="transmission" required>
                                    <option value="" disabled selected>Select transmission</option>
                                    <option value="Automatic" <?php echo $form_data['transmission'] === 'Automatic' ? 'selected' : ''; ?>>Automatic</option>
                                    <option value="Manual" <?php echo $form_data['transmission'] === 'Manual' ? 'selected' : ''; ?>>Manual</option>
                                    <option value="Semi-Automatic" <?php echo $form_data['transmission'] === 'Semi-Automatic' ? 'selected' : ''; ?>>Semi-Automatic</option>
                                    <option value="CVT" <?php echo $form_data['transmission'] === 'CVT' ? 'selected' : ''; ?>>CVT</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fuel_type" class="form-label">Fuel Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="fuel_type" name="fuel_type" required>
                                    <option value="" disabled selected>Select fuel type</option>
                                    <option value="Petrol" <?php echo $form_data['fuel_type'] === 'Petrol' ? 'selected' : ''; ?>>Petrol</option>
                                    <option value="Diesel" <?php echo $form_data['fuel_type'] === 'Diesel' ? 'selected' : ''; ?>>Diesel</option>
                                    <option value="Electric" <?php echo $form_data['fuel_type'] === 'Electric' ? 'selected' : ''; ?>>Electric</option>
                                    <option value="Hybrid" <?php echo $form_data['fuel_type'] === 'Hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                    <option value="LPG" <?php echo $form_data['fuel_type'] === 'LPG' ? 'selected' : ''; ?>>LPG</option>
                                    <option value="CNG" <?php echo $form_data['fuel_type'] === 'CNG' ? 'selected' : ''; ?>>CNG</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="color" class="form-label">Color <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="color" name="color" 
                                       value="<?php echo htmlspecialchars($form_data['color']); ?>"
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price (₹) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="price" name="price" 
                                       value="<?php echo htmlspecialchars($form_data['price']); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      rows="3"><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($form_data['phone']); ?>"
                                       required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="owner_email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="owner_email" name="owner_email" 
                                       value="<?php echo htmlspecialchars($form_data['owner_email']); ?>"
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="car_image" class="form-label">Car Photo <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="car_image" name="car_image" accept="image/*" required>
                            <div class="form-text">Max file size: 5MB. Allowed formats: JPG, PNG, GIF, WebP</div>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">List My Car</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (isset($show_success_popup) && $show_success_popup): ?>
<script>
// Show success popup when the page loads
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: 'Success!',
        text: 'Your car has been listed successfully!',
        icon: 'success',
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false,
        customClass: {
            confirmButton: 'btn btn-primary'
        },
        buttonsStyling: false
    }).then((result) => {
        // Reset the form after user clicks OK
        document.getElementById('sellCarForm').reset();
    });
});
</script>
<?php endif; ?>

<!-- Image Preview Modal -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Image Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imagePreview" src="#" alt="Preview" class="img-fluid" style="max-height: 70vh;">
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Format price input with commas
    const priceInput = document.getElementById('price');
    if (priceInput) {
        priceInput.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            this.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        });
    }
    
    // Format mileage input with commas
    const mileageInput = document.getElementById('mileage');
    if (mileageInput) {
        mileageInput.addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            this.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        });
    }

    // Image preview
    const imageInput = document.getElementById('car_image');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreviewModal.show();
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // Simple form validation with alert messages
    const sellCarForm = document.getElementById('sellCarForm');
    
    // Simple alert message
    function showMessage(message) {
        alert(message);
    }
    
    // Form submission
    if (sellCarForm) {
        sellCarForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Required fields
            const requiredFields = [
                { id: 'make', name: 'Make' },
                { id: 'model', name: 'Model' },
                { id: 'year', name: 'Year' },
                { id: 'price', name: 'Price' },
                { id: 'mileage', name: 'Mileage' },
                { id: 'color', name: 'Color' },
                { id: 'transmission', name: 'Transmission' },
                { id: 'fuel_type', name: 'Fuel Type' },
                { id: 'owner_name', name: 'Owner Name' },
                { id: 'owner_email', name: 'Email Address' },
                { id: 'owner_phone', name: 'Phone Number' },
                { id: 'address', name: 'Address' },
                { id: 'description', name: 'Description' }
            ];
            
            // Check required fields
            for (const field of requiredFields) {
                const fieldElement = document.getElementById(field.id);
                if (fieldElement && !fieldElement.value.trim()) {
                    showMessage(field.name + ' is required');
                    fieldElement.focus();
                    return false;
                }
            }
            
            // Email validation
            const emailField = document.getElementById('owner_email');
            if (emailField && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim())) {
                showMessage('Please enter a valid email address');
                emailField.focus();
                return false;
            }
            
            // Phone number validation (basic)
            const phoneField = document.getElementById('owner_phone');
            if (phoneField && phoneField.value.trim() && !/^[0-9\-\+\(\)\s]{10,20}$/.test(phoneField.value.trim())) {
                showMessage('Please enter a valid phone number');
                phoneField.focus();
                return false;
            }
            
            // Year validation
            const yearField = document.getElementById('year');
            if (yearField) {
                const currentYear = new Date().getFullYear();
                const year = parseInt(yearField.value);
                if (isNaN(year) || year < 1900 || year > currentYear + 1) {
                    showMessage('Please enter a valid year between 1900 and ' + (currentYear + 1));
                    yearField.focus();
                    return false;
                }
            }
            
            // Price validation
            if (priceInput && (!priceInput.value.trim() || isNaN(parseFloat(priceInput.value.replace(/,/g, ''))))) {
                showMessage('Please enter a valid price');
                priceInput.focus();
                return false;
            }
            
            // Mileage validation
            if (mileageInput && (!mileageInput.value.trim() || isNaN(parseInt(mileageInput.value.replace(/,/g, ''))))) {
                showMessage('Please enter a valid mileage');
                mileageInput.focus();
                return false;
            }
            
            // Image validation
            if (imageInput && imageInput.files.length === 0) {
                showMessage('Please upload a photo of the car');
                return false;
            }
            
            // If all validations pass, submit the form
            sellCarForm.submit();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
