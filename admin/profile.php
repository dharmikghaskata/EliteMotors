<?php
require_once 'includes/header.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$success = $error = '';
$admin = [];

// Get admin details
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT admin_id, username, email, created_at FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
} else {
    $error = 'Admin not found';
}
$stmt->close();

// Handle form submission for updating profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($username)) {
        $error = 'Username is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if password is being changed
        if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
            // Verify current password
            $stmt = $conn->prepare("SELECT password_hash FROM admins WHERE admin_id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $db_password = $result->fetch_assoc()['password_hash'];
            $stmt->close();
            
            if (!password_verify($current_password, $db_password)) {
                $error = 'Current password is incorrect';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters long';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New password and confirm password do not match';
            }
        }
        
        // If no errors, update the profile
        if (empty($error)) {
            if (!empty($new_password)) {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE admins SET username = ?, email = ?, password_hash = ? WHERE admin_id = ?");
                $stmt->bind_param("sssi", $username, $email, $hashed_password, $admin_id);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE admins SET username = ?, email = ? WHERE admin_id = ?");
                $stmt->bind_param("ssi", $username, $email, $admin_id);
            }
            
            if ($stmt->execute()) {
                $success = 'Profile updated successfully';
                // Update session
                $_SESSION['admin_name'] = $username;
                // Refresh admin data
                $admin['username'] = $username;
                $admin['email'] = $email;
            } else {
                $error = 'Error updating profile: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Admin Profile</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($admin['username'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Member Since</label>
                            <p class="form-control-plaintext">
                                <?php echo !empty($admin['created_at']) ? date('F j, Y', strtotime($admin['created_at'])) : 'N/A'; ?>
                            </p>
                        </div>
                        
                        <h5 class="mt-4 mb-3">Change Password</h5>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>
</div>

<?php include 'includes/footer.php'; ?>
