<?php
ob_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    $staff_member_id = getStaffMemberDetails();

    if ($staff_member_id) {
        $_SESSION['staff_member_id'] = $staff_member_id;
    } else {
        // Optional: handle case where no staff member is found
        error_log("No staff member found for user ID: " . $_SESSION['user_id']);
    }
}

$isAdmin = isset($_SESSION['staff_role']) && $_SESSION['staff_role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helpdesk Dashboard</title>
    <script src="//unpkg.com/alpinejs" defer></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body x-data="{ 
    sidebarOpen: true, 
    activeView: '<?php echo isset($_GET['view']) && in_array($_GET['view'], ['staff-management', 'tickets', 'priorities', 'categories', 'reports']) ? htmlspecialchars($_GET['view']) : 'tickets'; ?>', 
    openSubmenu: '<?php echo isset($_GET['view']) && $_GET['view'] === 'staff-management' ? 'users' : ''; ?>',
    searchExpanded: false,
    isViewsListOpen: true,
    profileMenuOpen: false,
    isAdmin: <?php echo $isAdmin ? 'true' : 'false'; ?>
}">
    <!-- Top Navigation Bar -->
    <nav class="navy-bg h-16 flex items-center px-6 fixed w-full z-50">
        <!-- Logo -->
        <div class="text-white font-bold text-xl">HelpDesk</div>

        <!-- Search Bar (Centered) -->
        <div class="flex-1 flex justify-center mx-4">
            <div class="relative w-full max-w-2xl search-bar" :class="{ 'expanded': searchExpanded }">
                <input type="text" placeholder="Search In My Tickets..." @click="searchExpanded = true"
                    @blur="searchExpanded = false"
                    class="w-full py-2 px-4 rounded-full bg-white shadow-sm focus:outline-none">
                <i class="fas fa-search text-gray-400 absolute right-4 top-3"></i>
            </div>
        </div>

        <!-- Right Icons (Properly Aligned) -->
        <div class="flex items-center space-x-5 ml-auto">
            <button class="text-white hover:bg-white/10 p-2 rounded-full">
                <i class="fas fa-info-circle"></i>
            </button>
            <button class="text-white hover:bg-white/10 p-2 rounded-full relative">
                <i class="fas fa-bell"></i>
                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs px-2 rounded-full">3</span>
            </button>
            <!-- Profile Button with Dropdown -->
            <div class="relative">
                <button @click="profileMenuOpen = !profileMenuOpen" @click.outside="profileMenuOpen = false"
                    class="text-white hover:bg-white/10 p-2 rounded-full">
                    <i class="fas fa-user-circle text-xl"></i>
                </button>
                <!-- Profile Dropdown Menu -->
                <div x-show="profileMenuOpen" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50"
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95">

                    <!-- User Info -->
                    <div class="px-4 py-3 border-b">
                        <p class="text-sm font-medium text-gray-900">
                            <?php echo htmlspecialchars($_SESSION['staff_member_id']['name'] ?? 'User'); ?>
                        </p>
                        <p class="text-xs text-gray-500 truncate">
                            <?php echo htmlspecialchars($_SESSION['staff_member_id']['email'] ?? 'email@example.com'); ?>
                        </p>
                        <!-- Optionally, you can keep the role if needed -->
                        <p class="text-xs text-gray-500 mt-1">
                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($_SESSION['staff_role'] ?? 'Role'))); ?>
                        </p>
                    </div>

                    <!-- Menu Items -->
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-user-cog mr-2"></i> My Profile
                    </a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-cog mr-2"></i> Account Settings
                    </a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-moon mr-2"></i> Dark Mode
                    </a>
                    <div class="border-t"></div>
                    <a href="../logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="flex pt-16">
        <aside class="navy-bg w-64 h-screen fixed flex flex-col sidebar-transition z-40"
            :class="{ 'w-20': !sidebarOpen }">
            <div class="p-4 flex-1">

                <nav class="space-y-2">
                    <button @click="activeView = 'tickets'"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                        :class="{ 'active': activeView === 'tickets' }">
                        <i class="fas fa-ticket-alt"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Tickets</span>
                    </button>

                    <!-- Users/Agents Menu - Only visible to admins -->
                    <div x-show="isAdmin">
                        <button @click="openSubmenu = openSubmenu === 'users' ? '' : 'users'"
                            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                            :class="{ 'active': activeView.startsWith('users') }">
                            <i class="fas fa-users"></i>
                            <span :class="{ 'hidden': !sidebarOpen }">Users/Agents</span>
                            <i class="fas fa-chevron-down ml-auto text-sm"
                                :class="{ 'hidden': !sidebarOpen, 'rotate-180': openSubmenu === 'users' }"></i>
                        </button>
                        <!-- Submenu -->
                        <div x-show="openSubmenu === 'users'" class="pl-8 mt-2 space-y-2 submenu"
                            :class="{ 'open': openSubmenu === 'users' }">
                            <button @click="activeView = 'users'"
                                class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                                :class="{ 'active': activeView === 'users' }">
                                <i class="fas fa-user"></i>
                                <span>Users</span>
                            </button>
                            <button @click="activeView = 'staff-management'"
                                class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                                :class="{ 'active': activeView === 'staff-management' }">
                                <i class="fas fa-user-plus"></i>
                                <span>Staff Management</span>
                            </button>
                        </div>
                    </div>

                    <!-- Registration Menu - Only visible to admins -->
                    <div x-show="isAdmin">
                        <button @click="openSubmenu = openSubmenu === 'registration' ? '' : 'registration'"
                            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                            :class="{ 'active': activeView.startsWith('registration') }">
                            <i class="fas fa-user-plus"></i>
                            <span :class="{ 'hidden': !sidebarOpen }">Registration</span>
                            <i class="fas fa-chevron-down ml-auto text-sm"
                                :class="{ 'hidden': !sidebarOpen, 'rotate-180': openSubmenu === 'registration' }"></i>
                        </button>
                        <div x-show="openSubmenu === 'registration'" class="pl-8 mt-2 space-y-2">
                            <button @click="activeView = 'priorities'"
                                class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                                :class="{ 'active': activeView === 'priorities' }">
                                <i class="fas fa-star"></i>
                                <span :class="{ 'hidden': !sidebarOpen }">Priorities</span>
                            </button>
                            <button @click="activeView = 'categories'"
                                class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                                :class="{ 'active': activeView === 'categories' }">
                                <i class="fas fa-tags"></i>
                                <span :class="{ 'hidden': !sidebarOpen }">Categories</span>
                            </button>
                        </div>
                    </div>

                    <!-- Knowledge Base -->
                    <button @click="activeView = 'knowledge'"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                        :class="{ 'active': activeView === 'knowledge' }">
                        <i class="fas fa-book"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Knowledge Base</span>
                    </button>

                    <!-- Reports -->
                    <button @click="activeView = 'reports'"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                        :class="{ 'active': activeView === 'reports' }">
                        <i class="fas fa-chart-bar"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Reports</span>
                    </button>

                    <!-- Settings -->
                    <button @click="activeView = 'settings'"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                        :class="{ 'active': activeView === 'settings' }">
                        <i class="fas fa-cog"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Settings</span>
                    </button>

                    <!-- Support -->
                    <button @click="activeView = 'support'"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10 nav-item"
                        :class="{ 'active': activeView === 'support' }">
                        <i class="fas fa-life-ring"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Support</span>
                    </button>
                </nav>

                <div class="p-4 border-t border-white/10">
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="w-full flex items-center justify-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10">
                        <i class="fas" :class="sidebarOpen ? 'fa-arrow-left' : 'fa-arrow-right'"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Collapse</span>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content Area with rounded corners -->
        <main class="flex-1 scrollable-content bg-white rounded-content main-content shadow-sm"
            :class="{ 'collapsed': !sidebarOpen }">
            <div x-show="activeView === 'tickets'">
                <?php include 'tickets_content.php'; ?>
            </div>
            <div x-show="activeView === 'staff-management'">
                <?php include 'staff_management.php'; ?>
            </div>
            <div x-show="activeView === 'priorities'">
                <?php include 'priorities.php'; ?>
            </div>
            <div x-show="activeView === 'categories'">
                <?php include 'categories.php'; ?>
            </div>
            <div x-show="activeView === 'reports'">
                <?php include 'reports.php'; ?>
            </div>
        </main>
    </div>
</body>

<script>
    // Clear the URL parameter after the page has loaded and Alpine.js has initialized
    window.addEventListener('DOMContentLoaded', () => {
        // Use setTimeout to ensure Alpine.js has had time to initialize
        setTimeout(() => {
            if (window.location.search) {
                const currentPath = window.location.pathname;
                window.history.replaceState({}, document.title, currentPath);
            }
        }, 100);
    });
</script>

</html>