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
    
    <style>
        body{
            background: #0a1155; 
        }
        .navy-bg { background-color: #0a1155; }
        .priority-pill { border-radius: 50px; }
        .sidebar-transition { transition: all 0.2s ease-in-out; }
        .scrollable-content { height: calc(100vh - 64px); overflow-y: auto; }
        /* Updated rounded corners for the content area */
        .rounded-content { 
            border-top-left-radius: 60px; 
            overflow: hidden; 
        }
        .main-content { 
            margin-left: 16rem; 
            z-index: 30; /* Ensure it's below the sidebar */
        } /* Default sidebar width */
        .main-content.collapsed { margin-left: 5rem; } /* Collapsed sidebar width */
    </style>
</head>
<body x-data="{ sidebarOpen: true, activeView: 'tickets', openSubmenu: '' }">
    <!-- Top Navigation Bar -->
    <nav class="navy-bg h-16 flex items-center px-6 fixed w-full z-50 shadow-lg">
        <!-- Logo -->
        <div class="text-white font-bold text-xl">HelpDesk</div>

        <!-- Search Bar (Centered) -->
        <div class="flex-1 flex justify-center mx-4">
            <div class="relative w-full max-w-2xl">
                <input type="text" placeholder="Search In My Tickets..." 
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
            <button class="text-white hover:bg-white/10 p-2 rounded-full">
                <i class="fas fa-user-circle text-xl"></i>
            </button>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="flex pt-16">
        <!-- Sidebar -->
        <aside class="navy-bg w-64 h-screen fixed flex flex-col sidebar-transition z-40"
               :class="{ 'w-20': !sidebarOpen }">
            <!-- Sidebar Content -->
            <div class="p-4 flex-1">

                <!-- Navigation Items -->
                <nav class="space-y-2">
                    <!-- Tickets -->
                    <button @click="activeView = 'tickets'" 
                            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                            :class="{ 'bg-blue-600': activeView === 'tickets' }">
                        <i class="fas fa-ticket-alt"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Tickets</span>
                    </button>

                    <!-- Users/Agents (with Submenu) -->
                    <div>
                        <button @click="openSubmenu = openSubmenu === 'users' ? '' : 'users'" 
                                class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                                :class="{ 'bg-blue-600': activeView.startsWith('users') }">
                            <i class="fas fa-users"></i>
                            <span :class="{ 'hidden': !sidebarOpen }">Users/Agents</span>
                            <i class="fas fa-chevron-down ml-auto text-sm" :class="{ 'hidden': !sidebarOpen, 'rotate-180': openSubmenu === 'users' }"></i>
                        </button>
                        <!-- Submenu -->
                        <div x-show="openSubmenu === 'users'" class="pl-8 mt-2 space-y-2">
                            <button @click="activeView = 'users'" 
                                    class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                                    :class="{ 'bg-blue-600': activeView === 'users' }">
                                <i class="fas fa-user"></i>
                                <span :class="{ 'hidden': !sidebarOpen }">Users</span>
                            </button>
                            <button @click="activeView = 'agents'" 
                                    class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                                    :class="{ 'bg-blue-600': activeView === 'agents' }">
                                <i class="fas fa-user-tie"></i>
                                <span :class="{ 'hidden': !sidebarOpen }">Agents</span>
                            </button>
                        </div>
                    </div>

                    <!-- Registration (with Submenu) -->
                    <div>
                        <button @click="openSubmenu = openSubmenu === 'registration' ? '' : 'registration'" 
                                class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                                :class="{ 'bg-blue-600': activeView.startsWith('registration') }">
                            <i class="fas fa-user-plus"></i>
                            <span :class="{ 'hidden': !sidebarOpen }">Registration</span>
                            <i class="fas fa-chevron-down ml-auto text-sm" :class="{ 'hidden': !sidebarOpen, 'rotate-180': openSubmenu === 'registration' }"></i>
                        </button>
                        <!-- Submenu -->
                        <div x-show="openSubmenu === 'registration'" class="pl-8 mt-2 space-y-2">
                            <button @click="activeView = 'priorities'" 
                                    class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                                    :class="{ 'bg-blue-600': activeView === 'priorities' }">
                                <i class="fas fa-star"></i>
                                <span :class="{ 'hidden': !sidebarOpen }">Priorities</span>
                            </button>
                            <button @click="activeView = 'categories'" 
                                    class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                                    :class="{ 'bg-blue-600': activeView === 'categories' }">
                                <i class="fas fa-tags"></i>
                                <span :class="{ 'hidden': !sidebarOpen }">Categories</span>
                            </button>
                        </div>
                    </div>

                    <!-- Knowledge Base -->
                    <button @click="activeView = 'knowledge'" 
                            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                            :class="{ 'bg-blue-600': activeView === 'knowledge' }">
                        <i class="fas fa-book"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Knowledge Base</span>
                    </button>
                    
                    <!-- Reports -->
                    <button @click="activeView = 'reports'" 
                            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                            :class="{ 'bg-blue-600': activeView === 'reports' }">
                        <i class="fas fa-chart-bar"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Reports</span>
                    </button>
                    
                    <!-- Settings -->
                    <button @click="activeView = 'settings'" 
                            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                            :class="{ 'bg-blue-600': activeView === 'settings' }">
                        <i class="fas fa-cog"></i>
                        <span :class="{ 'hidden': !sidebarOpen }">Settings</span>
                    </button>
                    
                    <!-- Support -->
                    <button @click="activeView = 'support'" 
                            class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10"
                            :class="{ 'bg-blue-600': activeView === 'support' }">
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
        <main class="flex-1 scrollable-content bg-white rounded-content main-content shadow-lg mt-0"
              :class="{ 'collapsed': !sidebarOpen }">
            <!-- Dynamic Content Sections -->
            <div x-show="activeView === 'tickets'" class="p-8">
                <!-- Tickets Table Content -->
                <div class="bg-white rounded-lg shadow-sm border">
                    <!-- Table Header -->
                    <div class="px-6 py-4 border-b flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <select class="px-4 py-2 rounded-lg border focus:outline-none">
                                <option>My Tickets</option>
                            </select>
                        </div>
                        <div class="flex space-x-4">
                            <button class="p-2 hover:bg-gray-100 rounded-lg">
                                <i class="fas fa-sort-amount-down"></i>
                            </button>
                            <button class="p-2 hover:bg-gray-100 rounded-lg">
                                <i class="fas fa-filter"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Table Content -->
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500"><input type="checkbox"></th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Ticket ID</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Subject</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Priority</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Type</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Client</th>
                                <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Request Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <!-- Sample Row -->
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4"><input type="checkbox"></td>
                                <td class="px-6 py-4 font-medium">#00001</td>
                                <td class="px-6 py-4">Payment Issue</td>
                                <td class="px-6 py-4">
                                    <span class="priority-pill bg-red-500 text-white px-3 py-1 text-sm">High</span>
                                </td>
                                <td class="px-6 py-4">Email</td>
                                <td class="px-6 py-4 text-blue-600 underline cursor-pointer">mitsi@gmail.com</td>
                                <td class="px-6 py-4">3/4/2025, 3:05 PM</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add other content sections here (Users, Reports, etc) using same pattern -->
        </main>
    </div>
</body>
</html>