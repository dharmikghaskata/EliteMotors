<?php
session_start();
$page_title = 'Login';

// Redirect if already logged in (as user or admin)
if (isset($_SESSION['user_id']) || isset($_SESSION['admin_id'])) {
    header('Location: /elitemotors/');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db_connect.php';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // First, try to log in as admin
        $sql = "SELECT admin_id, username, password_hash, first_name, last_name, role 
                FROM admins 
                WHERE username = ? AND is_active = 1";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $admin = $result->fetch_assoc();
                    if (password_verify($password, $admin['password_hash'])) {
                        // Admin login successful
                        session_regenerate_id();
                        
                        // Store admin data in session
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_name'] = $admin['first_name'] . ' ' . $admin['last_name'];
                        $_SESSION['admin_role'] = $admin['role'];
                        
                        // Update last login
                        $update_sql = "UPDATE admins SET last_login = NOW() WHERE admin_id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("i", $admin['admin_id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        
                        // Redirect to admin dashboard
                        header("Location: /elitemotors/admin/");
                        exit();
                    }
                }
            }
            $stmt->close();
        }
        
        // If not an admin, try regular user login
        $sql = "SELECT user_id, username, password_hash, first_name, last_name 
                FROM users 
                WHERE username = ? AND is_active = 1";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password_hash'])) {
                        // User login successful
                        session_regenerate_id();
                        
                        // Store user data in session
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        
                        // Update last login
                        $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("i", $user['user_id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        
                        // Redirect to home page
                        header("Location: /elitemotors/");
                        exit();
                    }
                }
            }
            $stmt->close();
        }
        
        // If we get here, login failed
        $error = 'Invalid username or password.';
    }
    $conn->close();
}
?>

<?php include 'includes/header.php'; ?>


<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-body">
                <h2 class="text-center mb-4">Login</h2>
                
                <form id="loginForm" action="/elitemotors/login.php" method="post" novalidate onsubmit="return validateLoginForm(event)">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary">Login</button>
                    </div>
                    
                    <div class="text-center">
                        <p class="mb-0">Don't have an account? <a href="/elitemotors/register.php">Register here</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Form validation with SweetAlert2
function validateLoginForm(event) {
    event.preventDefault();
    
    const form = event.target;
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    let errorMessage = '';
    
    // Validate username
    if (!username) {
        errorMessage = 'Please enter your username';
    } 
    // Validate password
    else if (!password) {
        errorMessage = 'Please enter your password';
    }
    
    // Show error message if validation fails
    if (errorMessage) {
        alert(errorMessage);
        return false;
    }
    
    // Show loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Signing in...';
    
    // Submit the form
    form.submit();
    return false;
}

// Show error message from PHP if exists
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($error)): ?>
    alert('<?php echo addslashes($error); ?>');
    <?php endif; ?>
});
</script>
