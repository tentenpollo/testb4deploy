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

// ...existing dashboard HTML code...
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
    sidebarOpen: true,
    searchExpanded: false,
    profileMenuOpen: false,
    activeTab: 'my-tickets',
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

    <div class="flex h-screen pt-16">
        <!-- Sidebar -->
        <aside class="navy-bg w-64 min-h-screen" :class="{ 'hidden': !sidebarOpen }">
            <div class="flex flex-col h-full">
                <div class="flex-1 px-4 py-6 space-y-1">
                    <!-- Dashboard -->
                    <button @click="activeTab = 'overview'"
                        :class="{ 'bg-white/10': activeTab === 'overview' }"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10">
                        <i class="fas fa-home"></i>
                        <span>Overview</span>
                    </button>

                    <!-- My Tickets -->
                    <button @click="activeTab = 'my-tickets'"
                        :class="{ 'bg-white/10': activeTab === 'my-tickets' }"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10">
                        <i class="fas fa-ticket-alt"></i>
                        <span>My Tickets</span>
                    </button>

                    <!-- Create Ticket -->
                    <button @click="activeTab = 'create-ticket'"
                        :class="{ 'bg-white/10': activeTab === 'create-ticket' }"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10">
                        <i class="fas fa-plus-circle"></i>
                        <span>Create Ticket</span>
                    </button>
                </div>

                <!-- Sidebar Footer -->
                <div class="p-4 border-t border-white/10">
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="w-full flex items-center justify-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10">
                        <i class="fas" :class="sidebarOpen ? 'fa-arrow-left' : 'fa-arrow-right'"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Collapse</span>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 bg-gray-50 p-8">
            <!-- Overview Tab -->
            <div x-show="activeTab === 'overview'" class="space-y-6">
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

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow">
                    <div class="p-6">
                        <h2 class="text-xl font-semibold mb-4">Recent Activity</h2>
                        <div class="space-y-4">
                            <?php
                            $query = "SELECT t.*, c.name as category_name 
                                     FROM tickets t 
                                     LEFT JOIN categories c ON t.category_id = c.id 
                                     WHERE t.user_id = ? 
                                     ORDER BY t.created_at DESC LIMIT 5";
                            $stmt = $mysqli->prepare($query);
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            while ($ticket = $result->fetch_assoc()):
                            ?>
                            <div class="flex items-center p-4 border rounded-lg">
                                <div class="flex-1">
                                    <h3 class="font-semibold"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                                    <p class="text-sm text-gray-600">
                                        Category: <?php echo htmlspecialchars($ticket['category_name']); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span class="px-3 py-1 rounded-full text-sm 
                                        <?php echo match($ticket['status']) {
                                            'open' => 'bg-blue-100 text-blue-800',
                                            'closed' => 'bg-green-100 text-green-800',
                                            default => 'bg-yellow-100 text-yellow-800'
                                        }; ?>">
                                        <?php echo ucfirst($ticket['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Tickets Tab -->
<div x-show="activeTab === 'my-tickets'" class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">My Tickets</h1>
    </div>

    <!-- Search and Filter Section -->
    <div class="bg-white rounded-lg shadow p-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Search by Title -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700">Search by Title</label>
                    <input type="text" id="search" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <!-- Filter by Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Filter by Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Statuses</option>
                        <option value="open" <?php echo (isset($_GET['status']) && $_GET['status'] === 'open' ? 'selected' : ''); ?>>Open</option>
                        <option value="closed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'closed' ? 'selected' : ''); ?>>Closed</option>
                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''); ?>>Pending</option>
                    </select>
                </div>

                <!-- Filter by Category -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700">Filter by Category</label>
                    <select id="category" name="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All Categories</option>
                        <?php
                        $categories_query = $mysqli->query("SELECT * FROM categories ORDER BY name");
                        while ($category = $categories_query->fetch_assoc()) {
                            $selected = (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($category['id']) . "' $selected>" . htmlspecialchars($category['name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Tickets List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div x-data="{ expandedTicket: null }">
            <?php
            // Build the base query
            $query = "SELECT t.*, c.name as category_name 
                     FROM tickets t 
                     LEFT JOIN categories c ON t.category_id = c.id 
                     WHERE t.user_id = ? AND t.created_by = 'user'";

            // Add search and filter conditions
            $params = [$user_id];
            $types = "i";

            if (!empty($_GET['search'])) {
                $query .= " AND t.title LIKE ?";
                $params[] = '%' . $_GET['search'] . '%';
                $types .= "s";
            }

            if (!empty($_GET['status'])) {
                if ($_GET['status'] === 'pending') {
                    $query .= " AND (t.status = 'pending' OR t.status = 'unseen')";
                } else {
                    $query .= " AND t.status = ?";
                    $params[] = $_GET['status'];
                    $types .= "s";
                }
            }

            if (!empty($_GET['category'])) {
                $query .= " AND t.category_id = ?";
                $params[] = $_GET['category'];
                $types .= "i";
            }

            $query .= " ORDER BY t.created_at DESC";

            // Prepare and execute the query
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            ?>

            <?php if ($result->num_rows > 0): ?>
                <div class="divide-y divide-gray-200">
                    <?php while ($ticket = $result->fetch_assoc()): ?>
                        <div class="p-4">
                            <div class="flex justify-between items-center cursor-pointer"
                                 @click="expandedTicket = expandedTicket === <?php echo $ticket['id']; ?> ? null : <?php echo $ticket['id']; ?>">
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                                    <p class="text-sm text-gray-500">
                                        Status: <span class="font-medium"><?php echo ucfirst($ticket['status']); ?></span>
                                        • Created: <?php echo date('M d, Y', strtotime($ticket['created_at'])); ?>
                                    </p>
                                </div>
                                <i class="fas" :class="expandedTicket === <?php echo $ticket['id']; ?> ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                            </div>

                            <div x-show="expandedTicket === <?php echo $ticket['id']; ?>" 
                                 x-transition:enter="transition ease-out duration-200"
                                 x-transition:enter-start="opacity-0 transform -translate-y-2"
                                 x-transition:enter-end="opacity-100 transform translate-y-0"
                                 class="mt-4">
                                <div class="prose max-w-none">
                                    <?php echo htmlspecialchars($ticket['description']); ?>
                                </div>
                                <div class="mt-4 text-sm text-gray-500">
                                    Category: <?php echo htmlspecialchars($ticket['category_name']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-gray-500">
                    No tickets found
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

            <!-- Create Ticket -->
            <div x-show="activeTab === 'create-ticket'" class="space-y-6">
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
