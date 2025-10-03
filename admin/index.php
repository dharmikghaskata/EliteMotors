<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Get counts for dashboard
$stats = [
    'total_cars' => 0,
    'available_cars' => 0,
    'sold_cars' => 0,
    'total_users' => 0,
    'total_admins' => 0,
    'recent_activity' => [],
    'unread_messages' => 0
];

// Get car stats
$sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_sold = 0 THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN is_sold = 1 THEN 1 ELSE 0 END) as sold
    FROM cars";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_cars'] = $row['total'] ?? 0;
    $stats['available_cars'] = $row['available'] ?? 0;
    $stats['sold_cars'] = $row['sold'] ?? 0;
}

// Get user count
$sql = "SELECT COUNT(*) as count FROM users";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_users'] = $row['count'] ?? 0;
}

// Get admin count
$sql = "SELECT COUNT(*) as count FROM admins";
$result = $admin_db->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['total_admins'] = $row['count'] ?? 0;
}

// Get recent activity - only show delete actions
$stats['recent_activity'] = [];
try {
    // Check if admin_activity table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'admin_activity'");
    if ($table_check && $table_check->num_rows > 0) {
        // Only fetch delete actions
        $sql = "SELECT * FROM admin_activity 
                WHERE activity_type IN ('user_deleted', 'car_deleted')
                ORDER BY created_at DESC 
                LIMIT 5";
                
        $recent_activity = $conn->query($sql);
        if ($recent_activity) {
            $stats['recent_activity'] = $recent_activity->fetch_all(MYSQLI_ASSOC);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching recent activity: " . $e->getMessage());
}

// Get unread messages
$sql = "SELECT COUNT(*) as count FROM contact_messages WHERE is_read = 0";
$result = $conn->query($sql);
if ($result && $row = $result->fetch_assoc()) {
    $stats['unread_messages'] = $row['count'] ?? 0;
}
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Total Cars Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Cars</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_cars']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-car-front fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Cars Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Available Cars</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['available_cars']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Users Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $stats['total_users']; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Recent Activity -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuLink">
                            <li><a class="dropdown-item" href="#">View All</a></li>
                            <li><a class="dropdown-item" href="#">Mark as Read</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="activity-feed">
                        <?php if (!empty($stats['recent_activity'])): ?>
                            <?php foreach ($stats['recent_activity'] as $activity): ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">
                                            <i class="bi bi-trash me-1"></i>
                                            <?php echo htmlspecialchars($activity['description']); ?>
                                        </span>
                                        <small class="text-muted">
                                            <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="activity_logs.php" class="btn btn-sm btn-outline-primary">View All Activities</a>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center my-3">No recent activity</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <a href="cars.php" class="btn btn-primary w-100 py-3">
                                <i class="bi bi-eye me-2"></i>View Cars
                            </a>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="users.php" class="btn btn-success w-100 py-3">
                                <i class="bi bi-people me-2"></i>View Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
