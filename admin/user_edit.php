<?php
// Start output buffering to prevent any accidental output
ob_start();

require_once 'includes/db_connect.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$success = '';
$error = '';
$user_id = 0;
$username = '';
$first_name = '';
$last_name = '';
$email = '';
$phone = '';
$is_active = 1;
$is_admin = 0;
$redirect_after_process = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user_id from form if editing
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    // Validate CSRF token
    if (!isset($_POST['_token']) || $_POST['_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        // Get form data
        $username = trim($_POST['username'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $password = $_POST['password'] ?? '';
        
        // Basic validation
        if (empty($username) || empty($first_name) || empty($last_name) || empty($email)) {
            $error = 'Please fill in all required fields';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address';
        } else {
            // Check if username or email already exists (for new users or when changed)
            $check_sql = "SELECT user_id FROM users WHERE (username = ? OR email = ?)";
            $params = [$username, $email];
            $types = "ss";
            
            if ($user_id > 0) {
                $check_sql .= " AND user_id != ?";
                $params[] = $user_id;
                $types .= "i";
            }
            
            $stmt = $conn->prepare($check_sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'Username or email already exists';
            } else {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    if ($user_id > 0) {
                        // Update existing user
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $sql = "UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, phone = ?, is_active = ?, is_admin = ?, password = ? WHERE user_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("sssssiiii", $username, $first_name, $last_name, $email, $phone, $is_active, $is_admin, $hashed_password, $user_id);
                        } else {
                            $sql = "UPDATE users SET username = ?, first_name = ?, last_name = ?, email = ?, phone = ?, is_active = ?, is_admin = ? WHERE user_id = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param("sssssiii", $username, $first_name, $last_name, $email, $phone, $is_active, $is_admin, $user_id);
                        }
                    } else {
                        // Insert new user
                        if (empty($password)) {
                            $error = 'Password is required for new users';
                            throw new Exception($error);
                        }
                        
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO users (username, first_name, last_name, email, phone, password, is_active, is_admin, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("ssssssii", $username, $first_name, $last_name, $email, $phone, $hashed_password, $is_active, $is_admin);
                    }
                    
                    if (!$stmt->execute()) {
                        throw new Exception('Error saving user: ' . $stmt->error);
                    }
                    
                    if ($user_id === 0) {
                        $user_id = $conn->insert_id;
                    }
                    
                    $conn->commit();
                    $success = 'User ' . ($user_id > 0 ? 'updated' : 'created') . ' successfully';
                    
                    // Set flag to redirect after processing
                    $redirect_after_process = true;
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// Handle viewing/editing an existing user
if (!$redirect_after_process && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // User not found
        header('Location: users.php?error=User not found');
        exit();
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Populate form fields
    $username = $user['username'];
    $first_name = $user['first_name'];
    $last_name = $user['last_name'];
    $email = $user['email'];
    $phone = $user['phone'] ?? '';
    $is_active = $user['is_active'];
    $is_admin = $user['is_admin'];
}

// Handle redirect after successful form submission
if ($redirect_after_process) {
    header('Location: users.php?success=' . urlencode($success));
    exit();
}

// Now include the header after all redirects are handled
require_once 'includes/header.php';

// Clear the output buffer to prevent any accidental output
ob_end_clean();
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
{{ ... }}
        <a href="users.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
            <i class="bi bi-arrow-left text-white-50"></i> Back to Users
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">User Information</h6>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                               value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                               value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="is_active" name="is_active" 
                           value="1" <?php echo ($user['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_active">Active Account</label>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Account Created</label>
                    <p class="form-control-plaintext">
                        <?php echo date('M j, Y g:i A', strtotime($user['created_at'])); ?>
                    </p>
                </div>
                
                <?php if (!empty($user['last_login'])): ?>
                <div class="mb-3">
                    <label class="form-label">Last Login</label>
                    <p class="form-control-plaintext">
                        <?php echo date('M j, Y g:i A', strtotime($user['last_login'])); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="users.php" class="btn btn-secondary me-md-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
