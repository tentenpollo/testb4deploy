<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if already logged in
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $errors[] = "Both email and password are required";
    } else {
        $db = db_connect();
        
        // Using mysqli instead of deprecated mysql extension
        $email_escaped = $db->real_escape_string($email);
        $query = "SELECT user_id, password_hash FROM users WHERE email = '$email_escaped' AND is_active = TRUE";
        $result = $db->query($query);
        
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['user_id'];
                
                $user_id_escaped = $db->real_escape_string($user['user_id']);
                $update_query = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = '$user_id_escaped'";
                $db->query($update_query);
                
                $redirect = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
                unset($_SESSION['redirect_after_login']);
                
                header("Location: $redirect");
                exit;
            } else {
                $errors[] = "Invalid email or password";
            }
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