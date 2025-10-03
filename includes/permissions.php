<?php
function canEditCar($car_user_id) {
    // Admins can edit any car
    if (isset($_SESSION['admin_id'])) {
        return true;
    }
    
    // Regular users can only edit their own cars
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $car_user_id) {
        return true;
    }
    
    return false;
}

function canDeleteCar() {
    // Only admins can delete cars
    return isset($_SESSION['admin_id']);
}

function isAdmin() {
    return isset($_SESSION['admin_id']);
}
?>
