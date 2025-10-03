<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$page_title = 'Add New Car';
$error = '';
$success = '';

// Define variables and initialize with empty values
$make = $model = $year = $price = $image_path = '';

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate make
    if (empty(trim($_POST["make"]))) {
        $error = "Please enter the car's make.";
    } else {
        $make = trim($_POST["make"]);
    }
    
    // Validate model
    if (empty(trim($_POST["model"]))) {
        $error = "Please enter the car's model.";
    } else {
        $model = trim($_POST["model"]);
    }
    
    // Validate year
    $current_year = date('Y');
    $year = (int)($_POST["year"] ?? 0);
    if ($year < 1990 || $year > ($current_year + 1)) {
        $error = "Please select a valid year between 1990 and " . ($current_year + 1);
    }
    
    // Validate price
    $price = (float)(str_replace([',', ' '], ['.', ''], $_POST["price"] ?? 0));
    if ($price <= 0) {
        $error = "Please enter a valid price.";
    }
    
    // Validate image
    if (!isset($_FILES["car_image"]) || $_FILES["car_image"]["error"] != 0) {
        $error = "Please select a car image.";
    }
    
    // Process image upload
    if (empty($error)) {
        $target_dir = "../uploads/cars/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $imageFileType = strtolower(pathinfo($_FILES["car_image"]["name"], PATHINFO_EXTENSION));
        $new_filename = 'car_' . uniqid() . '.' . $imageFileType;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is an actual image
        $check = getimagesize($_FILES["car_image"]["tmp_name"]);
        if ($check === false) {
            $error = "File is not an image.";
        }
        // Check file size (5MB max)
        elseif ($_FILES["car_image"]["size"] > 5000000) {
            $error = "Sorry, your file is too large. Maximum size is 5MB.";
        }
        // Allow certain file formats
        elseif (!in_array($imageFileType, ["jpg", "jpeg", "png", "gif"])) {
            $error = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        }
        // Try to upload file
        else {
            if (move_uploaded_file($_FILES["car_image"]["tmp_name"], $target_file)) {
                $image_path = $new_filename;
            } else {
                $error = "Sorry, there was an error uploading your file.";
            }
        }
    }
    
    // If no errors, insert into database
    if (empty($error)) {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Prepare the SQL statement with all necessary fields
            $stmt = $conn->prepare("INSERT INTO cars (
                user_id, 
                make, 
                model, 
                year, 
                price, 
                image_path, 
                is_approved, 
                status,
                is_sold,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, 'active', 0, NOW())");
            
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            // Bind parameters - only the ones that are being inserted
            $stmt->bind_param("ississ", 
                $_SESSION['admin_id'],
                $make, 
                $model, 
                $year, 
                $price,
                $image_path
            );
            
            // Execute the statement
            if (!$stmt->execute()) {
                throw new Exception("Error adding car: " . $stmt->error);
            }
            
            // Commit the transaction
            $conn->commit();
            
            // Set success message
            $success = "Car added successfully and is now visible to users!";
            
            // Clear form
            $make = $model = $year = $price = '';
            
        } catch (Exception $e) {
            // Rollback the transaction on error
            if (isset($conn)) {
                $conn->rollback();
            }
            $error = $e->getMessage();
        } finally {
            // Close the statement if it was created
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Add New Car</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="cars.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back to Cars
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <a href="cars.php" class="alert-link">View all cars</a>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Car Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="make" class="form-label">Make <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="make" name="make" value="<?php echo htmlspecialchars($make); ?>" required>
                                                <div class="invalid-feedback">Please enter car make (e.g., Toyota, Honda)</div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="model" class="form-label">Model <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($model); ?>" required>
                                                <div class="invalid-feedback">Please enter car model (e.g., Camry, Civic)</div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="year" class="form-label">Year <span class="text-danger">*</span></label>
                                                <select class="form-select" id="year" name="year" required>
                                                    <option value="">Select Year</option>
                                                    <?php 
                                                    $current_year = date('Y');
                                                    for ($y = $current_year + 1; $y >= 1990; $y--) {
                                                        $selected = ($year == $y) ? 'selected' : '';
                                                        echo "<option value='$y' $selected>$y</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <div class="invalid-feedback">Please select a year</div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <label for="price" class="form-label">Price (PKR) <span class="text-danger">*</span></label>
                                                <div class="input-group">
                                                    <span class="input-group-text">PKR</span>
                                                    <input type="number" class="form-control" id="price" name="price" min="0" step="1000" value="<?php echo htmlspecialchars($price); ?>" required>
                                                </div>
                                                <div class="invalid-feedback">Please enter a valid price</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Car Image</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="car_image" class="form-label">Upload Image <span class="text-danger">*</span></label>
                                            <input class="form-control" type="file" id="car_image" name="car_image" accept="image/*" required>
                                            <div class="form-text">Max file size: 5MB. Allowed formats: JPG, JPEG, PNG, GIF</div>
                                            <div class="invalid-feedback">Please upload a car image</div>
                                        </div>
                                        
                                        <div class="text-center">
                                            <img id="imagePreview" src="#" alt="Car Preview" class="img-fluid d-none" style="max-height: 200px; border: 1px solid #ddd; border-radius: 5px;">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-save me-2"></i> Add Car
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview
    const imageInput = document.getElementById('car_image');
    const imagePreview = document.getElementById('imagePreview');
    
    if (imageInput && imagePreview) {
        imageInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                // Check file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File is too large. Maximum size is 5MB.');
                    this.value = '';
                    imagePreview.src = '#';
                    imagePreview.classList.add('d-none');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.remove('d-none');
                }
                reader.readAsDataURL(file);
            } else {
                imagePreview.src = '#';
                imagePreview.classList.add('d-none');
            }
        });
    }
    
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php include '../includes/footer.php'; ?>
