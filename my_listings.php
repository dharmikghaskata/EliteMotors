<?php
session_start();
require_once 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_url'] = 'my_listings.php';
    header('Location: login.php');
    exit();
}

$page_title = 'My Listings | Elite Motors';
$user_id = $_SESSION['user_id'];
$success = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);

$error = '';
$listings = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'pending' => 0,
    'sold' => 0,
    'rejected' => 0,
    'views' => 0,
    'inquiries' => 0
];

// First, ensure the status column exists
$conn->query("ALTER TABLE cars ADD COLUMN IF NOT EXISTS status ENUM('pending', 'active', 'sold', 'rejected') DEFAULT 'pending'");

// Get user's car listings
$sql = "SELECT 
            c.*, 
            COALESCE(c.status, 'pending') as status,
            0 AS inquiry_count,
            c.image_path AS primary_image
        FROM cars c 
        WHERE c.user_id = ? 
        ORDER BY c.created_at DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $listings = $result->fetch_all(MYSQLI_ASSOC);
        
        // Calculate stats
        $stats['total'] = count($listings);
        foreach ($listings as $listing) {
            $status = $listing['is_sold'] ? 'sold' : 'active';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
            $stats['inquiries'] += $listing['inquiry_count'];
        }
    } else {
        $error = 'Error fetching your listings. Please try again later.';
    }
    
    $stmt->close();
} else {
    $error = 'Database error. Please try again later.';
}

// Status update and delete functionality has been removed as per requirements

include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h2 mb-0">My Listings</h1>
                <a href="sell_car.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i> List a New Car
                </a>
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
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-0">Total Listings</h6>
                                    <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                                </div>
                                <i class="bi bi-car-front fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-uppercase mb-0">Active</h6>
                                    <h2 class="mb-0"><?php echo $stats['active']; ?></h2>
                                </div>
                                <i class="bi bi-check-circle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Listings Table -->
            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($listings)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-car-front fs-1 text-muted mb-3"></i>
                            <h3>No Listings Found</h3>
                            <p class="text-muted mb-4">You haven't listed any cars for sale yet.</p>
                            <a href="sell_car.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i> List Your First Car
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Car</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Inquiries</th>
                                        <th>Listed On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($listings as $listing): 
                                        $status_class = [
                                            'panding' => 'bg-warning text-dark',
                                            'approved' => 'bg-success text-white',
                                            'active' => 'bg-success text-white',
                                            'sold' => 'bg-primary text-white',
                                            'rejected' => 'bg-danger text-white'
                                        ][strtolower($listing['status'])] ?? 'bg-secondary text-white';
                                        
                                        $formatted_price = '₹' . number_format($listing['price']);
                                        $formatted_date = date('M j, Y', strtotime($listing['created_at']));
                                        $car_title = $listing['year'] . ' ' . $listing['make'] . ' ' . $listing['model'];
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0 me-3">
                                                        <?php if ($listing['primary_image']): ?>
                                                            <img src="/elitemotors/uploads/cars/<?php echo htmlspecialchars($listing['primary_image']); ?>" 
                                                                 class="rounded" alt="Car" style="width: 80px; height: 60px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                                 style="width: 80px; height: 60px;">
                                                                <i class="bi bi-car-front text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($car_title); ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($listing['mileage'] . ' miles'); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo $formatted_price; ?></strong>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="badge <?php echo $status_class; ?> text-uppercase me-2">
                                                        <?php 
                                                        $displayStatus = $listing['status'];
                                                        if ($displayStatus === 'panding') $displayStatus = 'Pending';
                                                        if ($displayStatus === 'approved') $displayStatus = 'Active';
                                                        echo $displayStatus; 
                                                        ?>
                                                    </span>
                                                    <?php if (strtolower($listing['status']) === 'panding' && isset($_SESSION['admin_id'])): ?>
                                                        <form method="post" action="update_status.php" class="d-inline">
                                                            <input type="hidden" name="car_id" value="<?php echo $listing['car_id']; ?>">
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this listing?')">
                                                                <i class="bi bi-check-lg"></i> Approve
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($listing['inquiry_count'] > 0): ?>
                                                    <a href="inquiries.php?car_id=<?php echo $listing['car_id']; ?>" class="text-primary">
                                                        <?php echo $listing['inquiry_count']; ?> 
                                                        <?php echo $listing['inquiry_count'] === 1 ? 'Inquiry' : 'Inquiries'; ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">0 Inquiries</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $formatted_date; ?></td>
                                            <td>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" id="dropdownMenuButton<?php echo $listing['car_id']; ?>" 
                                                            data-bs-toggle="dropdown" aria-expanded="false">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?php echo $listing['car_id']; ?>">
                                                        <li><a class="dropdown-item" href="car_details.php?id=<?php echo $listing['car_id']; ?>"><i class="bi bi-eye me-2"></i>View</a></li>
                                                        <li><a class="dropdown-item" href="admin/car_edit.php?id=<?php echo $listing['car_id']; ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
                            <div class="text-muted">
                                Showing <span class="fw-bold">1</span> to <span class="fw-bold"><?php echo count($listings); ?></span> of 
                                <span class="fw-bold"><?php echo count($listings); ?></span> entries
                            </div>
                            <nav>
                                <ul class="pagination mb-0">
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                                    </li>
                                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                                    <li class="page-item">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
