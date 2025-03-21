<?php
require_once '../includes/config.php';
$conn = db_connect();
function addCategory($name, $description)
{
    global $conn;

    $name = mysqli_real_escape_string($conn, $name);
    $description = mysqli_real_escape_string($conn, $description);

    $sql = "INSERT INTO categories (name, description, created_at, updated_at) 
            VALUES ('$name', '$description', current_timestamp(), current_timestamp())";

    return mysqli_query($conn, $sql);
}


function updateCategory($id, $name, $description)
{
    global $conn;

    $id = (int) $id;
    $name = mysqli_real_escape_string($conn, $name);
    $description = mysqli_real_escape_string($conn, $description);

    $sql = "UPDATE categories 
            SET name = '$name', description = '$description', updated_at = current_timestamp() 
            WHERE id = $id";

    return mysqli_query($conn, $sql);
}


function deleteCategory($id)
{
    global $conn;

    $id = (int) $id;


    $check_sql = "SELECT COUNT(*) as count FROM subcategories WHERE category_id = $id";
    $result = mysqli_query($conn, $check_sql);
    $row = mysqli_fetch_assoc($result);

    if ($row['count'] > 0) {
        return false;
    }

    $sql = "DELETE FROM categories WHERE id = $id";
    return mysqli_query($conn, $sql);
}


function addSubcategory($name, $description, $category_id)
{
    global $conn;

    $name = mysqli_real_escape_string($conn, $name);
    $description = mysqli_real_escape_string($conn, $description);
    $category_id = (int) $category_id;

    $sql = "INSERT INTO subcategories (name, description, category_id, created_at, updated_at) 
            VALUES ('$name', '$description', $category_id, current_timestamp(), current_timestamp())";

    return mysqli_query($conn, $sql);
}


function updateSubcategory($id, $name, $description, $category_id)
{
    global $conn;

    $id = (int) $id;
    $name = mysqli_real_escape_string($conn, $name);
    $description = mysqli_real_escape_string($conn, $description);
    $category_id = (int) $category_id;

    $sql = "UPDATE subcategories 
            SET name = '$name', description = '$description', category_id = $category_id, updated_at = current_timestamp() 
            WHERE id = $id";

    return mysqli_query($conn, $sql);
}


function deleteSubcategory($id)
{
    global $conn;

    $id = (int) $id;
    $sql = "DELETE FROM subcategories WHERE id = $id";

    return mysqli_query($conn, $sql);
}

function getAllCategories1()
{
    global $conn;

    $sql = "SELECT id, name, description, created_at, updated_at FROM categories ORDER BY id";
    $result = $conn->query($sql);

    $categories = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }

    return $categories;
}

function getAllSubcategories()
{
    global $conn;

    $sql = "SELECT s.*, c.name as category_name 
            FROM subcategories s
            JOIN categories c ON s.category_id = c.id
            ORDER BY s.category_id ASC, s.id ASC";
    $result = $conn->query($sql);

    $subcategories = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $subcategories[] = $row;
        }
    }

    return $subcategories;
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['category_action'])) {
        if ($_POST['category_action'] == 'add') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);

            if (!empty($name)) {
                if (addCategory($name, $description)) {
                    $_SESSION['success_msg'] = "Category added successfully!";
                    header("Location: dashboard.php?view=categories");
                    exit();
                } else {
                    $_SESSION['error_msg'] = "Error adding category: " . mysqli_error($conn);
                }
            } else {
                $_SESSION['error_msg'] = "Category name is required.";
            }
        } elseif ($_POST['category_action'] == 'edit' && isset($_POST['category_id'])) {
            $category_id = (int) $_POST['category_id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);

            if (updateCategory($category_id, $name, $description)) {
                $_SESSION['success_msg'] = "Category updated successfully!";
                header("Location: dashboard.php?view=categories");
                exit();
            } else {
                $_SESSION['error_msg'] = "Error updating category: " . mysqli_error($conn);
            }
        } elseif ($_POST['category_action'] == 'delete' && isset($_POST['category_id'])) {
            $category_id = (int) $_POST['category_id'];

            if (deleteCategory($category_id)) {
                $_SESSION['success_msg'] = "Category deleted successfully!";
                header("Location: dashboard.php?view=categories");
                exit();
            } else {
                $_SESSION['error_msg'] = "Error deleting category. Make sure there are no subcategories associated with this category.";
            }
        }
    }

    if (isset($_POST['subcategory_action'])) {
        if ($_POST['subcategory_action'] == 'add') {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $category_id = (int) $_POST['category_id'];

            if (!empty($name) && $category_id > 0) {
                if (addSubcategory($name, $description, $category_id)) {
                    $_SESSION['success_msg'] = "Subcategory added successfully!";
                    header("Location: dashboard.php?view=categories");
                    exit();
                } else {
                    $_SESSION['error_msg'] = "Error adding subcategory: " . mysqli_error($conn);
                }
            } else {
                $_SESSION['error_msg'] = "Subcategory name and category are required.";
            }
        } elseif ($_POST['subcategory_action'] == 'edit' && isset($_POST['subcategory_id'])) {
            $subcategory_id = (int) $_POST['subcategory_id'];
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $category_id = (int) $_POST['category_id'];

            if (updateSubcategory($subcategory_id, $name, $description, $category_id)) {
                $_SESSION['success_msg'] = "Subcategory updated successfully!";
                header("Location: dashboard.php?view=categories");
                exit();
            } else {
                $_SESSION['error_msg'] = "Error updating subcategory: " . mysqli_error($conn);
            }
        } elseif ($_POST['subcategory_action'] == 'delete' && isset($_POST['subcategory_id'])) {
            $subcategory_id = (int) $_POST['subcategory_id'];

            if (deleteSubcategory($subcategory_id)) {
                $_SESSION['success_msg'] = "Subcategory deleted successfully!";
                header("Location: dashboard.php?view=categories");
                exit();
            } else {
                $_SESSION['error_msg'] = "Error deleting subcategory: " . mysqli_error($conn);
            }
        }
    }
}

$categories = getAllCategories1();
$subcategories = getAllSubcategories();
?>

<!-- Categories and Subcategories Management Content -->
<div x-data="categoryManager()" x-show="activeView === 'categories'" class="p-6 space-y-6">
    <!-- Categories Management Section -->
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Category Management</h1>
        <div class="flex space-x-2">
            <button @click="showAddCategoryModal = true"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> Add Category
            </button>
            <button @click="showAddSubcategoryModal = true"
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                <i class="fas fa-plus mr-2"></i> Add Subcategory
            </button>
        </div>
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

    <!-- Replace the Categories Table with this expanded/collapsible view -->
    <div x-show="activeTab === 'categories'" class="bg-white shadow-md rounded-lg overflow-hidden">
        <?php if (empty($categories)): ?>
            <div class="px-6 py-4 text-center text-sm text-gray-500">No categories found</div>
        <?php else: ?>
            <div class="space-y-4 p-4">
                <?php foreach ($categories as $category): ?>
                    <!-- Category Card with Expandable Subcategories -->
                    <div class="border border-gray-200 rounded-lg overflow-hidden" x-data="{ expanded: false }">
                        <!-- Category Header -->
                        <div class="bg-gray-50 px-4 py-3 flex items-center justify-between cursor-pointer"
                            @click="expanded = !expanded">
                            <div class="flex items-center space-x-2">
                                <button class="text-gray-500 focus:outline-none" aria-label="Toggle category">
                                    <i class="fas" :class="expanded ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                </button>
                                <h3 class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($category['name']); ?>
                                </h3>
                                <span class="text-sm text-gray-500">(ID: <?php echo $category['id']; ?>)</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-500">
                                    <?php
                                    $subcategory_count = 0;
                                    foreach ($subcategories as $subcategory) {
                                        if ($subcategory['category_id'] == $category['id']) {
                                            $subcategory_count++;
                                        }
                                    }
                                    echo $subcategory_count . ' subcategories';
                                    ?>
                                </span>
                                <div class="flex space-x-2 ml-4">
                                    <button @click.stop="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)"
                                        class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button
                                        @click.stop="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')"
                                        class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Category Details and Subcategories -->
                        <div x-show="expanded" x-transition class="border-t border-gray-200">
                            <!-- Category Description -->
                            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                                <div class="flex justify-between">
                                    <div>
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">Description:</span>
                                            <?php echo !empty($category['description']) ? htmlspecialchars($category['description']) : '<span class="italic text-gray-400">No description</span>'; ?>
                                        </p>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <span>Created: <?php echo $category['created_at']; ?></span>
                                        <span class="ml-3">Updated: <?php echo $category['updated_at']; ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Subcategories -->
                            <div class="p-4">
                                <div class="flex justify-between items-center mb-3">
                                    <h4 class="font-medium text-gray-700">Subcategories</h4>
                                    <button
                                        @click.stop="showAddSubcategoryModal = true; document.getElementById('subcategory_category_id').value = '<?php echo $category['id']; ?>'"
                                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md text-sm flex items-center">
                                        <i class="fas fa-plus mr-1"></i> Add Subcategory
                                    </button>
                                </div>

                                <!-- Subcategories List -->
                                <div class="overflow-x-auto">
                                    <?php
                                    $hasSubcategories = false;
                                    foreach ($subcategories as $subcategory) {
                                        if ($subcategory['category_id'] == $category['id']) {
                                            $hasSubcategories = true;
                                            break;
                                        }
                                    }

                                    if (!$hasSubcategories):
                                        ?>
                                        <p class="text-sm text-gray-500 italic py-2">No subcategories found for this category</p>
                                    <?php else: ?>
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col"
                                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        ID</th>
                                                    <th scope="col"
                                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Name</th>
                                                    <th scope="col"
                                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Description</th>
                                                    <th scope="col"
                                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Created</th>
                                                    <th scope="col"
                                                        class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                        Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($subcategories as $subcategory): ?>
                                                    <?php if ($subcategory['category_id'] == $category['id']): ?>
                                                        <tr>
                                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo $subcategory['id']; ?></td>
                                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($subcategory['name']); ?></td>
                                                            <td class="px-4 py-2 text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($subcategory['description']); ?></td>
                                                            <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                                                <?php echo $subcategory['created_at']; ?></td>
                                                            <td class="px-4 py-2 whitespace-nowrap text-sm font-medium">
                                                                <div class="flex space-x-2">
                                                                    <button
                                                                        @click.stop="editSubcategory(<?php echo htmlspecialchars(json_encode($subcategory)); ?>)"
                                                                        class="text-blue-600 hover:text-blue-900">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                    <button
                                                                        @click.stop="deleteSubcategory(<?php echo $subcategory['id']; ?>, '<?php echo htmlspecialchars($subcategory['name']); ?>')"
                                                                        class="text-red-600 hover:text-red-900">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Category Modal -->
    <div x-show="showAddCategoryModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showAddCategoryModal" @click="showAddCategoryModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showAddCategoryModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="category_action" value="add">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Category</h3>
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                            <input type="text" name="name" id="name"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required>
                        </div>
                        <div class="mb-4">
                            <label for="description"
                                class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Add Category
                        </button>
                        <button type="button" @click="showAddCategoryModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div x-show="showEditCategoryModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditCategoryModal" @click="showEditCategoryModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showEditCategoryModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="category_action" value="edit">
                    <input type="hidden" name="category_id" x-bind:value="currentCategory.id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Category</h3>
                        <div class="mb-4">
                            <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Category
                                Name</label>
                            <input type="text" name="name" id="edit_name"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required x-bind:value="currentCategory.name">
                        </div>
                        <div class="mb-4">
                            <label for="edit_description"
                                class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="edit_description" rows="3"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                x-text="currentCategory.description"></textarea>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Update Category
                        </button>
                        <button type="button" @click="showEditCategoryModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Category Confirmation Modal -->
    <div x-show="showDeleteCategoryModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showDeleteCategoryModal" @click="showDeleteCategoryModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showDeleteCategoryModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="category_action" value="delete">
                    <input type="hidden" name="category_id" x-bind:value="categoryToDelete.id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Category</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete the category "<span
                                            x-text="categoryToDelete.name"></span>"? This action cannot be undone.
                                    </p>
                                    <p class="text-sm text-red-500 mt-2">
                                        Note: You cannot delete a category that has subcategories.
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
                        <button type="button" @click="showDeleteCategoryModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Subcategory Modal -->
    <div x-show="showAddSubcategoryModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showAddSubcategoryModal" @click="showAddSubcategoryModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showAddSubcategoryModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="subcategory_action" value="add">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Subcategory</h3>
                        <div class="mb-4">
                            <label for="subcategory_name"
                                class="block text-sm font-medium text-gray-700 mb-1">Subcategory Name</label>
                            <input type="text" name="name" id="subcategory_name"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required>
                        </div>
                        <div class="mb-4">
                            <label for="subcategory_description"
                                class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="subcategory_description" rows="3"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="subcategory_category_id"
                                class="block text-sm font-medium text-gray-700 mb-1">Parent Category</label>
                            <select name="category_id" id="subcategory_category_id"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required>
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Add Subcategory
                        </button>
                        <button type="button" @click="showAddSubcategoryModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Subcategory Modal -->
    <div x-show="showEditSubcategoryModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditSubcategoryModal" @click="showEditSubcategoryModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showEditSubcategoryModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="subcategory_action" value="edit">
                    <input type="hidden" name="subcategory_id" x-bind:value="currentSubcategory.id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Edit Subcategory</h3>
                        <div class="mb-4">
                            <label for="edit_subcategory_name"
                                class="block text-sm font-medium text-gray-700 mb-1">Subcategory Name</label>
                            <input type="text" name="name" id="edit_subcategory_name"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required x-bind:value="currentSubcategory.name">
                        </div>
                        <div class="mb-4">
                            <label for="edit_subcategory_description"
                                class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" id="edit_subcategory_description" rows="3"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                x-text="currentSubcategory.description"></textarea>
                        </div>
                        <div class="mb-4">
                            <label for="edit_subcategory_category_id"
                                class="block text-sm font-medium text-gray-700 mb-1">Parent Category</label>
                            <select name="category_id" id="edit_subcategory_category_id"
                                class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md"
                                required x-bind:value="currentSubcategory.category_id">
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Update Subcategory
                        </button>
                        <button type="button" @click="showEditSubcategoryModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Subcategory Confirmation Modal -->
    <div x-show="showDeleteSubcategoryModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showDeleteSubcategoryModal" @click="showDeleteSubcategoryModal = false"
                class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <div x-show="showDeleteSubcategoryModal"
                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full"
                x-transition>
                <form action="" method="POST">
                    <input type="hidden" name="subcategory_action" value="delete">
                    <input type="hidden" name="subcategory_id" x-bind:value="subcategoryToDelete.id">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-exclamation-triangle text-red-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900">Delete Subcategory</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">
                                        Are you sure you want to delete the subcategory "<span
                                            x-text="subcategoryToDelete.name"></span>"? This action cannot be undone.
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
                        <button type="button" @click="showDeleteSubcategoryModal = false"
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
    function categoryManager() {
        return {
            activeTab: 'categories',
            activeView: 'categories',
            showAddCategoryModal: false,
            showEditCategoryModal: false,
            showDeleteCategoryModal: false,
            showAddSubcategoryModal: false,
            showEditSubcategoryModal: false,
            showDeleteSubcategoryModal: false,
            currentCategory: {
                id: null,
                name: '',
                description: ''
            },
            categoryToDelete: {
                id: null,
                name: ''
            },
            currentSubcategory: {
                id: null,
                name: '',
                description: '',
                category_id: null
            },
            subcategoryToDelete: {
                id: null,
                name: ''
            },

            editCategory(category) {
                this.currentCategory = JSON.parse(JSON.stringify(category));
                this.showEditCategoryModal = true;
            },

            deleteCategory(id, name) {
                this.categoryToDelete = {
                    id: id,
                    name: name
                };
                this.showDeleteCategoryModal = true;
            },

            editSubcategory(subcategory) {
                this.currentSubcategory = JSON.parse(JSON.stringify(subcategory));
                this.showEditSubcategoryModal = true;


                setTimeout(() => {
                    document.getElementById('edit_subcategory_category_id').value = subcategory.category_id;
                }, 100);
            },

            deleteSubcategory(id, name) {
                this.subcategoryToDelete = {
                    id: id,
                    name: name
                };
                this.showDeleteSubcategoryModal = true;
            }
        };
    }
</script>