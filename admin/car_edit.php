<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection first
require_once __DIR__ . '/includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
    exit();
}

// If it's an edit operation, verify ownership
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $car_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT user_id FROM cars WHERE car_id = ?");
    $stmt->bind_param("i", $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $car_data = $result->fetch_assoc();
        // If user is not admin and not the owner of the car
        if (!isset($_SESSION['admin_id']) && $car_data['user_id'] != $_SESSION['user_id']) {
            header('Location: ../unauthorized.php');
            exit();
        }
    } else {
        header('Location: ../404.php');
        exit();
    }
}

// Define upload directories
$upload_base_dir = dirname(dirname(__FILE__)) . '/uploads/';
$upload_cars_dir = $upload_base_dir . 'cars/';

// Ensure upload directories exist and are writable
function ensureWritableDirectory($dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0777, true)) {
            die("Error: Failed to create directory: $dir");
        }
    }
    if (!is_writable($dir)) {
        if (!chmod($dir, 0777)) {
            die("Error: Directory is not writable: $dir");
        }
    }
    return true;
}

// Ensure directories exist
ensureWritableDirectory($upload_base_dir);
ensureWritableDirectory($upload_cars_dir);

// Initialize variables
$car = [
    'make' => '',
    'model' => '',
    'year' => '',
    'price' => '',
    'mileage' => '',
    'transmission' => 'automatic',
    'fuel_type' => 'petrol',
    'description' => '',
    'user_id' => $_SESSION['admin_id']
];

$car_images = [];
$is_edit = false;
$car_id = 0;
$success = '';
$error = '';

// Check if we're editing an existing car
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $car_id = (int)$_GET['id'];
    $is_edit = true;
    
    // Fetch car details
    $stmt = $conn->prepare("SELECT * FROM cars WHERE car_id = ?");
    $stmt->bind_param('i', $car_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $car = $result->fetch_assoc();
        
        // Fetch car images
        $img_stmt = $conn->prepare("SELECT * FROM car_images WHERE car_id = ? ORDER BY is_primary DESC, image_id ASC");
        $img_stmt->bind_param('i', $car_id);
        $img_stmt->execute();
        $car_images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Car not found.";
        $is_edit = false;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $car = [
        'make' => trim($_POST['make'] ?? ''),
        'model' => trim($_POST['model'] ?? ''),
        'year' => (int)($_POST['year'] ?? 0),
        'price' => (float)str_replace([',', ' '], '', $_POST['price'] ?? '0'),
        'mileage' => !empty($_POST['mileage']) ? (int)str_replace([',', ' '], '', $_POST['mileage']) : null,
        'transmission' => $_POST['transmission'] ?? 'automatic',
        'fuel_type' => $_POST['fuel_type'] ?? 'petrol',
        'description' => trim($_POST['description'] ?? ''),
        'user_id' => (int)($_POST['user_id'] ?? $_SESSION['admin_id']),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_sold' => isset($_POST['is_sold']) ? 1 : 0
    ];
    
    // Validate form data
    $errors = [];
    
    if (empty($car['make'])) $errors[] = 'Make is required';
    if (empty($car['model'])) $errors[] = 'Model is required';
    if ($car['year'] < 1900 || $car['year'] > (date('Y') + 1)) $errors[] = 'Please select a valid year';
    if ($car['price'] <= 0) $errors[] = 'Price must be greater than 0';
    if (!empty($car['mileage']) && $car['mileage'] < 0) $errors[] = 'Mileage cannot be negative';
    
    // If no validation errors, proceed with save
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            if ($is_edit) {
                // Update existing car
                $stmt = $conn->prepare("UPDATE cars SET 
                    make = ?, model = ?, year = ?, price = ?, mileage = ?, 
                    transmission = ?, fuel_type = ?, description = ?, 
                    user_id = ?, is_sold = ?
                    WHERE car_id = ?");
                
                $stmt->bind_param('ssiissssiii', 
                    $car['make'], $car['model'], $car['year'], $car['price'], 
                    $car['mileage'], $car['transmission'], $car['fuel_type'], 
                    $car['description'], $car['user_id'], 
                    $car['is_sold'], $car_id
                );
            } else {
                // Insert new car
                $stmt = $conn->prepare("INSERT INTO cars (
                    make, model, year, price, mileage, transmission, 
                    fuel_type, description, user_id, is_sold
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->bind_param('ssiissssii', 
                    $car['make'], $car['model'], $car['year'], $car['price'], 
                    $car['mileage'], $car['transmission'], $car['fuel_type'], 
                    $car['description'], $car['user_id'], 
                    $car['is_sold']
                );
            }
            
            if ($stmt->execute()) {
                if (!$is_edit) {
                    $car_id = $conn->insert_id;
                }
                
                // Handle file uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $uploaded_files = [];
                    
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;
                        
                        $file_name = $_FILES['images']['name'][$key];
                        $file_tmp = $_FILES['images']['tmp_name'][$key];
                        $file_size = $_FILES['images']['size'][$key];
                        $file_type = $_FILES['images']['type'][$key];
                        
                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (!in_array($file_type, $allowed_types)) continue;
                        
                        // Validate file size (5MB max)
                        if ($file_size > 5 * 1024 * 1024) continue;
                        
                        // Generate unique filename
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $new_filename = uniqid('car_', true) . '.' . $file_ext;
                        $destination = $upload_cars_dir . $new_filename;
                        
                        // Move uploaded file
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $uploaded_files[] = [
                                'car_id' => $car_id,
                                'image_path' => $new_filename,
                                'is_primary' => (count($car_images) + count($uploaded_files) === 0) ? 1 : 0
                            ];
                        }
                    }
                    
                    // Save uploaded files to database
                    if (!empty($uploaded_files)) {
                        $img_stmt = $conn->prepare("INSERT INTO car_images (car_id, image_path, is_primary) VALUES (?, ?, ?)");
                        
                        foreach ($uploaded_files as $file) {
                            $img_stmt->bind_param('isi', $file['car_id'], $file['image_path'], $file['is_primary']);
                            $img_stmt->execute();
                            $image_id = $conn->insert_id;
                            
                            if ($file['is_primary']) {
                                // Update primary image flag for other images
                                $update_primary = $conn->prepare("UPDATE car_images SET is_primary = 0 WHERE car_id = ? AND image_id != ?");
                                $update_primary->bind_param('ii', $car_id, $image_id);
                                $update_primary->execute();
                            }
                        }
                    }
                }
                
                // Handle primary image change
                if (!empty($_POST['primary_image']) && $is_edit) {
                    $primary_image_id = (int)$_POST['primary_image'];
                    $update_primary = $conn->prepare("UPDATE car_images SET is_primary = IF(image_id = ?, 1, 0) WHERE car_id = ?");
                    $update_primary->bind_param('ii', $primary_image_id, $car_id);
                    $update_primary->execute();
                }
                
                // Handle image deletion
                if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                    $delete_stmt = $conn->prepare("DELETE FROM car_images WHERE image_id = ? AND car_id = ?");
                    
                    foreach ($_POST['delete_images'] as $image_id) {
                        $image_id = (int)$image_id;
                        // Get image path before deleting
                        $img_path = $conn->query("SELECT image_path FROM car_images WHERE image_id = $image_id")->fetch_assoc()['image_path'] ?? '';
                        
                        // Delete from database
                        $delete_stmt->bind_param('ii', $image_id, $car_id);
                        if ($delete_stmt->execute() && !empty($img_path)) {
                            // Delete file
                            @unlink($upload_cars_dir . $img_path);
                        }
                    }
                    
                    // If we deleted the primary image, set a new one
                    if ($conn->affected_rows > 0) {
                        $new_primary = $conn->query("SELECT image_id FROM car_images WHERE car_id = $car_id LIMIT 1")->fetch_assoc();
                        if ($new_primary) {
                            $conn->query("UPDATE car_images SET is_primary = 1 WHERE image_id = {$new_primary['image_id']}");
                        }
                    }
                }
                
                $conn->commit();
                $success = 'Car ' . ($is_edit ? 'updated' : 'added') . ' successfully!';
                
                // Refresh car data
                if ($is_edit) {
                    $stmt = $conn->prepare("SELECT * FROM cars WHERE car_id = ?");
                    $stmt->bind_param('i', $car_id);
                    $stmt->execute();
                    $car = $stmt->get_result()->fetch_assoc();
                    
                    // Refresh images
                    $img_stmt = $conn->prepare("SELECT * FROM car_images WHERE car_id = ? ORDER BY is_primary DESC, image_id ASC");
                    $img_stmt->bind_param('i', $car_id);
                    $img_stmt->execute();
                    $car_images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                } else {
                    // Redirect to edit page for new car
                    header("Location: car_edit.php?id=" . $car_id);
                    exit();
                }
            } else {
                throw new Exception('Error saving car: ' . $conn->error);
            }
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get users for owner dropdown
$users = [];
$users_result = $conn->query("SELECT user_id, username, first_name, last_name FROM users ORDER BY first_name, last_name");
if ($users_result) {
    $users = $users_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Car' : 'Add New Car'; ?> - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom styles -->
    <style>
        .required:after {
            content: ' *';
            color: #dc3545;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #dc3545;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        .image-preview {
            position: relative;
            margin-bottom: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            overflow: hidden;
        }
        .image-preview img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }
        .image-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            display: flex;
            gap: 0.5rem;
        }
        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        .spinner-border {
            width: 1rem;
            height: 1rem;
            border-width: 0.15em;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0"><?php echo $is_edit ? 'Edit Car' : 'Add New Car'; ?></h1>
                    <a href="cars.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Cars
                    </a>
                </div>
            </div>
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

        <form id="carForm" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Car Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="make" class="form-label required">Make</label>
                                        <input type="text" class="form-control" id="make" name="make" 
                                               value="<?php echo htmlspecialchars($car['make']); ?>" required>
                                        <div class="invalid-feedback">Please enter the car make.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="model" class="form-label required">Model</label>
                                        <input type="text" class="form-control" id="model" name="model" 
                                               value="<?php echo htmlspecialchars($car['model']); ?>" required>
                                        <div class="invalid-feedback">Please enter the car model.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="year" class="form-label required">Year</label>
                                        <select class="form-select" id="year" name="year" required>
                                            <option value="">Select Year</option>
                                            <?php 
                                            $current_year = date('Y');
                                            for ($y = $current_year + 1; $y >= 2000; $y--): 
                                            ?>
                                                <option value="<?php echo $y; ?>" <?php echo $car['year'] == $y ? 'selected' : ''; ?>>
                                                    <?php echo $y; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <div class="invalid-feedback">Please select a valid year.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="price" class="form-label required">Price (₹)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">₹</span>
                                            <input type="text" class="form-control" id="price" name="price" 
                                                   value="<?php echo !empty($car['price']) ? number_format($car['price']) : ''; ?>" 
                                                   required>
                                        </div>
                                        <div class="invalid-feedback">Please enter a valid price.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="mileage" class="form-label">Mileage (km)</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="mileage" name="mileage" 
                                                   value="<?php echo !empty($car['mileage']) ? number_format($car['mileage']) : ''; ?>">
                                            <span class="input-group-text">km</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transmission" class="form-label">Transmission</label>
                                        <select class="form-select" id="transmission" name="transmission">
                                            <option value="automatic" <?php echo ($car['transmission'] ?? '') === 'automatic' ? 'selected' : ''; ?>>Automatic</option>
                                            <option value="manual" <?php echo ($car['transmission'] ?? '') === 'manual' ? 'selected' : ''; ?>>Manual</option>
                                            <option value="semi-automatic" <?php echo ($car['transmission'] ?? '') === 'semi-automatic' ? 'selected' : ''; ?>>Semi-Automatic</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fuel_type" class="form-label">Fuel Type</label>
                                        <select class="form-select" id="fuel_type" name="fuel_type">
                                            <option value="petrol" <?php echo ($car['fuel_type'] ?? '') === 'petrol' ? 'selected' : ''; ?>>Petrol</option>
                                            <option value="diesel" <?php echo ($car['fuel_type'] ?? '') === 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                            <option value="electric" <?php echo ($car['fuel_type'] ?? '') === 'electric' ? 'selected' : ''; ?>>Electric</option>
                                            <option value="hybrid" <?php echo ($car['fuel_type'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                            <option value="cng" <?php echo ($car['fuel_type'] ?? '') === 'cng' ? 'selected' : ''; ?>>CNG</option>
                                            <option value="lpg" <?php echo ($car['fuel_type'] ?? '') === 'lpg' ? 'selected' : ''; ?>>LPG</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($car['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Owner & Status</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="user_id" class="form-label">Owner</label>
                                <select class="form-select" id="user_id" name="user_id" required>
                                    <option value="">Select Owner</option>
                                    <?php foreach ($users as $user): 
                                        $display_name = trim($user['first_name'] . ' ' . $user['last_name']);
                                        if (empty($display_name)) {
                                            $display_name = $user['username'];
                                        }
                                        $selected = (isset($car['user_id']) && $car['user_id'] == $user['user_id']) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $user['user_id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($display_name); ?> 
                                            (<?php echo htmlspecialchars($user['username']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" 
                                       <?php echo !empty($car['is_featured']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_featured">Featured</label>
                            </div>
                            
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_sold" name="is_sold" 
                                       <?php echo !empty($car['is_sold']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_sold">Mark as Sold</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Car Images</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="images" class="form-label">Upload Images</label>
                                <input class="form-control" type="file" id="images" name="images[]" multiple 
                                       accept="image/jpeg,image/png,image/webp" onchange="previewImages(this)">
                                <div class="form-text">You can select multiple images. First image will be set as primary.</div>
                            </div>
                            
                            <div id="imagePreviews" class="row g-2">
                                <?php if (!empty($car_images)): ?>
                                    <?php foreach ($car_images as $image): ?>
                                        <div class="col-6 col-md-4" id="image-<?php echo $image['image_id']; ?>">
                                            <div class="image-preview">
                                                <img src="/elitemotors/uploads/cars/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                     alt="Car Image" class="img-fluid">
                                                <div class="image-actions">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="primary_image" 
                                                               value="<?php echo $image['image_id']; ?>" 
                                                               id="primary_<?php echo $image['image_id']; ?>"
                                                               <?php echo !empty($image['is_primary']) ? 'checked' : ''; ?>>
                                                    </div>
                                                    <button type="button" class="btn btn-danger btn-sm btn-delete-image" 
                                                            data-id="<?php echo $image['image_id']; ?>"
                                                            title="Delete Image">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div id="newImagePreviews" class="row g-2 mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="fixed-bottom bg-white border-top py-3">
                <div class="container">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <a href="cars.php" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-1"></i> Cancel
                            </a>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save me-1"></i> <?php echo $is_edit ? 'Update Car' : 'Add Car'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Form validation
    (function () {
        'use strict'
        
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll('.needs-validation')
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                // Reset validation
                form.classList.remove('was-validated');
                
                // Check required fields
                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');
                let errorMessage = '';
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        const fieldName = field.previousElementSibling?.textContent || 'This field';
                        errorMessage += `${fieldName} is required\n`;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                // Check price format
                const priceField = document.getElementById('price');
                if (priceField && priceField.value) {
                    const price = parseFloat(priceField.value.replace(/[^0-9.]/g, ''));
                    if (isNaN(price) || price <= 0) {
                        isValid = false;
                        errorMessage += 'Please enter a valid price\n';
                        priceField.classList.add('is-invalid');
                    }
                }
                
                // Check year format
                const yearField = document.getElementById('year');
                if (yearField && yearField.value) {
                    const year = parseInt(yearField.value);
                    const currentYear = new Date().getFullYear();
                    if (isNaN(year) || year < 1900 || year > currentYear + 1) {
                        isValid = false;
                        errorMessage += 'Please enter a valid year (1900-' + (currentYear + 1) + ')\n';
                        yearField.classList.add('is-invalid');
                    }
                }
                
                if (!isValid) {
                    event.preventDefault();
                    event.stopPropagation();
                    alert('Validation Error\n' + errorMessage);
                    // Scroll to first error
                    const firstError = form.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                } else {
                    // Show loading state on submit button
                    const submitBtn = document.getElementById('submitBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = `
                            <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                            ${submitBtn.textContent.trim()}
                        `;
                    }
                }
                
                form.classList.add('was-validated');
            }, false);
        });
    })();
    
    // Format price input
    document.getElementById('price')?.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value) {
            value = parseInt(value).toLocaleString('en-IN');
        }
        this.value = value;
    });
    
    // Format mileage input
    document.getElementById('mileage')?.addEventListener('input', function(e) {
        let value = this.value.replace(/\D/g, '');
        if (value) {
            value = parseInt(value).toLocaleString('en-IN');
        }
        this.value = value;
    });
    
    // Image preview for new uploads
    function previewImages(input) {
        const previewContainer = document.getElementById('newImagePreviews');
        previewContainer.innerHTML = '';
        
        if (input.files && input.files.length > 0) {
            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const col = document.createElement('div');
                    col.className = 'col-6 col-md-4';
                    col.innerHTML = `
                        <div class="image-preview">
                            <img src="${e.target.result}" alt="Preview" class="img-fluid">
                            <div class="image-actions">
                                <button type="button" class="btn btn-danger btn-sm btn-remove-preview" 
                                        data-index="${i}" title="Remove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    previewContainer.appendChild(col);
                    
                    // Add event listener for remove button
                    col.querySelector('.btn-remove-preview').addEventListener('click', function() {
                        // Create a new DataTransfer object
                        const dataTransfer = new DataTransfer();
                        const input = document.getElementById('images');
                        
                        // Add all files except the one being removed
                        for (let j = 0; j < input.files.length; j++) {
                            if (j !== parseInt(this.dataset.index)) {
                                dataTransfer.items.add(input.files[j]);
                            }
                        }
                        
                        // Update files in the input
                        input.files = dataTransfer.files;
                        
                        // Refresh previews
                        previewImages(input);
                    });
                };
                
                reader.readAsDataURL(file);
            }
        }
    }
    
    // Handle delete image button clicks
    document.querySelectorAll('.btn-delete-image').forEach(btn => {
        btn.addEventListener('click', function() {
            const imageId = this.dataset.id;
            const imageContainer = document.getElementById(`image-${imageId}`);
            
            if (confirm('Delete Image\nAre you sure you want to delete this image?')) {
                // Create a hidden input to mark this image for deletion
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_images[]';
                input.value = imageId;
                document.getElementById('carForm').appendChild(input);
                
                // Remove the image preview
                imageContainer.remove();
            }
        });
    });
    
    // Set primary image
    document.querySelectorAll('input[name="primary_image"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                // Update UI to show which image is primary
                document.querySelectorAll('.image-preview').forEach(preview => {
                    preview.classList.remove('border-primary', 'border-2');
                });
                this.closest('.image-preview').classList.add('border-primary', 'border-2');
            }
        });
    });
    </script>
</body>
</html>
<?php
// Close database connection
if (isset($conn)) {
    $conn->close();
}
?>
