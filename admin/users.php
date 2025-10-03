<?php
// Start output buffering at the very beginning
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('IN_ELITEMOTORS', true);
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    $_SESSION['error'] = 'Please login to access this page';
    header('Location: login.php');
    exit();
}

// Initialize variables
$success = $error = '';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    // Set JSON header for AJAX responses
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        
        try {
            // Verify CSRF token
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token. Please refresh the page and try again.');
            }
            
            $user_id = (int)($_POST['user_id'] ?? 0);
            
            if ($user_id <= 0) {
                throw new Exception('Invalid user ID');
            }
            
            if ($user_id == ($_SESSION['admin_id'] ?? 0)) {
                throw new Exception('You cannot delete your own account while logged in.');
            }
            
            // Start transaction
            $conn->begin_transaction();
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT user_id, is_admin FROM users WHERE user_id = ?");
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                throw new Exception('Database error: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception('User not found');
            }
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // Basic permission check - only allow deleting other users
            if ($user_id == ($_SESSION['admin_id'] ?? 0)) {
                throw new Exception('You cannot delete your own account.');
            }
            
            // Disable foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS=0");
            
            // Get all tables that reference the users table
            $tables_query = $conn->query("
                SELECT TABLE_NAME, COLUMN_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE REFERENCED_TABLE_NAME = 'users' 
                AND TABLE_SCHEMA = DATABASE()
            ");
            
            // Delete or update related records
            if ($tables_query) {
                while ($row = $tables_query->fetch_assoc()) {
                    $table = $row['TABLE_NAME'];
                    $column = $row['COLUMN_NAME'];
                    
                    try {
                        $conn->query("UPDATE `$table` SET `$column` = NULL WHERE `$column` = $user_id");
                    } catch (Exception $e) {
                        try {
                            $conn->query("DELETE FROM `$table` WHERE `$column` = $user_id");
                        } catch (Exception $e) {
                            error_log("Error cleaning up related records in $table.$column: " . $e->getMessage());
                        }
                    }
                }
            }
            
            // Get user details before deletion
            $user = $conn->query("SELECT username, email FROM users WHERE user_id = $user_id")->fetch_assoc();
            
            // Now delete the user
            $delete = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            if (!$delete) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $delete->bind_param("i", $user_id);
            if ($delete->execute()) {
                // Log the deletion
                $description = "User deleted: {$user['username']} ({$user['email']})";
                $conn->query("INSERT INTO admin_activity (admin_id, activity_type, description, ip_address) 
                             VALUES (1, 'user_deleted', ".$conn->real_escape_string($description).", '$_SERVER[REMOTE_ADDR]')");
                
                // Commit the transaction
                $conn->commit();
            } else {
                throw new Exception('Could not delete user: ' . $delete->error);
            }
            
            // Re-enable foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS=1");
            
            // Clean the output buffer and return JSON response
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully!',
                'redirect' => 'users.php?deleted=1'
            ]);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($conn)) {
                $conn->rollback();
            }
            
            // Log the error
            error_log('User Deletion Error: ' . $e->getMessage());
            
            // Clean the output buffer and return error response
            ob_end_clean();
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
            exit();
        }
    } else {
        // Non-AJAX fallback
        try {
            if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
                throw new Exception('Invalid security token');
            }
            
            $user_id = (int)($_POST['user_id'] ?? 0);
            
            if ($user_id <= 0) {
                throw new Exception('Invalid user ID');
            }
            
            if ($user_id == ($_SESSION['admin_id'] ?? 0)) {
                throw new Exception('You cannot delete your own account while logged in.');
            }
            
            // Start transaction
            $conn->begin_transaction();
            
            // Check if user exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
            if (!$stmt) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) {
                throw new Exception('Database error: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception('User not found');
            }
            
            $user = $result->fetch_assoc();
            $stmt->close();
            
            // Check permissions for admin accounts
            if ($user['is_admin']) {
                if (!isset($_SESSION['is_super_admin']) || $_SESSION['is_super_admin'] !== true) {
                    throw new Exception('You do not have permission to delete administrator accounts.');
                }
                
                if ($user_id == ($_SESSION['admin_id'] ?? 0)) {
                    throw new Exception('You cannot delete your own admin account.');
                }
            }
            
            // No need to update sessions as we don't have a sessions table
            
            // Delete the user
            $delete = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            if (!$delete) {
                throw new Exception('Database error: ' . $conn->error);
            }
            
            $delete->bind_param("i", $user_id);
            if (!$delete->execute()) {
                if ($conn->errno == 1451) { // Foreign key constraint
                    throw new Exception('Cannot delete user because they have related records in the system.');
                }
                throw new Exception('Could not delete user: ' . $delete->error);
            }
            
            $delete->close();
            $conn->commit();
            
            $_SESSION['success'] = 'User deleted successfully!';
            
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        
        // Clean the output buffer and redirect
        ob_end_clean();
        header('Location: users.php');
        exit();
    }
}

// Get success/error messages from session
$success = '';
$error = '';

// Only get messages if we're not in an AJAX request
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    $success = $_SESSION['success'] ?? '';
    $error = $_SESSION['error'] ?? '';
    unset($_SESSION['success'], $_SESSION['error']);
    
    // If we're being redirected after a successful deletion
    if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
        $success = 'User deleted successfully!';
    }
}

// Get all users
$users = [];
$query = "SELECT user_id, username, first_name, last_name, email, phone, is_active, created_at 
          FROM users 
          ORDER BY created_at DESC";

try {
    $result = $conn->query($query);
    if ($result) {
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
    } else {
        throw new Exception("Error fetching users: " . $conn->error);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log($error);
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Users</h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Users List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="usersTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['user_id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : '-'; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-danger delete-user-btn" 
                                                data-userid="<?php echo $user['user_id']; ?>"
                                                data-isadmin="0"
                                                title="Delete User">
                                            <i class="fas fa-trash"></i> 
                                            Delete
                                        </button>
                                    </td>
                                    
                                    <!-- Hidden form for actual deletion -->
                                    <form id="deleteForm_<?php echo $user['user_id']; ?>" method="POST" style="display: none;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <input type="hidden" name="delete_user" value="1">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    </form>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Simple delete confirmation is now handled by browser dialogs -->

<!-- Required Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Handle delete button clicks
    $(document).on('click', '.delete-user-btn', function() {
        const userId = $(this).data('userid');
        const isAdmin = $(this).data('isadmin') === '1';
        const $button = $(this);
        
        // Show confirmation
        let confirmMessage = 'Are you sure you want to delete this user? This action cannot be undone.';
        if (isAdmin) {
            confirmMessage = 'WARNING: You are about to delete an administrator account.\n\n' +
                           'Type "DELETE" to confirm:';
            
            const userInput = prompt(confirmMessage);
            if (userInput !== 'DELETE') {
                return false;
            }
        } else if (!confirm(confirmMessage)) {
            return false;
        }
        
        // Show loading state
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
        
        // Submit the form
        $(`#deleteForm_${userId}`).submit();
    });
    
    // Initialize DataTable
    const table = $('#usersTable').DataTable({
        responsive: true,
        pageLength: 10,
        order: [[0, 'desc']],
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search users...",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No entries found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            zeroRecords: "No matching records found",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });

    // Add a simple confirmation for all delete forms
    $(document).on('submit', 'form[onsubmit*="confirm"]', function() {
        const form = this;
        const isAdmin = $(this).find('button[type="submit"]').text().toLowerCase().includes('admin');
        
        if (isAdmin) {
            const confirmText = prompt('Type "DELETE" to confirm deletion of this admin account:');
            if (confirmText !== 'DELETE') {
                return false; // Cancel the form submission
            }
        } else if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return false; // Cancel the form submission
        }
        
        // Show loading state on the button
        const submitBtn = $(this).find('button[type="submit"]');
        const originalText = submitBtn.html();
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
        
        // Store the original button content to restore if there's an error
        $(this).data('original-button', originalText);
        
        return true; // Allow the form to submit
    });
    
    // Re-enable the button if the form submission fails
    $(document).ajaxError(function(event, jqXHR, settings, error) {
        $('form[onsubmit*="confirm"]').each(function() {
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = $(this).data('original-button');
            if (originalText) {
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
});
</script>

<?php
// Include footer
include 'includes/footer.php';
?>