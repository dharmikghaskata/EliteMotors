<?php
require_once 'includes/header.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$success = '';
$error = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['car_id'])) {
    $car_id = (int)$_POST['car_id'];
    
    if (isset($_POST['approve'])) {
        // Approve the car
        $stmt = $conn->prepare("UPDATE cars SET is_approved = 1, status = 'active' WHERE car_id = ?");
        if ($stmt->bind_param("i", $car_id) && $stmt->execute()) {
            $success = 'Car approved successfully';
            
            // Get car details for notification
            $car = $conn->query("SELECT * FROM cars WHERE car_id = $car_id")->fetch_assoc();
            if ($car) {
                $title = "Car Approved: {$car['year']} {$car['make']} {$car['model']}";
                $message = "Your car listing has been approved and is now live on the website.";
                
                // Add notification
                $conn->query("INSERT INTO notifications (type, title, message, related_id, related_type) 
                             VALUES ('car_approved', '" . $conn->real_escape_string($title) . "', '" . 
                             $conn->real_escape_string($message) . "', $car_id, 'car')");
            }
            
            header('Location: pending_approval.php?approved=1');
            exit();
        } else {
            $error = 'Error approving car: ' . $conn->error;
        }
    } 
    elseif (isset($_POST['reject']) && !empty($_POST['reject_reason'])) {
        $reason = trim($_POST['reject_reason']);
        
        // Reject the car
        $stmt = $conn->prepare("UPDATE cars SET is_approved = 0, status = 'rejected', rejection_reason = ? WHERE car_id = ?");
        if ($stmt->bind_param("si", $reason, $car_id) && $stmt->execute()) {
            $success = 'Car has been rejected';
            
            // Get car details for notification
            $car = $conn->query("SELECT * FROM cars WHERE car_id = $car_id")->fetch_assoc();
            if ($car) {
                $title = "Car Rejected: {$car['year']} {$car['make']} {$car['model']}";
                $message = "Your car listing has been rejected. Reason: " . htmlspecialchars($reason);
                
                // Add notification
                $conn->query("INSERT INTO notifications (type, title, message, related_id, related_type) 
                             VALUES ('car_rejected', '" . $conn->real_escape_string($title) . "', '" . 
                             $conn->real_escape_string($message) . "', $car_id, 'car')");
            }
            
            header('Location: pending_approval.php?rejected=1');
            exit();
        } else {
            $error = 'Error rejecting car: ' . $conn->error;
        }
    }
}

// Get car details
$car = null;
if (!empty($_GET['id'])) {
    $car_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT c.*, u.username, u.email, u.phone 
                           FROM cars c 
                           LEFT JOIN users u ON c.user_id = u.user_id 
                           WHERE c.car_id = ?");
    
    if ($stmt->bind_param("i", $car_id) && $stmt->execute()) {
        $result = $stmt->get_result();
        $car = $result->fetch_assoc();
    }
    $stmt->close();
}

if (!$car) {
    header('Location: pending_approval.php');
    exit();
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Approve Car Listing</h1>
            <p class="text-muted mb-0">Review and approve or reject this car listing</p>
        </div>
        <a href="pending_approval.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Pending
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Car Details</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <img src="../<?php echo htmlspecialchars($car['image_path']); ?>" 
                                 class="img-fluid rounded mb-3" alt="Car Image">
                            
                            <h4><?php echo htmlspecialchars($car['year'] . ' ' . $car['make'] . ' ' . $car['model']); ?></h4>
                            <p class="h5 text-primary mb-4">₹<?php echo number_format($car['price']); ?></p>
                            
                            <div class="mb-3">
                                <h5>Description</h5>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($car['description'])); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">Specifications</h6>
                                </div>
                                <div class="card-body">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-5">Mileage</dt>
                                        <dd class="col-sm-7"><?php echo number_format($car['mileage']); ?> km</dd>
                                        
                                        <dt class="col-sm-5">Transmission</dt>
                                        <dd class="col-sm-7"><?php echo ucfirst(htmlspecialchars($car['transmission'])); ?></dd>
                                        
                                        <dt class="col-sm-5">Fuel Type</dt>
                                        <dd class="col-sm-7"><?php echo ucfirst(htmlspecialchars($car['fuel_type'])); ?></dd>
                                        
                                        <dt class="col-sm-5">Engine Size</dt>
                                        <dd class="col-sm-7"><?php echo htmlspecialchars($car['engine_size']); ?>L</dd>
                                        
                                        <dt class="col-sm-5">Color</dt>
                                        <dd class="col-sm-7"><?php echo ucfirst(htmlspecialchars($car['color'])); ?></dd>
                                        
                                        <dt class="col-sm-5">Listed On</dt>
                                        <dd class="col-sm-7"><?php echo date('M j, Y', strtotime($car['created_at'])); ?></dd>
                                    </dl>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Seller Information</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($car['seller_name'] ?? 'N/A'); ?></p>
                                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($car['seller_email'] ?? 'N/A'); ?></p>
                                    <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($car['seller_phone'] ?? 'N/A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <form method="post" class="d-flex justify-content-between">
                        <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
                        
                        <div class="d-flex align-items-center">
                            <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                <i class="bi bi-x-circle me-1"></i> Reject
                            </button>
                            <button type="submit" name="approve" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i> Approve
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Car Listing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
                    
                    <div class="mb-3">
                        <label for="reject_reason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="reject_reason" name="reject_reason" rows="3" required></textarea>
                        <div class="form-text">Please provide a reason for rejecting this listing.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject" class="btn btn-danger">Reject Listing</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
