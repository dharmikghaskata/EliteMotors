<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/includes/db_connect.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$success = '';
$error = '';

// Handle approval/rejection before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && !empty($_POST['car_id'])) {
        $car_id = (int)$_POST['car_id'];
        
        if ($_POST['action'] === 'approve') {
            // First check if status column exists
            $status_column_exists = $conn->query("SHOW COLUMNS FROM cars LIKE 'status'")->num_rows > 0;
            
            // Build the query based on whether status column exists
            $query = "UPDATE cars SET is_approved = 1";
            if ($status_column_exists) {
                $query .= ", status = 'active'";
            }
            $query .= " WHERE car_id = ?";
            
            // Approve car
            $stmt = $conn->prepare($query);
            if ($stmt->bind_param("i", $car_id) && $stmt->execute()) {
                $success = 'Car approved successfully';
                
                // Add notification
                $car = $conn->query("SELECT * FROM cars WHERE car_id = $car_id")->fetch_assoc();
                $title = "Car Approved: {$car['year']} {$car['make']} {$car['model']}";
                $message = "Your {$car['year']} {$car['make']} {$car['model']} has been approved and is now live on the website.";
                
                // Check if notifications table exists
                $notifications_table = $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0;
                if ($notifications_table) {
                    try {
                        // Try the simplest possible insert first
                        $conn->query("INSERT INTO notifications (type, message) VALUES ('car_approved', '" . $conn->real_escape_string($message) . "')");
                    } catch (Exception $e) {
                        // If simple insert fails, try to determine the table structure
                        try {
                            $columns = [];
                            $result = $conn->query("SHOW COLUMNS FROM notifications");
                            while($row = $result->fetch_assoc()) {
                                $columns[] = $row['Field'];
                            }
                            
                            $fields = ['type', 'message'];
                            $values = ["'car_approved'", "'" . $conn->real_escape_string($message) . "'"];
                            
                            // Add optional fields if they exist
                            if (in_array('title', $columns)) {
                                $fields[] = 'title';
                                $values[] = "'" . $conn->real_escape_string($title) . "'";
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
                
                // Redirect to avoid form resubmission
                header('Location: pending_approval.php?approved=' . $car_id);
                exit();
            } else {
                $error = 'Error approving car: ' . $conn->error;
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'reject') {
            // Reject car
            $reason = trim($_POST['reject_reason'] ?? '');
            if (empty($reason)) {
                $error = 'Please provide a reason for rejection';
            } else {
                // First check if status column exists
                $status_column_exists = $conn->query("SHOW COLUMNS FROM cars LIKE 'status'")->num_rows > 0;
                $rejection_column_exists = $conn->query("SHOW COLUMNS FROM cars LIKE 'rejection_reason'")->num_rows > 0;
                
                // Build the query based on which columns exist
                $query = "UPDATE cars SET ";
                $params = [];
                $types = "";
                
                if ($status_column_exists) {
                    $query .= "status = 'rejected'";
                } else {
                    $query .= "is_approved = 0"; // Keep it unapproved
                }
                
                if ($rejection_column_exists) {
                    $query .= ", rejection_reason = ?";
                    $params[] = $reason;
                    $types .= "s";
                }
                
                $query .= " WHERE car_id = ?";
                $params[] = $car_id;
                $types .= "i";
                
                $stmt = $conn->prepare($query);
                
                // Bind parameters dynamically
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                
                if ($stmt->execute()) {
                    $success = 'Car has been rejected';
                    
                    // Add notification
                    $car = $conn->query("SELECT * FROM cars WHERE car_id = $car_id")->fetch_assoc();
                    $title = "Car Rejected: {$car['year']} {$car['make']} {$car['model']}";
                    $message = "Your {$car['year']} {$car['make']} {$car['model']} has been rejected. Reason: $reason";
                    
                    // Check if notifications table exists
                    $notifications_table = $conn->query("SHOW TABLES LIKE 'notifications'")->num_rows > 0;
                    if ($notifications_table) {
                        try {
                            // Try the simplest possible insert first
                            $conn->query("INSERT INTO notifications (type, message) VALUES ('car_rejected', '" . $conn->real_escape_string($message) . "')");
                        } catch (Exception $e) {
                            // If simple insert fails, try to determine the table structure
                            try {
                                $columns = [];
                                $result = $conn->query("SHOW COLUMNS FROM notifications");
                                while($row = $result->fetch_assoc()) {
                                    $columns[] = $row['Field'];
                                }
                                
                                $fields = ['type', 'message'];
                                $values = ["'car_rejected'", "'" . $conn->real_escape_string($message) . "'"];
                                
                                // Add optional fields if they exist
                                if (in_array('title', $columns)) {
                                    $fields[] = 'title';
                                    $values[] = "'" . $conn->real_escape_string($title) . "'";
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
                                error_log("Failed to add rejection notification: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // Redirect to avoid form resubmission
                    header('Location: pending_approval.php?rejected=' . $car_id);
                    exit();
                } else {
                    $error = 'Error rejecting car: ' . $conn->error;
                }
                $stmt->close();
            }
        } elseif ($_POST['action'] === 'delete') {
            // Delete car
            $delete = $conn->prepare("DELETE FROM cars WHERE car_id = ?");
            if ($delete->bind_param("i", $car_id) && $delete->execute()) {
                $success = 'Car deleted successfully';
                // Redirect to avoid form resubmission
                header('Location: pending_approval.php?deleted=1');
                exit();
            } else {
                $error = 'Error deleting car: ' . $conn->error;
            }
            $delete->close();
        }
    }
}

// Now include the header after all header modifications are done
require_once 'includes/header.php';

if (isset($_GET['approved'])) {
    $success = 'Car approved successfully';
    $success = 'Car has been deleted';
}

// Get pending cars for approval
$pending_cars = [];
$pending_query = "SELECT c.*, u.username, u.email, u.phone 
                FROM cars c 
                LEFT JOIN users u ON c.user_id = u.user_id 
                WHERE (c.is_approved = 0 OR c.status = 'pending' OR c.status IS NULL)
                AND (c.is_sold = 0 OR c.is_sold IS NULL)
                ORDER BY c.created_at DESC";
$pending_result = $conn->query($pending_query);
if ($pending_result) {
    $pending_cars = $pending_result->fetch_all(MYSQLI_ASSOC);
    $pending_result->free();
}

?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="text-muted mb-0">Review and approve new car listings</p>
        </div>
        <a href="cars.php" class="btn btn-primary">
            <i class="bi bi-arrow-left me-1"></i> Back to All Cars
        </a>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (empty($pending_cars)): ?>
        <div class="card shadow mb-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-check2-circle text-success" style="font-size: 3rem;"></i>
                <h4 class="mt-3">No pending approvals</h4>
                <p class="text-muted">All cars have been reviewed.</p>
                <a href="cars.php" class="btn btn-primary mt-2">
                    <i class="bi bi-arrow-left me-1"></i> Back to All Cars
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Car Details</th>
                                <th>Seller Info</th>
                                <th>Listed On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_cars as $car): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex">
                                            <?php if (!empty($car['image_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($car['image_path']); ?>" 
                                                     alt="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>" 
                                                     class="img-thumbnail me-3" style="width: 100px; height: 70px; object-fit: cover;">
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($car['year'] . ' ' . $car['make'] . ' ' . $car['model']); ?></h6>
                                                <p class="mb-1 text-muted">
                                                    <i class="bi bi-speedometer2 me-1"></i> 
                                                    <?php echo number_format($car['mileage'] ?? 0); ?> km
                                                </p>
                                                <p class="mb-0 text-primary fw-bold">₹<?php echo number_format($car['price']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($car['username'])): ?>
                                            <p class="mb-1"><?php echo htmlspecialchars($car['username']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($car['email'])): ?>
                                            <p class="mb-1 small text-muted">
                                                <i class="bi bi-envelope me-1"></i> 
                                                <?php echo htmlspecialchars($car['email']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($car['phone'])): ?>
                                            <p class="mb-0 small text-muted">
                                                <i class="bi bi-telephone me-1"></i> 
                                                <?php echo htmlspecialchars($car['phone']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-nowrap">
                                        <?php echo date('M j, Y', strtotime($car['created_at'])); ?>
                                        <br>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($car['created_at'])); ?></small>
                                    </td>
                                    <td class="text-nowrap">
                                        <div class="btn-group btn-group-sm">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success" title="Approve">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" 
                                                    data-bs-target="#rejectModal<?php echo $car['car_id']; ?>" title="Reject">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </button>
                                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal<?php echo $car['car_id']; ?>" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?php echo $car['car_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reject Car Listing</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form method="post">
                                                <div class="modal-body">
                                                    <p>Are you sure you want to reject this car listing?</p>
                                                    <div class="mb-3">
                                                        <label for="rejectReason<?php echo $car['car_id']; ?>" class="form-label">Reason for rejection:</label>
                                                        <textarea class="form-control" id="rejectReason<?php echo $car['car_id']; ?>" 
                                                                  name="reject_reason" rows="3" required></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Reject</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?php echo $car['car_id']; ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Car Listing</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this car listing? This action cannot be undone.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="post">
                                                    <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
