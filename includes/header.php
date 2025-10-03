<?php require_once __DIR__ . '/init.php'; ?>
<!DOCTYPE html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Motors - Luxury Vehicles</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.0/nouislider.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Load jQuery first -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Then Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.0/nouislider.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Initialize Bootstrap dropdowns -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });
        
        // Enable dropdown on hover for desktop
        if (window.innerWidth > 992) { // Desktop only
            var dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(function(dropdown) {
                dropdown.addEventListener('mouseenter', function() {
                    var dropdownMenu = this.querySelector('.dropdown-menu');
                    var dropdownInstance = bootstrap.Dropdown.getInstance(this.querySelector('.dropdown-toggle'));
                    if (dropdownInstance) {
                        dropdownInstance.show();
                    }
                });
                
                dropdown.addEventListener('mouseleave', function() {
                    var dropdownMenu = this.querySelector('.dropdown-menu');
                    var dropdownInstance = bootstrap.Dropdown.getInstance(this.querySelector('.dropdown-toggle'));
                    if (dropdownInstance) {
                        // Add a small delay before hiding to allow moving cursor to dropdown
                        setTimeout(() => {
                            if (!this.matches(':hover') && !dropdownMenu.matches(':hover')) {
                                dropdownInstance.hide();
                            }
                        }, 200);
                    }
                });
            });
        }
    });
    </script>
    <link rel="stylesheet" href="/elitemotors/assets/css/style.css">
    <style>
        /* Custom styles for range slider */
        .range-slider {
            padding: 10px 15px;
        }
        .noUi-target {
            background: #f0f0f0;
            border: none;
            box-shadow: none;
            height: 4px;
        }
        .noUi-connect {
            background: #0d6efd;
        }
        .noUi-handle {
            width: 18px;
            height: 18px;
            right: -9px !important;
            top: -7px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #0d6efd;
            box-shadow: none;
        }
        .noUi-handle:before, .noUi-handle:after {
            display: none;
        }
        .noUi-handle.noUi-handle-upper {
            right: -9px !important;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/elitemotors/">Elite Motors</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" 
                           href="/elitemotors/index.php">
                            <i class="bi bi-house-door me-1"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'buy_car.php') ? 'active' : ''; ?>" 
                           href="/elitemotors/buy_car.php">
                            <i class="bi bi-search me-1"></i> Buy Car
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'sell_car.php') ? 'active' : ''; ?>" 
                           href="/elitemotors/sell_car.php">
                            <i class="bi bi-tag me-1"></i> Sell Car
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'contact.php') ? 'active' : ''; ?>" 
                           href="/elitemotors/contact.php">
                            <i class="bi bi-envelope me-1"></i> Contact
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'active' : ''; ?>" 
                           href="/elitemotors/about.php">
                            <i class="bi bi-info-circle me-1"></i> About Us
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php 
                    // Check if user is logged in (either as regular user or admin)
                    $isLoggedIn = false;
                    $username = '';
                    $isAdmin = false;
                    
                    if(isset($_SESSION['user_id'])) {
                        // Regular user is logged in
                        $isLoggedIn = true;
                        $username = $_SESSION['username'] ?? '';
                    } 
                    // Check for admin session
                    elseif(isset($_SESSION['admin_id'])) {
                        $isLoggedIn = true;
                        $isAdmin = true;
                        $username = $_SESSION['admin_username'] ?? 'Admin';
                    }
                    
                    if($isLoggedIn): 
                    ?>
                        <li class="nav-item dropdown" id="userDropdownContainer">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="me-2">Hi, <?php echo htmlspecialchars($username); ?></span>
                                <i class="bi bi-<?php echo $isAdmin ? 'shield-lock' : 'person-circle'; ?> fs-5"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <?php if($isAdmin): ?>
                                    <li><a class="dropdown-item" href="/elitemotors/admin/"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="/elitemotors/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                                    <li><a class="dropdown-item" href="/elitemotors/my_listings.php"><i class="bi bi-car-front me-2"></i>My Listings</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item text-danger" href="/elitemotors/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                        <style>
                            /* Ensure dropdown works on hover for desktop */
                            @media (min-width: 992px) {
                                #userDropdownContainer .dropdown-menu {
                                    display: none;
                                    margin-top: 0;
                                }
                                #userDropdownContainer:hover .dropdown-menu {
                                    display: block;
                                }
                                #userDropdownContainer .dropdown-toggle::after {
                                    display: none;
                                }
                            }
                        </style>
                    <?php else: ?>
                        <li class="nav-item me-2">
                            <a class="btn btn-outline-light px-3 rounded-pill" href="/elitemotors/login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-primary px-3 rounded-pill" href="/elitemotors/register.php">
                                <i class="bi bi-person-plus me-1"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">
