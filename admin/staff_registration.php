<?php
require_once '../includes/config.php';

// Check if user is authorized (admin or master_agent)
if (!isset($_SESSION['user_id']) || ($_SESSION['staff_role'] != 'admin' && $_SESSION['staff_role'] != 'master_agent')) {
    // Redirect unauthorized users
    header('Location: ../login.php');
    exit;
}
$mysqli = db_connect();
$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

    // Basic validation
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error_message = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long";
    } else {
        $stmt = $mysqli->prepare("SELECT id FROM staff_members WHERE email = ?");
        if (!$stmt) {
            die("Prepare failed: " . $mysqli->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Email already registered";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $mysqli->prepare("INSERT INTO staff_members (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
            if (!$stmt) {
                die("Prepare failed: " . $mysqli->error);
            }


            if ($stmt->execute()) {
                $success_message = "Staff member registered successfully!";

                $name = $email = $password = $confirm_password = $role = '';
            } else {
                $error_message = "Registration failed: " . $mysqli->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration - Helpdesk</title>
    <script src="//unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<body>
    <div x-show="activeView === 'staff-management'">
        <div class="flex pt-16">
            <main class="flex-1 p-8 bg-gray-50">
                <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md p-6">
                    <h1 class="text-2xl font-bold mb-6 text-gray-800">Staff Member Registration</h1>

                    <?php if ($success_message): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p><?php echo $success_message; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p><?php echo $error_message; ?></p>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                        class="space-y-4">
                        <input type="hidden" name="return_to" value="staff-management">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" id="name" name="name"
                                value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" id="email" name="email"
                                value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" id="password" name="password" required minlength="8"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Must be at least 8 characters long</p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm
                                Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select id="role" name="role" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="" disabled selected>Select a role</option>
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>
                                        Admin</option>
                                    <option value="master_agent" <?php echo (isset($role) && $role === 'master_agent') ? 'selected' : ''; ?>>Master Agent</option>
                                <?php endif; ?>
                                <option value="agent" <?php echo (isset($role) && $role === 'agent') ? 'selected' : ''; ?>>
                                    Support Agent</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="window.location.href='dashboard.php'"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Register Staff Member
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>

</html>