<?php
session_start();
require_once 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /elitemotors/login.php');
    exit();
}

$page_title = 'My Profile';
$success = '';
$error = '';

// Get user data
$user_id = $_SESSION['user_id'];
$user = [];
$sql = "SELECT * FROM users WHERE user_id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process profile update
    if (isset($_POST['update_profile'])) {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email already exists (excluding current user)
            $sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("si", $email, $user_id);
                if ($stmt->execute()) {
                    $stmt->store_result();
                    if ($stmt->num_rows > 0) {
                        $error = "This email is already taken.";
                    }
                }
                $stmt->close();
            }
        }
        
        // If no errors, update the profile
        if (empty($error)) {
            $sql = "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ? WHERE user_id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("sssssi", $first_name, $last_name, $email, $phone, $address, $user_id);
                if ($stmt->execute()) {
                    $success = "Profile updated successfully!";
                    // Update session data
                    $_SESSION['username'] = $email;
                    // Refresh user data
                    $user = array_merge($user, [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'email' => $email,
                        'phone' => $phone,
                        'address' => $address
                    ]);
                } else {
                    $error = "Error updating profile. Please try again.";
                }
                $stmt->close();
            }
        }
    }
    
    // Profile picture upload functionality has been removed
}
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
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
            
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mb-3" 
                         style="width: 150px; height: 150px; margin: 0 auto;">
                        <i class="bi bi-person fs-1 text-muted"></i>
                    </div>
                    
                    <h4 class="mb-2"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h4>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                    
                    <?php if (!empty($user['phone'])): ?>
                        <p class="text-muted mb-4">
                            <i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($user['phone']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Profile Information -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Profile Information</h5>
                </div>
                <div class="card-body">
                    <form id="profileForm" method="post">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            </div>
                            <div class="col-12">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>">
                            </div>
                            <div class="col-12">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php 
                                    echo htmlspecialchars($user['address'] ?? ''); 
                                ?></textarea>
                            </div>
                            <div class="col-12 mt-3">
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('profileForm');
    
    // Simple alert message
    function showMessage(message) {
        alert(message);
    }

    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            
            // Simple validation
            if (!firstName) {
                return showMessage('Please enter your first name');
            }
            
            if (!lastName) {
                return showMessage('Please enter your last name');
            }
            
            if (!email) {
                return showMessage('Please enter your email address');
            }
            
            if (email.indexOf('@') === -1 || email.indexOf('.') === -1) {
                return showMessage('Please enter a valid email address');
            }
            
            
            // If all validations pass, submit the form
            this.submit();
        });
    }
    
    // Show success message if profile was updated
    <?php if ($success): ?>
    showMessage('<?php echo addslashes($success); ?>');
    <?php endif; ?>
    
    // Show error message if there was an error
    <?php if ($error): ?>
    showMessage('<?php echo addslashes($error); ?>');
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>
