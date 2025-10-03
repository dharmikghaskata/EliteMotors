<?php
session_start();
$page_title = 'Register';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: /elitemotors/');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'includes/db_connect.php';
    
    // Validate username
    if (empty(trim($_POST['username']))) {
        $error = 'Please enter a username.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', trim($_POST['username']))) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    } else {
        // Check if username already exists
        $sql = "SELECT user_id FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST['username']);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $error = 'This username is already taken.';
                } else {
                    $username = trim($_POST['username']);
                }
            } else {
                $error = 'Oops! Something went wrong. Please try again later.';
            }
            $stmt->close();
        }
    }
    
    // Validate email
    if (empty(trim($_POST['email']))) {
        $error = 'Please enter an email address.';
    } elseif (!filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email already exists
        $sql = "SELECT user_id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST['email']);
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $error = 'This email is already registered.';
                } else {
                    $email = trim($_POST['email']);
                }
            } else {
                $error = 'Oops! Something went wrong. Please try again later.';
            }
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST['password']))) {
        $error = 'Please enter a password.';     
    } elseif (strlen(trim($_POST['password'])) < 6) {
        $error = 'Password must have at least 6 characters.';
    } else {
        $password = trim($_POST['password']);
    }
    
    // Validate confirm password
    if (empty(trim($_POST['confirm_password']))) {
        $error = 'Please confirm the password.';     
    } else {
        $confirm_password = trim($_POST['confirm_password']);
        if ($password !== $confirm_password) {
            $error = 'Passwords did not match.';
        }
    }
    
    // Check for errors before inserting into database
    if (empty($error)) {
        $sql = "INSERT INTO users (username, email, password_hash, first_name, last_name, phone) VALUES (?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param(
                "ssssss", 
                $param_username, 
                $param_email, 
                $param_password, 
                $param_first_name, 
                $param_last_name, 
                $param_phone
            );
            
            // Set parameters
            $param_username = $username;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_first_name = trim($_POST['first_name']);
            $param_last_name = trim($_POST['last_name']);
            $param_phone = trim($_POST['phone']);
            
            if ($stmt->execute()) {
                $success = 'Registration successful! You can now login.';
                // Clear form
                $_POST = array();
            } else {
                $error = 'Something went wrong. Please try again later.';
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<?php include 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow">
            <div class="card-body">
                <h2 class="text-center mb-4">Create an Account</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?> <a href="/elitemotors/login.php" class="alert-link">Login here</a>.
                    </div>
                <?php else: ?>
                
                <form action="/elitemotors/register.php" method="post" class="needs-validation" novalidate>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter your first name.</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                            <div class="invalid-feedback">Please enter your last name.</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">@</span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                        <div class="invalid-feedback">Please choose a username.</div>
                        <div class="form-text">Letters, numbers, and underscores only.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="invalid-feedback">Please enter a password (min 6 characters).</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        <div class="invalid-feedback">Passwords must match.</div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <p>Already have an account? <a href="/elitemotors/login.php">Login here</a></p>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
