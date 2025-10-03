<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: index.php');
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
        // Prepare a select statement for admin users from admins table
        $sql = "SELECT admin_id, username, password_hash, CONCAT(first_name, ' ', last_name) as full_name, role 
                FROM admins 
                WHERE username = ? AND is_active = 1";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $username);
            
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                
                if ($result->num_rows == 1) {
                    $admin = $result->fetch_assoc();
                    if (password_verify($password, $admin['password_hash'])) {
                        // Password is correct, start admin session
                        session_regenerate_id();
                        
                        // Store admin data in session variables
                        $_SESSION['admin_id'] = $admin['admin_id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_name'] = $admin['full_name'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['is_superadmin'] = ($admin['role'] === 'superadmin') ? 1 : 0;
                        
                        // Update last login in database
                        $update_sql = "UPDATE admins SET last_login = NOW() WHERE admin_id = ?";
                        if ($update_stmt = $conn->prepare($update_sql)) {
                            $update_stmt->bind_param("i", $admin['admin_id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                        
                        // Redirect to admin dashboard
                        header("Location: index.php");
                        exit();
                    } else {
                        $error = 'The password you entered is not valid.';
                    }
                } else {
                    $error = 'No admin account found with that username or you do not have admin privileges.';
                }
            } else {
                $error = 'Database error: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Elite Motors</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 3rem;
            color: #0d6efd;
        }
        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .btn-login {
            background: #0d6efd;
            border: none;
            padding: 10px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: #0b5ed7;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <i class="bi bi-car-front"></i>
                <h2>Elite Motors</h2>
                <p class="text-muted">Admin Panel</p>
            </div>
            
            
            <form id="loginForm" action="/elitemotors/admin/login.php" method="post" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> Login
                    </button>
                </div>
            </form>
            
            <div class="footer mt-4">
                <p>© <?php echo date('Y'); ?> Elite Motors. All rights reserved.</p>
                <a href="/elitemotors/" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to Website
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation with simple alerts
    document.addEventListener('DOMContentLoaded', function() {
        const loginForm = document.getElementById('loginForm');
        
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
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
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Signing in...';
                
                // Submit the form
                this.submit();
                return false;
            });
        }
        
        // Show error message from PHP if exists
        <?php if (!empty($error)): ?>
        alert('<?php echo addslashes($error); ?>');
        <?php endif; ?>
    });
    </script>
</body>
</html>
