<?php
require_once 'includes/init.php';
require_once 'includes/db_connect.php';

$page_title = 'Contact Us';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Simple validation
    if (empty($name) || empty($email) || empty($message)) {
        header('Location: contact.php?error=Please fill in all required fields');
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: contact.php?error=Please enter a valid email address');
        exit();
    }
    
    // Save to database (you can customize this part)
    $sql = "INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
    
    if ($stmt->execute()) {
        header('Location: contact.php?success=1');
        exit();
    } else {
        header('Location: contact.php?error=Failed to send message. Please try again.');
        exit();
    }
}
?>

<?php include 'includes/header.php'; ?>

<!-- Simple alert messages -->


<div class="container my-5" style="min-height: 60vh;">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="text-center mb-4">Contact Us</h1>
            
            <div class="card shadow-sm mb-5">
                <div class="card-body p-4">
                    <form id="contactForm" method="post" action="" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Your Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                       data-required="true"
                                       data-error="Please enter your name">
                                <div class="invalid-feedback">Please enter your name</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       data-error="Please enter a valid email address">
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number <small class="text-muted">(Optional)</small></label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject <small class="text-muted">(Optional)</small></label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Your Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="4" 
                                     data-required="true"
                                     data-error="Please enter your message"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                            <div class="invalid-feedback">Please enter your message</div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                                <i class="bi bi-send me-2"></i> Send Message
                            </button>
                        </div>
                        
                        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    alert('Your message has been sent successfully!');
                                    // Remove the success parameter from URL without refreshing
                                    window.history.replaceState({}, document.title, window.location.pathname);
                                });
                            </script>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['error'])): ?>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    alert('<?php echo addslashes(htmlspecialchars($_GET['error'])); ?>');
                                    // Remove the error parameter from URL without refreshing
                                    window.history.replaceState({}, document.title, window.location.pathname);
                                });
                            </script>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alert container removed for simple alerts -->
<?php include 'includes/footer.php'; ?>

<script>
// Simple form validation with alert messages
document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    
    // Simple alert message
    function showMessage(message) {
        alert(message);
    }
    
    // Form submission
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Field validations
            const nameField = document.getElementById('name');
            const emailField = document.getElementById('email');
            const messageField = document.getElementById('message');
            
            // Reset all error states
            contactForm.querySelectorAll('.is-invalid').forEach(el => {
                el.classList.remove('is-invalid');
            });
            
            // Name validation
            if (!nameField.value.trim()) {
                showMessage('Please enter your name');
                nameField.focus();
                return false;
            }
            
            // Email validation
            if (!emailField.value.trim()) {
                showMessage('Please enter your email address');
                emailField.focus();
                return false;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailField.value.trim())) {
                showMessage('Please enter a valid email address');
                emailField.focus();
                return false;
            }
            
            // Message validation
            if (!messageField.value.trim()) {
                showMessage('Please enter your message');
                messageField.focus();
                return false;
            }
            
            // Submit the form
            contactForm.submit();
        });
    }
});
</script>
