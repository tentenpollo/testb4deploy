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
        $email_escaped = $db->real_escape_string($email);
        
        // First check if it's a staff member
        $staff_query = "SELECT id, password, role FROM staff_members WHERE email = '$email_escaped'";
        $staff_result = $db->query($staff_query);
        
        if ($staff_result && $staff_result->num_rows > 0) {
            // User found in staff_members table
            $staff = $staff_result->fetch_assoc();
            
            if (password_verify($password, $staff['password'])) {
                // Set session variables for staff
                $_SESSION['user_id'] = $staff['id'];
                $_SESSION['is_staff'] = true;
                $_SESSION['staff_role'] = $staff['role'];
                
                // Update last login (using updated_at field for staff)
                $staff_id_escaped = $db->real_escape_string($staff['id']);
                $update_query = "UPDATE staff_members SET updated_at = CURRENT_TIMESTAMP() WHERE id = '$staff_id_escaped'";
                $db->query($update_query);
                
                $redirect = $_SESSION['redirect_after_login'] ?? 'admin/dashboard.php';
                unset($_SESSION['redirect_after_login']);
                
                header("Location: $redirect");
                exit;
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            // Not found in staff, check normal users
            $user_query = "SELECT user_id, password FROM users WHERE email = '$email_escaped'";
            $user_result = $db->query($user_query);
            
            if ($user_result && $user_result->num_rows > 0) {
                $user = $user_result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    // Set session variables for regular user
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['is_staff'] = false;
                    
                    // Update last login
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