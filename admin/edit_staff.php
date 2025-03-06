<?php
require_once '../includes/config.php';

// Check if user is authorized (admin or master_agent)
if (!isset($_SESSION['user_id']) || ($_SESSION['staff_role'] != 'admin' && $_SESSION['staff_role'] != 'master_agent')) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

$mysqli = db_connect();

if (!isset($_GET['id'])) {
    header('Location: staff_management.php');
    exit;
}

$user_id = (int) $_GET['id'];

// Get user data
$stmt = $mysqli->prepare("SELECT id, name, email, role FROM staff_members WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: staff_management.php');
    exit;
}

$user = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
    $password = $_POST['password']; // Will be hashed if not empty

    // Basic validation
    if (empty($name) || empty($email) || empty($role)) {
        $error_message = "Name, email, and role are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Check if email already exists for other users
        $stmt = $mysqli->prepare("SELECT id FROM staff_members WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Email already registered for another user";
        } else {
            // Update user
            if (!empty($password)) {
                // If password is provided, update all fields including password
                if (strlen($password) < 8) {
                    $error_message = "Password must be at least 8 characters long";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $mysqli->prepare("UPDATE staff_members SET name = ?, email = ?, password = ?, role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->bind_param("ssssi", $name, $email, $hashed_password, $role, $user_id);
                }
            } else {
                // If no password is provided, update only name, email, and role
                $stmt = $mysqli->prepare("UPDATE staff_members SET name = ?, email = ?, role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("sssi", $name, $email, $role, $user_id);
            }

            if (!isset($error_message) && $stmt->execute()) {
                $success_message = "Staff member updated successfully!";

                $user['name'] = $name;
                $user['email'] = $email;
                $user['role'] = $role;
            } else if (!isset($error_message)) {
                $error_message = "Update failed: " . $mysqli->error;
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
    <title>Edit Staff Member - Helpdesk</title>
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
                    <h1 class="text-2xl font-bold mb-6 text-gray-800">Edit Staff Member</h1>

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

                    <form method="POST"
                        action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $user_id); ?>"
                        class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
                            <input type="text" id="name" name="name"
                                value="<?php echo htmlspecialchars($user['name']); ?>" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                            <input type="email" id="email" name="email"
                                value="<?php echo htmlspecialchars($user['email']); ?>" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input type="password" id="password" name="password"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password. New password
                                must be at least 8 characters long.</p>
                        </div>

                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                            <select id="role" name="role" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                    <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>Admin
                                    </option>
                                    <option value="master_agent" <?php echo ($user['role'] === 'master_agent') ? 'selected' : ''; ?>>Master Agent</option>
                                <?php endif; ?>
                                <option value="agent" <?php echo ($user['role'] === 'agent') ? 'selected' : ''; ?>>Support
                                    Agent</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="window.location.href='staff_management.php'"
                                class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Update Staff Member
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>

</html>