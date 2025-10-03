<?php
session_start();
require_once '../includes/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: /elitemotors/login.php');
    exit();
}

$page_title = 'Manage Cars';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the base query
$sql = "SELECT c.*, u.username as seller_username 
        FROM cars c 
        LEFT JOIN users u ON c.user_id = u.user_id 
        WHERE 1=1";
$params = [];
$types = '';

// Add status filter
if ($status === 'available') {
    $sql .= " AND c.is_sold = 0";
} elseif ($status === 'sold') {
    $sql .= " AND c.is_sold = 1";
}

// Add search filter
if (!empty($search)) {
    $sql .= " AND (c.make LIKE ? OR c.model LIKE ? OR c.description LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

// Add sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC' ? 'ASC' : 'DESC';
$sql .= " ORDER BY $sort $order";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get total count for stats
$totalCars = $result->num_rows;
$availableCars = 0;
$soldCars = 0;

// Count available and sold cars
$statsSql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_sold = 0 THEN 1 ELSE 0 END) as available,
    SUM(CASE WHEN is_sold = 1 THEN 1 ELSE 0 END) as sold
    FROM cars";
$statsResult = $conn->query($statsSql);
if ($statsRow = $statsResult->fetch_assoc()) {
    $totalCars = $statsRow['total'];
    $availableCars = $statsRow['available'];
    $soldCars = $statsRow['sold'];
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
                <h1 class="h2">Manage Cars</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="add_car.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle"></i> Add New Car
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Total Cars</h6>
                                    <h4 class="mb-0"><?php echo $totalCars; ?></h4>
                                </div>
                                <i class="bi bi-car-front fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Available</h6>
                                    <h4 class="mb-0"><?php echo $availableCars; ?></h4>
                                </div>
                                <i class="bi bi-check-circle fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">Sold</h6>
                                    <h4 class="mb-0"><?php echo $soldCars; ?></h4>
                                </div>
                                <i class="bi bi-currency-dollar fs-1 opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select name="status" id="status" class="form-select">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Cars</option>
                                <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="sold" <?php echo $status === 'sold' ? 'selected' : ''; ?>>Sold</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search by make, model, or description">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cars Table -->
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>
                                        <a href="?sort=make&order=<?php echo ($sort === 'make' && $order === 'ASC') ? 'DESC' : 'ASC'; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                            Make & Model
                                            <?php if ($sort === 'make'): ?>
                                                <i class="bi bi-caret-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>-fill"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Year</th>
                                    <th>
                                        <a href="?sort=price&order=<?php echo ($sort === 'price' && $order === 'ASC') ? 'DESC' : 'ASC'; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                            Price
                                            <?php if ($sort === 'price'): ?>
                                                <i class="bi bi-caret-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>-fill"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Mileage</th>
                                    <th>Status</th>
                                    <th>
                                        <a href="?sort=created_at&order=<?php echo ($sort === 'created_at' && $order === 'ASC') ? 'DESC' : 'ASC'; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                                            Added On
                                            <?php if ($sort === 'created_at'): ?>
                                                <i class="bi bi-caret-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>-fill"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($row['image_path'])): ?>
                                                        <img src="/elitemotors/uploads/<?php echo htmlspecialchars($row['image_path']); ?>" 
                                                             alt="<?php echo htmlspecialchars($row['make'] . ' ' . $row['model']); ?>"
                                                             class="rounded me-2" width="50" height="40" style="object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" 
                                                             style="width: 50px; height: 40px;">
                                                            <i class="bi bi-car-front text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-bold">
                                                            <?php echo htmlspecialchars($row['make'] . ' ' . $row['model']); ?>
                                                            <?php if ($row['is_featured']): ?>
                                                                <span class="badge bg-warning text-dark ms-1">Featured</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo $row['seller_username'] ? 'By: ' . htmlspecialchars($row['seller_username']) : 'By: Admin'; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['year']); ?></td>
                                            <td>$<?php echo number_format($row['price'], 2); ?></td>
                                            <td><?php echo number_format($row['mileage']); ?> mi</td>
                                            <td>
                                                <span class="badge bg-<?php echo $row['is_sold'] ? 'danger' : 'success'; ?> bg-opacity-10 text-<?php echo $row['is_sold'] ? 'danger' : 'success'; ?>">
                                                    <?php echo $row['is_sold'] ? 'Sold' : 'Available'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit_car.php?id=<?php echo $row['car_id']; ?>" 
                                                       class="btn btn-outline-primary" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="#" 
                                                       class="btn btn-outline-danger delete-car" 
                                                       data-id="<?php echo $row['car_id']; ?>"
                                                       data-bs-toggle="tooltip" 
                                                       title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                    <a href="/elitemotors/car_details.php?id=<?php echo $row['car_id']; ?>" 
                                                       class="btn btn-outline-secondary" 
                                                       target="_blank"
                                                       data-bs-toggle="tooltip" 
                                                       title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="bi bi-car-front fs-1 d-block mb-2"></i>
                                                No cars found matching your criteria.
                                            </div>
                                            <a href="?status=all" class="btn btn-sm btn-outline-primary mt-2">
                                                Show All Cars
                                            </a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this car? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Delete car functionality
    const deleteButtons = document.querySelectorAll('.delete-car');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    let carIdToDelete = null;

    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            carIdToDelete = this.getAttribute('data-id');
            deleteModal.show();
        });
    });

    document.getElementById('confirmDelete').addEventListener('click', function() {
        if (carIdToDelete) {
            // Send AJAX request to delete the car
            fetch(`delete_car.php?id=${carIdToDelete}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const toast = new bootstrap.Toast(document.getElementById('deleteToast'));
                    document.querySelector('.toast-body').textContent = 'Car deleted successfully.';
                    toast.show();
                    
                    // Remove the row from the table
                    const row = document.querySelector(`.delete-car[data-id="${carIdToDelete}"]`).closest('tr');
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                } else {
                    alert('Error deleting car: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting car. Please try again.');
            })
            .finally(() => {
                deleteModal.hide();
                carIdToDelete = null;
            });
        }
    });
});
</script>

<!-- Toast for success messages -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
    <div id="deleteToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
            <strong class="me-auto">Success</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            Car deleted successfully.
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
