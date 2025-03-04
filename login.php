<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if already logged in
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $errors[] = "Both email and password are required";
    } else {
        $db = db_connect();
        $stmt = $db->prepare("SELECT user_id, password_hash FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Login successful
            $_SESSION['user_id'] = $user['user_id'];
            
            // Update last login time
            $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
            $updateStmt->execute([$user['user_id']]);
            
            // Redirect to appropriate page
            $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
            unset($_SESSION['redirect_after_login']);
            
            header("Location: $redirect");
            exit;
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}

$pageTitle = "Login";
include 'includes/header.php';
?>

<div class="form-container">
    <div class="form-card">
        <h1>Login to Your Account</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="post" action="login.php" class="login-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
            
            <div class="form-footer">
                Don't have an account? <a href="register.php">Register</a><br>
                Or <a href="guest.php">continue as guest</a>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>