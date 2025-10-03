<?php 
require_once 'includes/init.php';
$page_title = 'Home | Elite Motors';
include 'includes/header.php'; 
?>

<!-- Hero Section with Full-Screen Background -->
<div class="hero-section position-relative">
    <div class="hero-overlay"></div>
    <div class="container h-100">
        <div class="row h-100 align-items-center">
            <div class="col-12 text-center text-white">
                <h1 class="display-3 fw-bold mb-4">Welcome to Elite Motors</h1>
                <p class="lead mb-5">Your Trusted Partner in Automotive Excellence</p>
            </div>
        </div>
    </div>
</div>

<!-- Featured Cars Section -->
<div id="featured" class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">Our Collection</h2>
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0">
                    <img src="https://images.unsplash.com/photo-1555215695-3004980ad54e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" 
                         class="card-img-top" alt="Luxury Sedan" style="height: 200px; object-fit: cover;">
                    <div class="card-body text-center">
                        <h5 class="card-title">Luxury Sedan</h5>
                        <a href="buy_car.php?type=sedan" class="btn btn-outline-primary mt-2">View Sedans</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0">
                    <img src="https://images.unsplash.com/photo-1503376780353-7e6692767b70?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" 
                         class="card-img-top" alt="Sports Car" style="height: 200px; object-fit: cover;">
                    <div class="card-body text-center">
                        <h5 class="card-title">Sports Car</h5>
                        <a href="buy_car.php?type=sports" class="btn btn-outline-primary mt-2">View Sports Cars</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100 border-0">
                    <img src="https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" 
                         class="card-img-top" alt="Luxury SUV" style="height: 200px; object-fit: cover;">
                    <div class="card-body text-center">
                        <h5 class="card-title">Luxury SUV</h5>
                        <a href="buy_car.php?type=suv" class="btn btn-outline-primary mt-2">View SUVs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Hero Section */
.hero-section {
    background: linear-gradient(rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.7)), 
                url('https://images.unsplash.com/photo-1603584173870-7f23fdae1b7a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2000&q=80');
    background-size: cover;
    background-position: center 40%;
    background-repeat: no-repeat;
    min-height: 75vh;
    display: flex;
    align-items: center;
    position: relative;
}

.hero-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.4);
}

/* Card Hover Effect */
.card {
    transition: transform 0.3s ease;
    border: 1px solid #eee;
}

.card:hover {
    transform: translateY(-5px);
}

/* Responsive Adjustments */
@media (max-width: 1200px) {
    .hero-section {
        min-height: 65vh;
        background-position: center 50%;
    }
}

@media (max-width: 768px) {
    .hero-section {
        min-height: 55vh;
        background-position: center 60%;
    }
    
    .display-3 {
        font-size: 2.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
