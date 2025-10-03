<?php
session_start();

$page_title = 'Listing Submitted | Elite Motors';

// Check if the user was redirected from the form submission
if (!isset($_SESSION['success_message'])) {
    header('Location: sell_car.php');
    exit();
}

// Get the success message
$success_message = $_SESSION['success_message'];
unset($_SESSION['success_message']);

include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-body p-5 text-center">
                    <div class="mb-4">
                        <div class="bg-success bg-opacity-10 d-inline-flex p-3 rounded-circle mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                        </div>
                        <h1 class="h3 mb-3">Thank You for Your Submission!</h1>
                        <p class="lead"><?php echo htmlspecialchars($success_message); ?></p>
                        <p>Our team will review your listing and contact you shortly. In the meantime, you can:</p>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="bg-light rounded-circle d-inline-flex p-3 mb-3">
                                        <i class="bi bi-car-front text-primary" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <h3 class="h5">View Your Listings</h3>
                                    <p class="small text-muted">Track the status of your car listing</p>
                                    <a href="my_listings.php" class="btn btn-outline-primary btn-sm">My Listings</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="bg-light rounded-circle d-inline-flex p-3 mb-3">
                                        <i class="bi bi-plus-circle text-primary" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <h3 class="h5">List Another Vehicle</h3>
                                    <p class="small text-muted">Add more cars to your listings</p>
                                    <a href="sell_car.php" class="btn btn-outline-primary btn-sm">Sell Another Car</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="bg-light rounded-circle d-inline-flex p-3 mb-3">
                                        <i class="bi bi-house text-primary" style="font-size: 1.5rem;"></i>
                                    </div>
                                    <h3 class="h5">Back to Home</h3>
                                    <p class="small text-muted">Return to the homepage</p>
                                    <a href="/elitemotors/" class="btn btn-outline-primary btn-sm">Go Home</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info text-start">
                        <h4 class="h6 mb-2">What Happens Next?</h4>
                        <ol class="mb-0">
                            <li>Our team will review your listing (usually within 24 hours)</li>
                            <li>We'll contact you if we need any additional information</li>
                            <li>Once approved, your car will be visible to thousands of potential buyers</li>
                            <li>You'll be notified when someone is interested in your vehicle</li>
                        </ol>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <p class="text-muted small mb-2">Need help? Contact our support team</p>
                        <a href="contact.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-envelope me-1"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
