<?php
require_once '../includes/config.php';

$edit_input = [];
if (isset($_SESSION['edit_input'])) {
    $edit_input = $_SESSION['edit_input'];
    unset($_SESSION['edit_input']);
}

if (isset($_SESSION['registration_error'])) {
    $error_message = $_SESSION['registration_error'];
    unset($_SESSION['registration_error']);
}

if (isset($_SESSION['registration_success'])) {
    $success_message = "Staff member <strong>" . htmlspecialchars($_SESSION['registered_user']) . "</strong> registered successfully!";
    unset($_SESSION['registration_success']);
    unset($_SESSION['registered_user']);
}

$mysqli = db_connect();


// Handle user deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];

    if ($user_id === $_SESSION['user_id']) {
        $error_message = "You cannot delete your own account";
    } else {
        $stmt = $mysqli->prepare("DELETE FROM staff_members WHERE id = ?");
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $success_message = "Staff member deleted successfully";
        } else {
            $error_message = "Failed to delete staff member: " . $mysqli->error;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_staff'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error_message = "Password must be at least 8 characters long";
    } else {
        // Check if email exists
        $stmt = $mysqli->prepare("SELECT id FROM staff_members WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Email already registered";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO staff_members (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);

            if ($stmt->execute()) {
                $_SESSION['registration_success'] = true;
                $_SESSION['registered_user'] = $name;
                header("Location: dashboard.php?view=staff-management");
                exit;
            } else {
                $error_message = "Registration failed: " . $mysqli->error;
            }
        }
    }

    if (isset($error_message)) {
        $_SESSION['registration_error'] = $error_message;
        $_SESSION['registration_input'] = [
            'name' => $name,
            'email' => $email,
            'role' => $role
        ];
        header('Location: dashboard.php?view=staff-management');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_staff') {
    $edit_user_id = (int) $_POST['edit_user_id'];
    $name = filter_input(INPUT_POST, 'edit_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'edit_email', FILTER_SANITIZE_EMAIL);
    $role = filter_input(INPUT_POST, 'edit_role', FILTER_SANITIZE_STRING);
    $password = $_POST['edit_password'];

    if (empty($name) || empty($email) || empty($role)) {
        $error_message = "Name, email, and role are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Check if email already exists for other users
        $stmt = $mysqli->prepare("SELECT id FROM staff_members WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $edit_user_id);
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
                    $stmt->bind_param("ssssi", $name, $email, $hashed_password, $role, $edit_user_id);
                }
            } else {
                // If no password is provided, update only name, email, and role
                $stmt = $mysqli->prepare("UPDATE staff_members SET name = ?, email = ?, role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("sssi", $name, $email, $role, $edit_user_id);
            }

            if (!isset($error_message) && $stmt->execute()) {
                $success_message = "Staff member <strong>{$name}</strong> updated successfully!";

                // Store a flag in the session to keep the edit modal open in case of errors
                $_SESSION['edit_success'] = true;
                $_SESSION['edited_user'] = $name;


                header('Location: dashboard.php?view=staff-management');
                exit;
            } else if (!isset($error_message)) {
                $error_message = "Update failed: " . $mysqli->error;

                // Store a flag in the session to keep the edit modal open in case of errors
                $_SESSION['edit_error'] = true;
            }
        }
    }
}

if (isset($_GET['edit_success']) && $_GET['edit_success'] == 1 && isset($_GET['user'])) {
    $success_message = "Staff member <strong>" . htmlspecialchars($_GET['user']) . "</strong> updated successfully!";
}

$sql = "SELECT id, name, email, role, created_at FROM staff_members WHERE id != ? ORDER BY created_at DESC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$staff_members = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Helpdesk</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>

<div x-data="{ 
    showModal: <?php echo isset($_GET['show_modal']) ? 'true' : 'false'; ?>,
    showEditModal: <?php echo isset($_SESSION['edit_error']) ? 'true' : 'false'; ?>,
    editUserId: <?php echo isset($edit_input['id']) ? $edit_input['id'] : 'null'; ?>,
    editName: '<?php echo isset($edit_input['name']) ? addslashes($edit_input['name']) : ''; ?>',
    editEmail: '<?php echo isset($edit_input['email']) ? addslashes($edit_input['email']) : ''; ?>',
    editRole: '<?php echo isset($edit_input['role']) ? addslashes($edit_input['role']) : ''; ?>',
    editSuccess: <?php echo isset($_GET['edit_success']) ? 'true' : 'false'; ?>,
    editedUser: '<?php echo isset($_GET['user']) ? htmlspecialchars($_GET['user']) : ''; ?>'
}" x-init="() => {
    
    <?php if (isset($_SESSION['edit_error'])): ?>
    // Clear the session flag
    <?php unset($_SESSION['edit_error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['edit_success'])): ?>
    // Clear the session flag
    <?php unset($_SESSION['edit_success']); ?>
    <?php unset($_SESSION['edited_user']); ?>
    
    // Show a toast notification for success
    if (editSuccess) {
        setTimeout(() => {
            editSuccess = false;
        }, 5000); // Hide after 5 seconds
    }
    <?php endif; ?>
}">
    <div class="flex pt-16">
        <main class="flex-1 p-8 bg-gray-50" :class="{ 'blur-sm': showModal || showEditModal }">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">Staff Management</h1>
                    <button @click="showModal = true"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-plus mr-2"></i> Add New Staff
                    </button>
                </div>

                <div class="bg-white shadow-md rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Email</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Role</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Date Added</th>
                                <th scope="col"
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (count($staff_members) > 0): ?>
                                <?php foreach ($staff_members as $staff): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div
                                                        class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                        <i class="fas fa-user text-gray-500"></i>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($staff['name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo htmlspecialchars($staff['email']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php
                                                if ($staff['role'] == 'admin')
                                                    echo 'bg-purple-100 text-purple-800';
                                                elseif ($staff['role'] == 'master_agent')
                                                    echo 'bg-green-100 text-green-800';
                                                else
                                                    echo 'bg-blue-100 text-blue-800';
                                                ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($staff['role']))); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M d, Y', strtotime($staff['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button @click="
    showEditModal = true; 
    editUserId = <?php echo $staff['id']; ?>;
    editName = '<?php echo addslashes(htmlspecialchars($staff['name'])); ?>';
    editEmail = '<?php echo addslashes(htmlspecialchars($staff['email'])); ?>';
    editRole = '<?php echo addslashes(htmlspecialchars($staff['role'])); ?>';
    
    $nextTick(() => {
        document.getElementById('edit_name').value = editName;
        document.getElementById('edit_email').value = editEmail;
        document.getElementById('edit_role').value = editRole;
    });" class="text-indigo-600 hover:text-indigo-900 mr-3 bg-transparent border-none cursor-pointer focus:outline-none">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <!-- Delete action with confirmation dialog using Alpine.js -->
                                            <div x-data="{ showConfirm: false }" class="inline-block">
                                                <button @click="showConfirm = true" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>

                                                <!-- Confirmation Modal -->
                                                <div x-show="showConfirm"
                                                    class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center"
                                                    style="background-color: rgba(0,0,0,0.5);">
                                                    <div class="relative bg-white rounded-lg max-w-md w-full p-6"
                                                        @click.away="showConfirm = false">
                                                        <div class="mb-4">
                                                            <h3 class="text-lg font-medium text-gray-900">Confirm Deletion
                                                            </h3>
                                                            <p class="text-sm text-gray-500 mt-2">
                                                                Are you sure you want to delete the staff member
                                                                <strong><?php echo htmlspecialchars($staff['name']); ?></strong>?
                                                                This action cannot be undone.
                                                            </p>
                                                        </div>
                                                        <div class="flex justify-end space-x-3">
                                                            <button @click="showConfirm = false"
                                                                class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md">
                                                                Cancel
                                                            </button>
                                                            <a href="?action=delete&id=<?php echo $staff['id']; ?>"
                                                                class="px-4 py-2 bg-red-600 text-white rounded-md">
                                                                Delete
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                        No staff members found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div x-show="showModal" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center"
        style="background-color: rgba(0,0,0,0.5);">
        <div class="relative bg-white rounded-lg max-w-2xl w-full p-6 shadow-lg" @click.away="showModal = false">

            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Staff Member Registration</h2>
                <button @click="showModal = false" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

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

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-4">
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
                        <?php if ($_SESSION['staff_role'] === 'admin'): ?>
                            <option value="admin" <?php echo (isset($role) && $role === 'admin') ? 'selected' : ''; ?>>
                                Admin</option>
                            <option value="master_agent" <?php echo (isset($role) && $role === 'master_agent') ? 'selected' : ''; ?>>Master Agent</option>
                        <?php endif; ?>
                        <option value="agent" <?php echo (isset($role) && $role === 'agent') ? 'selected' : ''; ?>>
                            Support Agent</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" @click="showModal = false"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Cancel
                    </button>
                    <button type="submit" name="register_staff"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Register Staff Member
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div x-show="showEditModal" class="fixed inset-0 overflow-y-auto z-50 flex items-center justify-center"
        style="background-color: rgba(0,0,0,0.5);">
        <div class="relative bg-white rounded-lg max-w-2xl w-full p-6 shadow-lg" @click.away="showEditModal = false">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold text-gray-800">Edit Staff Member</h2>
                <button @click="showEditModal = false" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="editStaffForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>"
                class="space-y-4" onsubmit="logFormData(event)">
                <input type="hidden" id="edit_user_id" name="edit_user_id" x-bind:value="editUserId">
                <input type="hidden" name="action" value="update_staff">

                <div>
                    <label for="edit_name" class="block text-sm font-medium text-gray-700">Full Name</label>
                    <input type="text" id="edit_name" name="edit_name" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="edit_email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="edit_email" name="edit_email" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="edit_password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="edit_password" name="edit_password"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password. New password
                        must be at least 8 characters long.</p>
                </div>

                <div>
                    <label for="edit_role" class="block text-sm font-medium text-gray-700">Role</label>
                    <select id="edit_role" name="edit_role" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <?php if ($_SESSION['staff_role'] === 'admin'): ?>
                            <option value="admin">Admin</option>
                            <option value="master_agent">Master Agent</option>
                        <?php endif; ?>
                        <option value="agent">Support Agent</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" @click="showEditModal = false"
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
    </div>

    <div x-show="editSuccess" x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 transform translate-y-2"
        x-transition:enter-end="opacity-100 transform translate-y-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100 transform translate-y-0"
        x-transition:leave-end="opacity-0 transform translate-y-2"
        class="fixed bottom-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-lg z-50 max-w-md"
        @click="editSuccess = false">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium">
                    Staff member <span x-text="editedUser"></span> updated successfully!
                </p>
            </div>
            <div class="ml-auto pl-3">
                <div class="-mx-1.5 -my-1.5">
                    <button @click="editSuccess = false"
                        class="text-green-500 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function logFormData(event) {
        event.preventDefault(); // Prevent the form from submitting immediately

        const form = event.target;
        const formData = new FormData(form);

        console.log('Form Data:');
        for (let [key, value] of formData.entries()) {
            console.log(`${key}: ${value}`);
        }

        form.submit();
    }
</script>

</html>