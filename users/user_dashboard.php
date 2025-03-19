<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a regular user
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_staff']) || $_SESSION['is_staff']) {
    header('Location: ../login.php');
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$mysqli = db_connect();
$stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Helpdesk</title>
    <script src="//unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body x-data="{ 
    searchExpanded: false,
    profileMenuOpen: false,
    activeTab: 'overview',
    showCreateTicket: false
}">
    <!-- Top Navigation Bar -->
    <nav class="navy-bg h-16 flex items-center px-6 fixed w-full z-50">
        <!-- Logo -->
        <div class="text-white font-bold text-xl">HelpDesk</div>

        <!-- Search Bar -->
        <div class="flex-1 flex justify-center mx-4">
            <div class="relative w-full max-w-2xl search-bar" :class="{ 'expanded': searchExpanded }">
                <input type="text" placeholder="Search tickets..." @click="searchExpanded = true"
                    @blur="searchExpanded = false"
                    class="w-full py-2 px-4 rounded-full bg-white shadow-sm focus:outline-none">
                <i class="fas fa-search text-gray-400 absolute right-4 top-3"></i>
            </div>
        </div>

        <!-- Right Icons -->
        <div class="flex items-center space-x-5 ml-auto">
            <div class="relative">
                <button @click="profileMenuOpen = !profileMenuOpen"
                    class="flex items-center space-x-2 text-white hover:text-gray-200 focus:outline-none">
                    <i class="fas fa-user-circle text-2xl"></i>
                    <span><?php echo htmlspecialchars($user['first_name']); ?></span>
                </button>
                <!-- Profile Dropdown -->
                <div x-show="profileMenuOpen" @click.away="profileMenuOpen = false"
                    class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1">
                    <a href="../profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-user mr-2"></i> Profile
                    </a>
                    <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Add this after the nav section -->
    <?php if (isset($_SESSION['success'])): ?>
        <div x-data="{ show: true }" x-show="show" class="fixed top-4 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded shadow-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span><?php echo $_SESSION['success']; ?></span>
                <button @click="show = false" class="ml-2">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="flex min-h-screen pt-16 overflow-x-hidden">
        <!-- Sidebar -->
        <aside class="navy-bg w-64 h-700">
            <div class="flex flex-col h-full">
                <div class="flex-1 px-4 py-3 space-y-1">
                    <!-- Dashboard -->
                    <button @click="activeTab = 'overview'"
                        :class="{ 'bg-white/10': activeTab === 'overview' }"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10">
                        <i class="fas fa-home"></i>
                        <span>Overview</span>
                    </button>

                    <!-- Create Ticket -->
                    <button @click="activeTab = 'create-ticket'"
                        :class="{ 'bg-white/10': activeTab === 'create-ticket' }"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Ticket</span>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 bg-gray-50 p-8" :class="{ 'min-h-screen': true, 'h-auto': activeTab === 'create-ticket' }">
            <!-- Overview Tab -->
            <div x-show="activeTab === 'overview'" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="space-y-6">
                <h1 class="text-2xl font-bold text-gray-800">Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-blue-100 text-blue-500">
                                <i class="fas fa-ticket-alt"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-gray-600">Total Tickets</h2>
                                <p class="text-2xl font-semibold">
                                    <?php
                                    $result = $mysqli->query("SELECT COUNT(*) as count FROM tickets WHERE user_id = $user_id");
                                    echo $result->fetch_assoc()['count'];
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-500">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-gray-600">Resolved Tickets</h2>
                                <p class="text-2xl font-semibold">
                                    <?php
                                    $result = $mysqli->query("SELECT COUNT(*) as count FROM tickets WHERE user_id = $user_id AND status = 'closed'");
                                    echo $result->fetch_assoc()['count'];
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-yellow-100 text-yellow-500">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-gray-600">Pending Tickets</h2>
                                <p class="text-2xl font-semibold">
                                    <?php
                                    $result = $mysqli->query("SELECT COUNT(*) as count FROM tickets WHERE user_id = $user_id AND status != 'closed'");
                                    echo $result->fetch_assoc()['count'];
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tickets Section -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold mb-4">Tickets</h2>
                        <div x-data="{ 
                            currentPage: 1,
                            itemsPerPage: 10,
                            totalItems: <?php
                                $count_query = "SELECT COUNT(*) as count FROM tickets WHERE user_id = ?";
                                $count_stmt = $mysqli->prepare($count_query);
                                $count_stmt->bind_param("i", $user_id);
                                $count_stmt->execute();
                                echo $count_stmt->get_result()->fetch_assoc()['count'];
                            ?>
                        }" class="space-y-4">
                            <?php
                            // Calculate pagination
                            $items_per_page = 10;
                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                            $offset = ($page - 1) * $items_per_page;

                            $query = "SELECT t.*, c.name as category_name 
                                    FROM tickets t 
                                    LEFT JOIN categories c ON t.category_id = c.id 
                                    WHERE t.user_id = ? 
                                    ORDER BY t.created_at DESC
                                    LIMIT ? OFFSET ?";
                            $stmt = $mysqli->prepare($query);
                            $stmt->bind_param("iii", $user_id, $items_per_page, $offset);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            while ($ticket = $result->fetch_assoc()):
                            ?>
                            <div x-data="{ open: false }" class="border rounded-lg overflow-hidden">
                                <!-- Ticket Header - Always visible -->
                                <div @click="open = !open" 
                                     class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50">
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <h3 class="font-semibold"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                                            <div class="flex items-center space-x-3">
                                                <span class="px-3 py-1 rounded-full text-sm 
                                                    <?php echo match($ticket['status']) {
                                                        'open' => 'bg-blue-100 text-blue-800',
                                                        'closed' => 'bg-green-100 text-green-800',
                                                        default => 'bg-yellow-100 text-yellow-800'
                                                    }; ?>">
                                                    <?php echo ucfirst($ticket['status']); ?>
                                                </span>
                                                <i class="fas fa-chevron-down transition-transform" 
                                                   :class="{'rotate-180': open}"></i>
                                            </div>
                                        </div>
                                        <p class="text-sm text-gray-600">
                                            Category: <?php echo htmlspecialchars($ticket['category_name']); ?> | 
                                            Created: <?php echo date('M d, Y g:i A', strtotime($ticket['created_at'])); ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Expanded Content -->
                                <div x-show="open" 
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                                     x-transition:enter-end="opacity-100 transform translate-y-0"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 transform translate-y-0"
                                     x-transition:leave-end="opacity-0 transform -translate-y-2"
                                     class="border-t px-4 py-3 bg-gray-50">
                                    <!-- Description -->
                                    <div class="mb-4">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Description:</h4>
                                        <p class="text-gray-600 whitespace-pre-line">
                                            <?php echo htmlspecialchars($ticket['description']); ?>
                                        </p>
                                    </div>

                                    <!-- Attachment if exists -->
                                    <?php if (!empty($ticket['attachment_path'])): ?>
                                    <div class="mb-4">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Attachment:</h4>
                                        <a href="<?php echo htmlspecialchars($ticket['attachment_path']); ?>" 
                                           class="text-blue-600 hover:text-blue-800 flex items-center"
                                           target="_blank">
                                            <i class="fas fa-paperclip mr-2"></i>
                                            View Attachment
                                        </a>
                                    </div>
                                    <?php endif; ?>

                                    <!-- Additional Details -->
                                    <div class="flex justify-between items-center text-sm text-gray-600">
                                        <div>
                                            <p>Last Updated: <?php echo date('M d, Y g:i A', strtotime($ticket['updated_at'] ?? $ticket['created_at'])); ?></p>
                                            <p>Ticket ID: #<?php echo $ticket['id']; ?></p>
                                        </div>
                                        <a href="view_ticket.php?id=<?php echo $ticket['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 flex items-center">
                                            View Full Details 
                                            <i class="fas fa-arrow-right ml-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>

                            <!-- Pagination -->
                            <div class="flex justify-between items-center mt-4">
                                <div class="text-sm text-gray-600">
                                    Showing <span x-text="((currentPage - 1) * itemsPerPage) + 1"></span>
                                    to <span x-text="Math.min(currentPage * itemsPerPage, totalItems)"></span>
                                    of <span x-text="totalItems"></span> tickets
                                </div>
                                <div class="flex space-x-2">
                                    <button @click="currentPage--" 
                                            :disabled="currentPage === 1"
                                            class="px-3 py-1 bg-gray-100 rounded disabled:opacity-50">
                                        Previous
                                    </button>
                                    <button @click="currentPage++" 
                                            :disabled="currentPage * itemsPerPage >= totalItems"
                                            class="px-3 py-1 bg-gray-100 rounded disabled:opacity-50">
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create Ticket Tab -->
            <div x-show="activeTab === 'create-ticket'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 class="space-y-6">
                <h1 class="text-2xl font-bold text-gray-800">Create New Ticket</h1>
                
                <div class="bg-white rounded-lg shadow p-6">
                    <form method="post" action="handle_ticket.php" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                        
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <select id="category" name="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                <option value="">Select a category</option>
                                <?php
                                $categories_query = $mysqli->query("SELECT * FROM categories ORDER BY name");
                                while ($category = $categories_query->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($category['id']) . "'>" . htmlspecialchars($category['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                            <input type="text" id="subject" name="subject" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required></textarea>
                        </div>

                        <div>
                            <label for="attachment" class="block text-sm font-medium text-gray-700">Attachment (Optional)</label>
                            <input type="file" id="attachment" name="attachment" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="mt-1 text-sm text-gray-500">Allowed file types: JPEG, PNG, PDF, TXT. Max size: 5MB</p>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                Submit Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>