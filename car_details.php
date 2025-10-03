<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/permissions.php';

$page_title = 'Car Details';
$car = null;
$related_cars = [];
$error = '';

// Check if car ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error = 'Invalid car ID.';
} else {
    $car_id = (int)$_GET['id'];
    
    // Get car details with additional permission info
    $sql = "SELECT c.*, u.first_name, u.last_name, u.phone, u.email, 
                   u2.user_id AS current_user_id
            FROM cars c 
            LEFT JOIN users u ON c.user_id = u.user_id 
            LEFT JOIN users u2 ON u2.user_id = ?
            WHERE c.car_id = ? AND c.is_approved = 1";
    
    if ($stmt = $conn->prepare($sql)) {
        $current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $stmt->bind_param("ii", $current_user_id, $car_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $car = $result->fetch_assoc();
            
            // If car is not approved and user is not an admin, show error
            if ($car['is_approved'] != 1 && !(isset($_SESSION['admin_id']) || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $car['user_id']))) {
                $error = 'This car listing is not available.';
                $car = null;
            } else {
                $page_title = $car['make'] . ' ' . $car['model'] . ' ' . $car['year'] . ' | ' . $page_title;
            }
            
            // Check if is_sold column exists
            $check_sold = $conn->query("SHOW COLUMNS FROM cars LIKE 'is_sold'");
            $sold_condition = ($check_sold && $check_sold->num_rows > 0) ? "AND is_sold = 0" : "";
            
            // Get related cars (same make, different model, not sold if column exists, and approved)
            $related_sql = "SELECT * FROM cars 
                           WHERE make = ? AND car_id != ? AND is_approved = 1 $sold_condition
                           ORDER BY created_at DESC LIMIT 3";
            
            if ($related_stmt = $conn->prepare($related_sql)) {
                $related_stmt->bind_param("si", $car['make'], $car_id);
                $related_stmt->execute();
                $related_result = $related_stmt->get_result();
                
                while ($row = $related_result->fetch_assoc()) {
                    $related_cars[] = $row;
                }
                $related_stmt->close();
            }
            
            // Check if views column exists before trying to update it
            $check_views = $conn->query("SHOW COLUMNS FROM cars LIKE 'views'");
            if ($check_views && $check_views->num_rows > 0) {
                // Increment view count (for analytics)
                $update_views_sql = "UPDATE cars SET views = views + 1 WHERE car_id = ?";
                if ($update_views_stmt = $conn->prepare($update_views_sql)) {
                    $update_views_stmt->bind_param("i", $car_id);
                    $update_views_stmt->execute();
                    $update_views_stmt->close();
                }
            }
        } else {
            $error = 'Car not found.';
        }
        $stmt->close();
    } else {
        $error = 'Database error. Please try again later.';
    }
}

// Handle contact form submission
$form_success = '';
$form_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $message = trim($_POST['message']);
    
    // Basic validation
    if (empty($name) || empty($email) || empty($message)) {
        $form_error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_error = 'Please enter a valid email address.';
    } else {
        // Save message to database
        $insert_sql = "INSERT INTO inquiries (car_id, name, email, phone, message, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
        
        if ($insert_stmt = $conn->prepare($insert_sql)) {
            $insert_stmt->bind_param("issss", $car_id, $name, $email, $phone, $message);
            
            if ($insert_stmt->execute()) {
                $form_success = 'Your message has been sent. We\'ll get back to you soon!';
                
                // Clear form
                $_POST = [];
                
                // Send email notification (you'll need to configure your mail server)
                $to = $car['email'] ?? 'admin@example.com';
                $subject = "New Inquiry About Your {$car['make']} {$car['model']}";
                $email_message = "You have received a new inquiry from the Elite Motors website:\n\n";
                $email_message .= "Car: {$car['year']} {$car['make']} {$car['model']}\n";
                $email_message .= "Name: $name\n";
                $email_message .= "Email: $email\n";
                $email_message .= "Phone: " . ($phone ?: 'Not provided') . "\n\n";
                $email_message .= "Message:\n$message\n\n";
                $email_message .= "View the car: http://" . $_SERVER['HTTP_HOST'] . "/elitemotors/car_details.php?id=$car_id\n";
                
                $headers = "From: no-reply@elitemotors.com\r\n";
                $headers .= "Reply-To: $email\r\n";
                
                // Uncomment to enable email sending (requires mail server setup)
                // mail($to, $subject, $email_message, $headers);
                
                // Alternatively, log the email for debugging
                error_log("Email to $to: $subject\n$email_message");
            } else {
                $form_error = 'Error sending your message. Please try again.';
            }
            $insert_stmt->close();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php if ($error): ?>
    <div class="container mt-5">
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <div class="text-center mt-3">
            <a href="/elitemotors/buy_car.php" class="btn btn-primary">Browse Available Cars</a>
        </div>
    </div>
<?php else: ?>
    <!-- Main Car Details -->
    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/elitemotors/">Home</a></li>
                <li class="breadcrumb-item"><a href="/elitemotors/buy_car.php">Cars for Sale</a></li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php echo htmlspecialchars($car['year'] . ' ' . $car['make'] . ' ' . $car['model']); ?>
                </li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Car Gallery -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <?php if (!empty($car['image_path'])): ?>
                            <!-- Main Image -->
                            <div class="position-relative">
                                <?php
                                // Image path handling with base URL
                                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                                $basePath = '/EliteMotors';
                                $defaultImage = $baseUrl . $basePath . '/assets/img/no-image.jpg';
                                $mainImagePath = $defaultImage;
                                
                                if (!empty($car['image_path'])) {
                                    // Get just the filename
                                    $filename = basename($car['image_path']);
                                    
                                    // Define possible paths to check
                                    $possiblePaths = [
                                        'uploads/cars/' . $filename,
                                        'uploads/' . $filename,
                                        $filename
                                    ];
                                    
                                    // Check each possible path
                                    foreach ($possiblePaths as $testPath) {
                                        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $basePath . '/' . ltrim($testPath, '/');
                                        
                                        if (file_exists($fullPath) && is_readable($fullPath)) {
                                            // Use absolute URL for the image
                                            $mainImagePath = $baseUrl . $basePath . '/' . ltrim($testPath, '/');
                                            break;
                                        }
                                    }
                                    
                                    // If still using default image, log the error
                                    if ($mainImagePath === $defaultImage) {
                                        error_log('Image not found in car details: ' . $car['image_path']);
                                        error_log('Tried paths: ' . print_r($possiblePaths, true));
                                    }
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($mainImagePath); ?>" 
                                     class="img-fluid w-100" alt="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>"
                                     style="height: 500px; object-fit: cover;" id="mainImage"
                                     onerror="this.onerror=null; this.src='assets/img/no-image.jpg'">
                                
                                <?php if (isset($car['is_featured']) && $car['is_featured']): ?>
                                    <span class="position-absolute top-0 start-0 m-3 badge bg-warning text-dark">
                                        <i class="bi bi-star-fill"></i> Featured
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($car['is_sold']): ?>
                                    <div class="position-absolute top-50 start-50 translate-middle">
                                        <span class="badge bg-danger p-3 fs-4">SOLD</span>
                                    </div>
                                <?php endif; ?>
                                
                            </div>
                            
                            <!-- Thumbnails (if multiple images available) -->
                            <div class="d-flex p-3 border-top">
                                <div class="thumbnail me-2 border rounded overflow-hidden" style="width: 80px; height: 60px;">
                                    <img src="<?php echo htmlspecialchars($mainImagePath); ?>" 
                                         class="w-100 h-100" style="object-fit: cover; cursor: pointer;"
                                         onclick="document.getElementById('mainImage').src = this.src"
                                         onerror="this.onerror=null; this.src='assets/img/no-image.jpg'">
                                </div>
                                <!-- Add more thumbnails here if you have multiple images -->
                                <div class="thumbnail me-2 border rounded overflow-hidden" style="width: 80px; height: 60px; background-color: #f8f9fa;">
                                    <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted">
                                        <i class="bi bi-plus-lg"></i>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-5 bg-light">
                                <i class="bi bi-car-front fs-1 text-muted"></i>
                                <p class="mt-3 text-muted">No image available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Car Details Tabs -->
                <div class="card mb-4">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="carDetailsTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" 
                                        data-bs-target="#overview" type="button" role="tab" aria-controls="overview" 
                                        aria-selected="true">
                                    Overview
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="specs-tab" data-bs-toggle="tab" 
                                        data-bs-target="#specs" type="button" role="tab" aria-controls="specs" 
                                        aria-selected="false">
                                    Specs
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="carDetailsTabsContent">
                            <!-- Overview Tab -->
                            <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                                <h4 class="mb-3">Vehicle Overview</h4>
                                <?php if (!empty($car['description'])): ?>
                                    <p class="mb-4"><?php echo nl2br(htmlspecialchars($car['description'])); ?></p>
                                <?php else: ?>
                                    <p class="text-muted mb-4">No description available for this vehicle.</p>
                                <?php endif; ?>
                                
                                <h5 class="mb-3">Vehicle History</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="bi bi-calendar-check text-primary me-2"></i>
                                                <strong>Year:</strong> <?php echo $car['year']; ?>
                                            </li>
                                            <li class="mb-2">
                                                <i class="bi bi-speedometer2 text-primary me-2"></i>
                                                <strong>Mileage:</strong> <?php echo number_format($car['mileage']); ?> miles
                                            </li>
                                            <li class="mb-2">
                                                <i class="bi bi-fuel-pump text-primary me-2"></i>
                                                <strong>Fuel Type:</strong> <?php echo $car['fuel_type'] ?? 'N/A'; ?>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li class="mb-2">
                                                <i class="bi bi-gear text-primary me-2"></i>
                                                <strong>Transmission:</strong> <?php echo $car['transmission'] ?? 'N/A'; ?>
                                            </li>
                                            <li class="mb-2">
                                                <i class="bi bi-palette text-primary me-2"></i>
                                                <strong>Color:</strong> <?php echo $car['color'] ?? 'N/A'; ?>
                                            </li>
                                            <li class="mb-2">
                                                <i class="bi bi-eye text-primary me-2"></i>
                                                <strong>Views:</strong> <?php echo number_format($car['views'] ?? 0); ?>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Specifications Tab -->
                            <div class="tab-pane fade" id="specs" role="tabpanel" aria-labelledby="specs-tab">
                                <h4 class="mb-3">Technical Specifications</h4>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <th style="width: 40%;">Make</th>
                                                <td><?php echo htmlspecialchars($car['make']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Model</th>
                                                <td><?php echo htmlspecialchars($car['model']); ?></td>
                                            </tr>
                                            <tr>
                                                <th>Year</th>
                                                <td><?php echo $car['year']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Mileage</th>
                                                <td><?php echo number_format($car['mileage']); ?> miles</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Similar Vehicles -->
                <?php if (!empty($related_cars)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Similar Vehicles</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php foreach ($related_cars as $related_car): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="position-relative">
                                                <?php if (!empty($related_car['image_path'])): ?>
                                                    <img src="/elitemotors/uploads/<?php echo htmlspecialchars($related_car['image_path']); ?>" 
                                                         class="card-img-top" alt="<?php echo htmlspecialchars($related_car['make'] . ' ' . $related_car['model']); ?>"
                                                         style="height: 180px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="bg-light d-flex align-items-center justify-content-center" 
                                                         style="height: 180px;">
                                                        <i class="bi bi-car-front fs-1 text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($related_car['is_featured']): ?>
                                                    <span class="position-absolute top-0 start-0 m-2 badge bg-warning text-dark">
                                                        <i class="bi bi-star-fill"></i> Featured
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="card-body">
                                                <h6 class="card-title">
                                                    <a href="car_details.php?id=<?php echo $related_car['car_id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($related_car['year'] . ' ' . $related_car['make'] . ' ' . $related_car['model']); ?>
                                                    </a>
                                                </h6>
                                                <p class="card-text text-muted small mb-2">
                                                    <i class="bi bi-speedometer2 me-1"></i> <?php echo number_format($related_car['mileage']); ?> mi
                                                </p>
                                                <h5 class="text-primary mb-0">₹<?php echo number_format($related_car['price']); ?></h5>
                                            </div>
                                            <div class="card-footer bg-transparent border-top-0">
                                                <a href="car_details.php?id=<?php echo $related_car['car_id']; ?>" class="btn btn-sm btn-outline-primary w-100">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Price & Contact Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h2 class="text-primary mb-0">₹<?php echo number_format($car['price']); ?></h2>
                        </div>
                        
                        <?php if ($car['is_sold']): ?>
                            <div class="alert alert-danger text-center">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <strong>This vehicle has been sold.</strong>
                            </div>
                            <a href="buy_car.php" class="btn btn-primary w-100 mb-3">
                                <i class="bi bi-car-front me-2"></i> View Similar Vehicles
                            </a>
                        <?php else: ?>
                            <!-- Action Buttons -->
                            <div class="d-grid gap-2 mb-4">
                                <?php if (isset($_SESSION['user_id']) && $car['user_id'] == $_SESSION['user_id']): ?>
                                    <!-- Owner Actions -->
                                    <a href="admin/car_edit.php?id=<?php echo $car['car_id']; ?>" class="btn btn-outline-primary mb-2">
                                        <i class="bi bi-pencil me-2"></i> Edit Listing
                                    </a>
                                    <?php if ($car['is_sold']): ?>
                                        <span class="btn btn-success mb-2">
                                            <i class="bi bi-check-circle me-2"></i> Marked as Sold
                                        </span>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-success mb-2" data-bs-toggle="modal" data-bs-target="#markAsSoldModal">
                                            <i class="bi bi-check-circle me-2"></i> Mark as Sold
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Contact Seller Button (for non-owners) -->
                                <?php if (!isset($_SESSION['user_id']) || $car['user_id'] != $_SESSION['user_id']): ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#contactSellerModal">
                                        <i class="bi bi-envelope me-2"></i> Contact Seller
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Contact Seller Modal -->
    <div class="modal fade" id="contactSellerModal" tabindex="-1" aria-labelledby="contactSellerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactSellerModalLabel">
                        Contact Seller - <?php echo htmlspecialchars($car['year'] . ' ' . $car['make'] . ' ' . $car['model']); ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="contactForm" action="" method="post" onsubmit="return validateContactForm()">
                    <div class="modal-body">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Your Name</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Your Message</label>
                            <textarea class="form-control" id="message" name="message" rows="4"><?php 
                                echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : 
                                "Hello, I'm interested in the {$car['year']} {$car['make']} {$car['model']} (ID: {$car['car_id']}). Please provide more details."; 
                            ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i> Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Contact form validation
function validateContactForm() {
    const form = document.getElementById('contactForm');
    const name = form.name.value.trim();
    const email = form.email.value.trim();
    const phone = form.phone.value.trim();
    const message = form.message.value.trim();
    
    // Name validation
    if (!name) {
        alert('Please enter your name');
        form.name.focus();
        return false;
    }
    
    // Email validation
    if (!email) {
        alert('Please enter your email address');
        form.email.focus();
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address\nExample: yourname@example.com');
        form.email.focus();
        form.email.select();
        return false;
    }
    
    // Phone validation (if provided)
    if (phone) {
        const phoneRegex = /^[0-9+\-\s()]{10,15}$/;
        if (!phoneRegex.test(phone)) {
            alert('Please enter a valid phone number\nExample: 1234567890 or +1234567890');
            form.phone.focus();
            form.phone.select();
            return false;
        }
    }
    
    // Message validation
    if (!message) {
        alert('Please enter your message');
        form.message.focus();
        return false;
    }
    
    alert('Thank you!\nYour message has been sent successfully.');
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    // Image gallery
    const mainImage = document.getElementById('mainImage');
    const thumbnails = document.querySelectorAll('.thumbnail');
    
    thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            mainImage.src = this.src;
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>

<?php if (canDeleteCar()): ?>
<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this car listing? This action cannot be undone.</p>
                <p class="fw-bold"><?php echo htmlspecialchars($car['year'] . ' ' . $car['make'] . ' ' . $car['model']); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="post" action="admin/cars.php" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Mark as Sold Modal -->
<div class="modal fade" id="markAsSoldModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Mark as Sold</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="update_car_status.php">
                <div class="modal-body">
                    <p>Are you sure you want to mark this car as sold? This will remove it from the active listings.</p>
                    <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
                    <input type="hidden" name="status" value="sold">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle me-1"></i> Mark as Sold
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
