<?php
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Function to check if a table exists
function tableExists($conn, $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    return $result->num_rows > 0;
}

// Check if user is superadmin
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'superadmin') {
    header('Location: index.php');
    exit();
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Add new admin
        if ($action === 'add' && !empty($_POST['username']) && !empty($_POST['password'])) {
            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $first_name = trim($_POST['first_name']);
            $last_name = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $is_superadmin = isset($_POST['is_superadmin']) ? 1 : 0;
            
            // Validate username
            $stmt = $admin_db->prepare("SELECT admin_id FROM admins WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();
            
            if ($stmt->num_rows > 0) {
                $error = 'Username already exists.';
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Set role based on is_superadmin
                $role = $is_superadmin ? 'superadmin' : 'admin';
                
                // Insert new admin
                $insert = $admin_db->prepare("INSERT INTO admins (username, password_hash, first_name, last_name, email, role) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->bind_param("ssssss", $username, $hashed_password, $first_name, $last_name, $email, $role);
                
                if ($insert->execute()) {
                    $admin_id = $insert->insert_id;
                    $success = 'Admin user created successfully.';
                    
                    // Log the activity if admin_activity table exists
                    if (tableExists($admin_db, 'admin_activity')) {
                        $description = 'Added new admin: ' . $username;
                        $activity_sql = "INSERT INTO admin_activity (admin_id, activity_type, description, ip_address, user_agent) 
                                       VALUES (?, 'admin_add', ?, ?, ?)";
                        $activity_stmt = $admin_db->prepare($activity_sql);
                        if ($activity_stmt) {
                            $activity_stmt->bind_param("isss", $_SESSION['admin_id'], $description, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                            $activity_stmt->execute();
                            $activity_stmt->close();
                        }
                    }
                } else {
                    $error = 'Error creating admin user: ' . $admin_db->error;
                }
                $insert->close();
            }
            $stmt->close();
        }
        // Delete admin
        elseif ($action === 'delete' && !empty($_POST['admin_id'])) {
            $admin_id = (int)$_POST['admin_id'];
            
            // Don't allow deleting self
            if ($admin_id === $_SESSION['admin_id']) {
                $error = 'You cannot delete your own account.';
            } else {
                // Get username for logging
                $stmt = $admin_db->prepare("SELECT username FROM admins WHERE admin_id = ?");
                $stmt->bind_param("i", $admin_id);
                $stmt->execute();
                $stmt->bind_result($username);
                $stmt->fetch();
                $stmt->close();
                
                // Delete admin
                $delete = $admin_db->prepare("DELETE FROM admins WHERE admin_id = ?");
                $delete->bind_param("i", $admin_id);
                
                if ($delete->execute()) {
                    $success = 'Admin user deleted successfully.';
                    
                    // Log the activity if admin_activity table exists
                    if (tableExists($admin_db, 'admin_activity')) {
                        $description = 'Deleted admin: ' . $username;
                        $activity_sql = "INSERT INTO admin_activity (admin_id, activity_type, description, ip_address, user_agent) 
                                       VALUES (?, 'admin_delete', ?, ?, ?)";
                        $activity_stmt = $admin_db->prepare($activity_sql);
                        if ($activity_stmt) {
                            $activity_stmt->bind_param("isss", $_SESSION['admin_id'], $description, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
                            $activity_stmt->execute();
                            $activity_stmt->close();
                        }
                    }
                } else {
                    $error = 'Error deleting admin user: ' . $admin_db->error;
                }
                $delete->close();
            }
        }
    }
}

// Get all admins
$admins = [];
$result = $admin_db->query("SELECT admin_id, username, first_name, last_name, email, role, last_login FROM admins ORDER BY role = 'superadmin' DESC, username");
if ($result) {
    $admins = $result->fetch_all(MYSQLI_ASSOC);
    
    // Add a full_name field to each admin by combining first_name and last_name
    foreach ($admins as &$admin) {
        $admin['full_name'] = trim($admin['first_name'] . ' ' . $admin['last_name']);
        // For backward compatibility, add is_superadmin flag based on role
        $admin['is_superadmin'] = ($admin['role'] === 'superadmin') ? 1 : 0;
    }
    unset($admin); // Break the reference
}
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Admin Users</h1>
        <button type="button" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addAdminModal">
            <i class="bi bi-plus-circle me-2"></i>Add New Admin
        </button>
    </div>

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

    <!-- Admins Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Admin Users</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td>
                                    <?php if ($admin['role'] === 'superadmin'): ?>
                                        <span class="badge bg-danger">Super Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $admin['last_login'] ? date('M j, Y g:i A', strtotime($admin['last_login'])) : 'Never'; ?>
                                </td>
                                <td>
                                    <?php if ($_SESSION['admin_id'] !== $admin['admin_id'] && $_SESSION['admin_role'] === 'superadmin'): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['admin_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Admin">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php elseif ($_SESSION['admin_id'] !== $admin['admin_id'] && $_SESSION['admin_role'] === 'admin' && $admin['role'] !== 'superadmin'): ?>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['admin_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete Admin">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
            </div>
        </div>
    </div>
</div>

<!-- Add Admin Modal -->
<div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAdminModalLabel">Add New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_superadmin" name="is_superadmin">
                        <label class="form-check-label" for="is_superadmin">
                            Super Admin (full access)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DataTables JavaScript -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        $('#dataTable').DataTable({
            "order": [[3, 'desc'], [0, 'asc']],
            "columnDefs": [
                { "orderable": false, "targets": [5] }
            ]
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
