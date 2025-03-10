<?php
require_once '../includes/config.php';

function addPriority($name, $level)
{
    global $conn;

    $name = mysqli_real_escape_string($conn, $name);
    $level = (int) $level;

    $sql = "INSERT INTO priorities (name, level, created_at, updated_at) 
            VALUES ('$name', $level, current_timestamp(), current_timestamp())";

    return mysqli_query($conn, $sql);
}

function updatePriority($id, $name, $level)
{
    global $conn;

    $id = (int) $id;
    $name = mysqli_real_escape_string($conn, $name);
    $level = (int) $level;

    $sql = "UPDATE priorities 
            SET name = '$name', level = $level, updated_at = current_timestamp() 
            WHERE id = $id";

    return mysqli_query($conn, $sql);
}

// Function to delete a priority
function deletePriority($id)
{
    global $conn;

    $id = (int) $id;
    $sql = "DELETE FROM priorities WHERE id = $id";

    return mysqli_query($conn, $sql);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['priority_action'])) {
    if ($_POST['priority_action'] == 'add') {
        // Add new priority logic
        $name = trim($_POST['name']);
        $level = (int) $_POST['level'];

        // Validate inputs
        if (!empty($name) && $level >= 0) {
            if (addPriority($name, $level)) {
                $success_msg = "Priority added successfully!";
            } else {
                $error_msg = "Error adding priority: " . mysqli_error($conn);
            }
        } else {
            $error_msg = "Please fill all required fields.";
        }
    } elseif ($_POST['priority_action'] == 'edit' && isset($_POST['priority_id'])) {
        // Edit priority logic
        $priority_id = (int) $_POST['priority_id'];
        $name = trim($_POST['name']);
        $level = (int) $_POST['level'];

        if (updatePriority($priority_id, $name, $level)) {
            $success_msg = "Priority updated successfully!";
        } else {
            $error_msg = "Error updating priority: " . mysqli_error($conn);
        }
    } elseif ($_POST['priority_action'] == 'delete' && isset($_POST['priority_id'])) {
        // Delete priority logic
        $priority_id = (int) $_POST['priority_id'];

        if (deletePriority($priority_id)) {
            $success_msg = "Priority deleted successfully!";
        } else {
            $error_msg = "Error deleting priority: " . mysqli_error($conn);
        }
    }
}

// Get all priorities from database
$priorities = getAllPriorities();
?>

<!-- Priorities Management Content -->
<div x-data="priorityManager()" x-show="activeView === 'priorities'" class="p-6 space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Priority Management</h1>
        <button @click="showAddPriorityModal = true"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
            <i class="fas fa-plus mr-2"></i> Add Priority
        </button>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($success_msg)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?php echo $success_msg; ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($error_msg)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?php echo $error_msg; ?></p>
        </div>
    <?php endif; ?>

    <!-- Priorities Table -->
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level
                        </th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Created</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Updated</th>
                        <th scope="col"
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($priorities)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No priorities found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($priorities as $priority): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $priority['id']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php
                                        $level = isset($priority['level']) ? $priority['level'] : 3;
                                        $color_class = 'bg-green-500'; // Low (level 3)
                                        if ($level == 2)
                                            $color_class = 'bg-yellow-500'; // Medium (level 2)
                                        if ($level == 1)
                                            $color_class = 'bg-orange-500'; // High (level 1)
                                        if ($level == 0)
                                            $color_class = 'bg-red-500'; // Critical (level 0, if you add it later)
                                        ?>
                                        <div class="h-3 w-3 rounded-full <?php echo $color_class; ?> mr-2"></div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($priority['name']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $priority['level']; ?>
                                    <span class="text-xs text-gray-400">(Lower = Higher Priority)</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $priority['created_at']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo $priority['updated_at']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button @click="editPriority(<?php echo htmlspecialchars(json_encode($priority)); ?>)"
                                            class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button
                                            @click="deletePriority(<?php echo $priority['id']; ?>, '<?php echo htmlspecialchars($priority['name']); ?>')"
                                            class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Priority Modal -->
    <div x-show="showAddPriorityModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showAddPriorityModal" @click="showAddPriorityModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showAddPriorityModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="priority_action" value="add">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Priority</h3>
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Priority Name</label>
                            <input type="text" name="name" id="name"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required>
                        </div>
                        <div class="mb-4">
                            <label for="level" class="block text-sm font-medium text-gray-700 mb-1">Priority
                                Level</label>
                            <input type="number" name="level" id="level"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required min="0" max="10">
                            <p class="mt-1 text-sm text-gray-500">Lower number means higher priority (0 = highest)</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Add Priority
                        </button>
                        <button type="button" @click="showAddPriorityModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Priority Modal -->
    <div x-show="showEditPriorityModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditPriorityModal" @click="showEditPriorityModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showEditPriorityModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="priority_action" value="edit">
                    <input type="hidden" name="priority_id" x-bind:value="currentPriority.id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Priority</h3>
                        <div class="mb-4">
                            <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Priority
                                Name</label>
                            <input type="text" name="name" id="edit_name"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required x-bind:value="currentPriority.name">
                        </div>
                        <div class="mb-4">
                            <label for="edit_level" class="block text-sm font-medium text-gray-700 mb-1">Priority
                                Level</label>
                            <input type="number" name="level" id="edit_level"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required min="0" max="10" x-bind:value="currentPriority.level">
                            <p class="mt-1 text-sm text-gray-500">Lower number means higher priority (0 = highest)</p>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Update Priority
                        </button>
                        <button type="button" @click="showEditPriorityModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Priority Confirmation Modal -->
    <div x-show="showDeletePriorityModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showDeletePriorityModal" @click="showDeletePriorityModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showDeletePriorityModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="priority_action" value="delete">
                    <input type="hidden" name="priority_id" x-bind:value="priorityToDelete.id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Priority</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete the priority "<span
                                            x-text="priorityToDelete.name"></span>"? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Delete
                        </button>
                        <button type="button" @click="showDeletePriorityModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Alpine.js component for priority management
    document.addEventListener('alpine:init', () => {
        Alpine.data('priorityManager', () => ({
            showAddPriorityModal: false,
            showEditPriorityModal: false,
            showDeletePriorityModal: false,
            currentPriority: {},
            priorityToDelete: { id: null, name: '' },

            editPriority(priority) {
                this.currentPriority = priority;
                this.showEditPriorityModal = true;
            },

            deletePriority(id, name) {
                this.priorityToDelete = { id, name };
                this.showDeletePriorityModal = true;
            }
        }));
    });
</script>