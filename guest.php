<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (is_logged_in() || is_guest()) {
    header("Location: create_ticket.php");
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($first_name)) {
        $errors[] = "First name is required";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }
    
    // Create guest user if no errors
    if (empty($errors)) {
        $db = db_connect();
        
        // Generate a unique token for the guest
        $token = generate_token();
        
        // Set expiration time (e.g., 7 days from now)
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt = $db->prepare("
            INSERT INTO guest_users (email, token, first_name, last_name, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$email, $token, $first_name, $last_name, $expires_at])) {
            $success = true;
            
            // Store guest info in session
            $_SESSION['guest_id'] = $db->lastInsertId();
            $_SESSION['guest_token'] = $token;
            
            // Redirect to create ticket page
            header("Location: create_ticket.php");
            exit;
        } else {
            $errors[] = "Failed to create guest access. Please try again.";
        }
    }
}

$pageTitle = "Continue as Guest";
include 'includes/header.php';
?>

<div class="form-container">
    <div class="form-card">
        <h1>Continue as Guest</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p>
                <i class="fas fa-info-circle"></i> You can create and track a support ticket without an account.
                You'll receive a unique link to access your ticket via email.
            </p>
        </div>
        
        <form method="post" action="guest.php" class="guest-form" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                <small class="form-text">We'll send ticket updates to this email</small>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Continue to Ticket</button>
            </div>
            
            <div class="form-footer">
                Want to create an account instead? <a href="register.php">Register</a><br>
                Already have an account? <a href="login.php">Login</a>
            </div>
        </form>
    </div>
</div>

<script>
function validateForm() {
    const email = document.getElementById('email').value.trim();
    const firstName = document.getElementById('first_name').value.trim();
    const lastName = document.getElementById('last_name').value.trim();
    
    if (!email || !firstName || !lastName) {
        alert('Please fill out all fields.');
        return false;
    }
    
    if (!validateEmail(email)) {
        alert('Please enter a valid email address.');
        return false;
    }
    
    return true;
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
}
</script>

<?php include 'includes/footer.php'; ?>