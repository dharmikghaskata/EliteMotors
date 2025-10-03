<?php
session_start();
require_once 'includes/db_connect.php';

// Set page title
$page_title = 'Buy Cars | Elite Motors';

// Include header
include 'includes/header.php';
?>

<!-- Main Content Wrapper -->
<div class="wrapper d-flex flex-column min-vh-100">
    <div class="main-content flex-grow-1">
<?php
// Get search parameters
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = 1; // Simple view, no pagination needed
$per_page = 12; // Show more cars per page

// Get filter parameters
$make = isset($_GET['make']) ? trim($conn->real_escape_string($_GET['make'])) : '';

// Build the base query - only show approved, active and available cars
$query = "SELECT * FROM cars WHERE is_sold = 0 AND is_approved = 1 AND status = 'active'";
$count_sql = "SELECT COUNT(*) as total FROM cars WHERE is_sold = 0 AND is_approved = 1 AND status = 'active'";

$params = [];
$types = '';

// Apply make filter if specified
if (!empty($make) && $make !== 'all') {
    $query .= " AND make = ?";
    $count_sql .= " AND make = ?";
    $params[] = $make;
    $types = 's';
}

// Apply search query if specified
if (!empty($search_query)) {
    $query .= " AND (make LIKE ? OR model LIKE ? OR description LIKE ?)";
    $count_sql .= " AND (make LIKE ? OR model LIKE ? OR description LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= str_repeat('s', 3);
}

// Default sorting by newest
$query .= " ORDER BY created_at DESC";

// Get total count for pagination
$total_cars = 0;
if ($stmt = $conn->prepare($count_sql)) {
    if (!empty($params)) {
        $bind_names = [];
        $bind_names[] = $types;
        
        // Create references to the parameters
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        
        // Call bind_param with the parameters
        call_user_func_array(array($stmt, 'bind_param'), $bind_names);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $total_cars = $row ? $row['total'] : 0;
    }
    $stmt->close();
}

// Get cars for current page
$offset = ($page - 1) * $per_page;
$query .= " LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

// Execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $bind_names = [];
    $bind_names[] = $types;
    
    // Create references to the parameters
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    
    // Call bind_param with the parameters
    call_user_func_array(array($stmt, 'bind_param'), $bind_names);
}

$stmt->execute();
$result = $stmt->get_result();
$cars = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get distinct makes for filter dropdown
$makes_result = $conn->query("SELECT DISTINCT make FROM cars WHERE is_approved = 1 AND make != '' ORDER BY make");
$makes = [];
while ($row = $makes_result->fetch_assoc()) {
    $makes[] = $row['make'];
}
?>

<!-- Close the container from header -->
    <div class="container">
        <div class="card mb-3">
            <div class="card-body p-3">
                <form action="" method="get" class="row g-3">
                    <div class="col-md-6">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by make, model or description..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                <div class="col-md-4">
                    <select name="make" class="form-select">
                        <option value="">All Makes</option>
                        <?php foreach ($makes as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>" 
                                <?php echo ($make === $m) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($m); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Results count -->
    <div class="mb-3">
        <h4 class="mb-0">
            <?php echo $total_cars; ?> Cars Found
            <?php if (!empty($search_query)): ?>
                <small class="text-muted">for "<?php echo htmlspecialchars($search_query); ?>"</small>
            <?php endif; ?>
        </h4>
    </div>

    <!-- Car Listings -->
    <div class="row">

            <?php 
if (empty($cars)) {
    echo '<div class="alert alert-info">No cars found matching your criteria. Please try different filters.</div>';
} else {
    echo '<div class="row" style="margin: 0 -10px;">';
    
    foreach ($cars as $car) {
        // Set default image path
        $default_image = 'assets/img/no-image.jpg';
        $image_path = $default_image;
        
        // Debug: Log the original image path
        error_log("Original image path: " . ($car['image_path'] ?? 'empty'));
        
        if (!empty($car['image_path'])) {
            // Clean up the path
            $clean_path = ltrim(str_replace('\\', '/', $car['image_path']), '/');
            
            // Try different possible locations
            $possible_paths = [
                $clean_path,  // Original path
                'uploads/' . basename($clean_path),  // Just the filename in uploads
                'uploads/cars/' . basename($clean_path),  // In uploads/cars/
                str_replace('uploads/', '', $clean_path),  // Remove uploads/ if it's duplicated
                str_replace('EliteMotors/', '', $clean_path),  // Remove EliteMotors/ if present
                'uploads/' . basename($clean_path)  // Just the filename using basename() instead
            ];
            
            // Add debug logging
            error_log("Trying paths: " . print_r($possible_paths, true));
            
            // Check each possible path
            foreach ($possible_paths as $path) {
                $path = ltrim($path, '/');
                $full_path = $_SERVER['DOCUMENT_ROOT'] . '/EliteMotors/' . $path;
                if (file_exists($full_path)) {
                    $image_path = $path;
                    error_log("Found image at: " . $full_path);
                    break;
                } else {
                    error_log("Not found: " . $full_path);
                }
            }
            
            // If still not found, try to find any image in uploads directory
            if ($image_path === $default_image) {
                $uploads_dir = $_SERVER['DOCUMENT_ROOT'] . '/EliteMotors/uploads/';
                if (is_dir($uploads_dir)) {
                    $files = glob($uploads_dir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
                    if (!empty($files)) {
                        $image_path = 'uploads/' . basename($files[0]);
                        error_log("Using fallback image: " . $image_path);
                    }
                }
            }
        }
        
        // Format price with Lakhs or Crores
        $formatted_price = '';
        $price = $car['price'];
        if ($price >= 10000000) {
            $formatted_price = '₹' . number_format($price / 10000000, 2) . ' Cr';
        } else if ($price >= 100000) {
            $formatted_price = '₹' . number_format($price / 100000, 2) . ' L';
        } else {
            $formatted_price = '₹' . number_format($price);
        }
        
        // Generate the car card HTML
        echo '
        <div class="col-12 col-sm-6 col-lg-4 mb-4" style="padding: 0 10px; margin-bottom: 20px;">
            <div class="card h-100" style="border-radius: 10px; border: 1px solid #eee; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.3s ease;">
                <div class="position-relative">
                    <div class="car-image-container" style="height: 200px; overflow: hidden; position: relative; background: #f8f9fa; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <img src="' . htmlspecialchars($image_path) . '" 
                             class="car-image w-100 h-100" 
                             style="max-width: 100%; max-height: 100%; object-fit: contain; transition: transform 0.3s ease;"
                             alt="' . htmlspecialchars($car['make'] . ' ' . $car['model']) . '"
                             onerror="this.onerror=null; this.src=\'assets/img/no-image.jpg\';">
                        ' . ((isset($car['is_featured']) && $car['is_featured'] == 1) ? 
                            '<div class="featured-badge">Featured</div>' : '') . '
                    </div>
                    <div class="card-body p-3" style="padding: 1rem !important;">
                        <h4 class="car-title mb-1" style="font-size: 1.2rem; font-weight: 600; color: #333;">
                            ' . htmlspecialchars($car['make'] . ' ' . $car['model']) . '
                            ' . ((isset($car['is_certified']) && $car['is_certified'] == 1) ? 
                                '<span class="certified-badge ms-1" data-bs-toggle="tooltip" title="Certified Pre-Owned">
                                    <i class="bi bi-patch-check-fill text-primary"></i>
                                </span>' : '') . '
                        </h4>
                        <div class="price-display mb-2" style="font-size: 1.1rem; font-weight: 700; color: #0d6efd;">
                            ' . $formatted_price . '
                        </div>
                        <div class="car-specs text-muted mb-2" style="font-size: 0.9rem;">
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="bi bi-calendar3 me-1"></i> ' . htmlspecialchars($car['year']) . '</span>
                                <span class="ms-2"><i class="bi bi-speedometer2 me-1"></i> ' . number_format($car['mileage']) . ' km</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="bi bi-fuel-pump me-1"></i> ' . htmlspecialchars($car['fuel_type']) . '</span>
                                <span class="ms-2"><i class="bi bi-gear me-1"></i> ' . htmlspecialchars($car['transmission'] ?? 'Auto') . '</span>
                            </div>
                        </div>
                        <a href="car_details.php?id=' . $car['car_id'] . '" class="btn btn-primary w-100 mt-2" style="font-size: 0.9rem; padding: 0.5rem;">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        </div>';
    }
    echo '</div>';
}
?>
    </div>
</div>
</div> <!-- Close container div -->

<style>
/* Card styles */
.car-listing-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.car-listing-card .card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 1.25rem;
}

.car-image-container {
    height: 200px;
    overflow: hidden;
    position: relative;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
}

.car-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    transition: transform 0.3s ease;
}

.car-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
}

.price-display {
    font-size: 1.1rem;
    font-weight: 700;
    color: #0d6efd;
    margin-bottom: 0.75rem;
}

.car-specs {
    font-size: 0.9rem;
    color: #6c757d;
    margin: 10px 0;
}

.car-specs i {
    width: 16px;
    text-align: center;
    margin-right: 4px;
}

/* Main layout */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}

.wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.main-content {
    flex: 1 0 auto;
    width: 100%;
}

.container {
    padding: 20px 15px;
    margin: 0 auto;
    max-width: 1200px;
    width: 100%;
}

/* Reset default margins and padding */
body {
    margin: 0;
    padding: 0;
}

/* Card grid */

/* Ensure proper card layout */
.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 -10px;
}

[class*='col-'] {
    padding: 0 10px;
    margin-bottom: 20px;
}

.card {
    height: 100%;
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
    margin: 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: relative;
}

.card:hover {
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 1rem !important;
}

.car-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.price-display {
    color: #0d6efd;
    font-weight: 700;
    font-size: 1.1rem;
    margin: 0.5rem 0;
}

.car-specs {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 1rem;
}

.car-specs i {
    width: 16px;
    text-align: center;
    margin-right: 4px;
}

.btn-view-details {
    margin-top: auto;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    [class*='col-'] {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .car-title {
        font-size: 1rem;
    }
    
    .price-display {
        font-size: 1rem;
    }
}
/* Enhanced Car Card Styles */
.car-listing-card {
    border: 1px solid #eee !important;
    border-radius: 10px !important;
    transition: all 0.3s ease;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.car-listing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}

.car-listing-card:hover .car-image {
    transform: scale(1.05);
}

.car-image-container {
    position: relative;
    overflow: hidden;
    background-color: #f8f9fa;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 15px;
}

.car-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain !important;
}

/* Price display styling */
.price-display {
    color: #0d6efd;
    font-weight: 700;
    margin: 0.5rem 0;
}

/* Card body improvements */
.card-body {
    padding: 1.25rem !important;
}

/* Car title */
.car-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #333;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

/* Car specs */
.car-specs {
    font-size: 0.9rem;
    color: #6c757d;
    line-height: 1.5;
}

.car-specs i {
    width: 16px;
    text-align: center;
    margin-right: 4px;
}

/* Ensure consistent spacing */
.card {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .car-title {
        font-size: 1.1rem;
    }
    
    .price-display {
        font-size: 1rem;
    }
    
    .car-specs {
        font-size: 0.85rem;
    }
}

.featured-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ffc107;
    color: #000;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    z-index: 2;
}

.car-title {
    font-weight: 600;
    color: #333;
    font-size: 1.1rem;
    margin-bottom: 10px;
}

.card-body {
    padding: 15px !important;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.car-specs {
    font-size: 0.9rem;
    color: #6c757d;
    margin-bottom: 15px;
}

.certified-badge {
    color: #0d6efd;
    font-size: 1rem;
    margin-left: 5px;
}

.btn-view-details {
    font-weight: 500;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    margin-top: auto;
}

.btn-view-details:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(13, 110, 253, 0.2);
}

/* Ensure all cards in a row have equal height */
.row-cols-md-2 > * {
    display: flex;
    flex-direction: column;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .car-image-container {
        min-height: 180px;
    }
    
    .car-title {
        font-size: 1rem;
    }
    
    .car-specs {
        font-size: 0.85rem;
    }
}

/* Main content wrapper */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}

.wrapper {
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.main-content {
    flex: 1 0 auto;
    padding-bottom: 60px; /* Adjust this value based on your footer height */
}

/* Card Styling */
.car-listing-card {
    border: none;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    background: #fff;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.car-listing-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
}

/* Image Container */
.car-image-container {
    position: relative;
    height: 200px;
    overflow: hidden;
    background: #f8f9fa;
}

.car-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}

.car-listing-card:hover .car-image {
    transform: scale(1.05);
}

/* Price Tag */
.price-tag {
    position: absolute;
    bottom: 15px;
    left: 0;
    background: rgba(0, 0, 0, 0.8);
    color: #fff;
    padding: 5px 12px;
    font-weight: 600;
    font-size: 1.1rem;
    border-top-right-radius: 4px;
    border-bottom-right-radius: 4px;
}

/* Featured Badge */
.featured-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: #ffc107;
    color: #000;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Card Body */
.car-listing-card .card-body {
    padding: 1.25rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.car-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #2c3e50;
    line-height: 1.3;
}

.car-specs {
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
    color: #6c757d;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
}

.car-specs i {
    color: #3498db;
    font-size: 0.9rem;
}

/* View Details Button */
.car-listing-card .btn-primary {
    margin-top: auto;
    background-color: #3498db;
    border: none;
    padding: 8px 15px;
    font-weight: 500;
    border-radius: 5px;
    transition: all 0.3s ease;
}

.car-listing-card .btn-primary:hover {
    background-color: #2980b9;
    transform: translateY(-2px);
}

/* Certified Badge */
.certified-badge {
    color: #3498db;
    font-size: 0.9rem;
}

.btn-sm {
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
    border-radius: 4px;
}

/* Price tag */
.position-absolute.bg-primary {
    background-color: #0d6efd !important;
    font-size: 0.9rem;
    font-weight: 500;
    padding: 0.25rem 0.75rem !important;
    border-top-left-radius: 4px;
    border-bottom-left-radius: 4px;
}

/* Featured badge */
.bg-warning {
    background-color: #ffc107 !important;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Location text */
.text-muted.small i {
    font-size: 0.9em;
}
</style>

    </div> <!-- Close main-content -->
</div> <!-- Close wrapper -->

<?php 
// Include the footer
include 'includes/footer.php'; 
?>
