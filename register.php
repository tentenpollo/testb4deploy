<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    header("Location: users/user_dashboard.php");
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');

    // Validate form data
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (empty($first_name)) {
        $errors[] = "First name is required";
    }

    if (empty($last_name)) {
        $errors[] = "Last name is required";
    }

    if (empty($errors)) {
        $db = db_connect();
        $email_escaped = $db->real_escape_string($email);
        $query = "SELECT user_id FROM users WHERE email = '$email_escaped'";
        $result = $db->query($query);

        if ($result && $result->num_rows > 0) {
            $errors[] = "Email already registered. Please login instead.";
        }
    }

    if (empty($errors)) {
        $db = db_connect();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $email_escaped = $db->real_escape_string($email);
        $password_hash_escaped = $db->real_escape_string($password_hash);
        $first_name_escaped = $db->real_escape_string($first_name);
        $last_name_escaped = $db->real_escape_string($last_name);
        $username = strtolower(substr($first_name, 0, 1) . $last_name);
        $username_escaped = $db->real_escape_string($username);
        $phone_escaped = $db->real_escape_string($phone);

        $base_username = $username;
        $counter = 1;

        while (true) {
            $username_escaped = $db->real_escape_string($username);
            $query = "SELECT user_id FROM users WHERE username = '$username_escaped'";
            $result = $db->query($query);

            if ($result && $result->num_rows > 0) {
                $username = $base_username . $counter;
                $counter++;
            } else {
                break;
            }
        }

        $query = "
            INSERT INTO users (email, password, first_name, last_name, username, phone) 
            VALUES ('$email_escaped', '$password_hash_escaped', '$first_name_escaped', '$last_name_escaped', '$username_escaped', '$phone_escaped')
        ";

        if ($db->query($query)) {
            $success = true;

            // Log the user in
            $user_id = $db->insert_id;
            $_SESSION['user_id'] = $user_id;

            // Redirect to dashboard or redirect URL
            $redirect = 'users/user_dashboard';

            header("Location: $redirect");
            exit;
        } else {
            $errors[] = "Registration failed. Please try again. Error: " . $db->error;
        }
    }
}

$pageTitle = "Register";
include 'includes/header.php';
?>

<div class="form-container">
    <div class="form-card">
        <h1>Create an Account</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                Registration successful! You are now logged in.
            </div>
        <?php else: ?>
            <form method="post" action="register.php" class="register-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" class="form-control"
                            value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                            required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" class="form-control"
                            value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                            required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number (optional)</label>
                    <input type="tel" id="phone" name="phone" class="form-control"
                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <small class="form-text">Must be at least 8 characters long</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>

                <div class="form-footer">
                    Already have an account? <a href="login.php">Login</a><br>
                    Or <a href="guest.php">continue as guest</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>