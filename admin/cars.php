<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['car_id'])) {
        $car_id = (int)$_POST['car_id'];
        
        // Get car details before deletion
        $car = $conn->query("SELECT make, model, year FROM cars WHERE car_id = $car_id")->fetch_assoc();
        
        // Delete car
        $delete = $conn->prepare("DELETE FROM cars WHERE car_id = ?");
        if ($delete->bind_param("i", $car_id) && $delete->execute()) {
            // Log the deletion
            $description = "Car deleted: {$car['year']} {$car['make']} {$car['model']} (ID: $car_id)";
            $conn->query("INSERT INTO admin_activity (admin_id, activity_type, description, ip_address) 
                         VALUES (1, 'car_deleted', '$description', '$_SERVER[REMOTE_ADDR]')");
        } else {
            $error = 'Error deleting car. Please try again.';
        }
        $delete->close();
    }
}

// Get all cars (both approved and pending)
$result = $conn->query("SELECT c.*, u.username, u.email, u.phone 
                      FROM cars c 
                      LEFT JOIN users u ON c.user_id = u.user_id 
                      ORDER BY c.is_approved ASC, c.created_at DESC");
$cars = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get counts for the dashboard
$stats = [
    'total' => 0,
    'approved' => 0,
    'pending' => 0,
    'sold' => 0,
    'featured' => 0
];

$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_approved = 1 THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN is_approved = 0 THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN is_sold = 1 THEN 1 ELSE 0 END) as sold,
        0 as featured
    FROM cars
");

if ($stats_result && $row = $stats_result->fetch_assoc()) {
    $stats = array_merge($stats, $row);
    $stats_result->free();
}

// Get pending approval count for the badge
$pending_count = 0;
$pending_result = $conn->query("SELECT COUNT(*) as count FROM cars WHERE is_approved = 0");
if ($pending_result) {
    $pending_count = $pending_result->fetch_assoc()['count'];
    $pending_result->free();
}

?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Cars</h1>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Cars</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-car fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Approved</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['approved']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Approval</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['pending']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">All Cars</h1>
            <p class="text-muted mb-0">Manage all car listings in the system</p>
        </div>
        <div>
            <a href="pending_approval.php" class="btn btn-warning">
                <i class="bi bi-hourglass-split me-1"></i> Pending Approval
                <?php if ($pending_count > 0): ?>
                    <span class="badge bg-danger"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Your car has been listed successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row">
        <?php if (!empty($cars)): ?>
            <?php foreach ($cars as $car): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">
                                <?php echo htmlspecialchars($car['year'] . ' ' . $car['make'] . ' ' . $car['model']); ?>
                            </h5>
                            <p class="card-text">
                                <strong>Price:</strong> ₹<?php echo number_format($car['price']); ?><br>
                                <strong>Mileage:</strong> <?php echo number_format($car['mileage']); ?> km
                            </p>
                            <div class="d-flex justify-content-between">
                                <a href="car_edit.php?id=<?php echo $car['car_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this car?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="car_id" value="<?php echo $car['car_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No cars listed yet.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
