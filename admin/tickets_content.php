<?php
ob_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

$tickets = getAllTickets();
$priorities = getAllPriorities();
$categories = getAllCategories();
$assignable_users = getAssignableUsers();

$isAdmin = isset($_SESSION['staff_role']) && $_SESSION['staff_role'] === 'admin';

$myTicketsCount = count(array_filter($tickets, function ($ticket) {
    return $ticket['assigned_to'] == $_SESSION['user_id']; // Assuming the current user's ID is stored in session
}));

$unassignedCount = count(array_filter($tickets, function ($ticket) {
    return empty($ticket['assigned_to']);
}));

$highPriorityCount = count(array_filter($tickets, function ($ticket) {
    return $ticket['priority_name'] === 'High';
}));

$allTicketsCount = count($tickets);

$pastDueCount = count(array_filter($tickets, function ($ticket) {
    $createdAt = new DateTime($ticket['created_at']);
    $dueDate = $createdAt->modify('+1 week')->format('Y-m-d H:i:s');

    return $dueDate < date('Y-m-d H:i:s') && $ticket['status'] != 'resolved';
}));

?>

<script>
    window.currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    window.staffRole = <?php echo json_encode($_SESSION['staff_role'] ?? ''); ?>;
    window.isAdmin = <?php echo json_encode($isAdmin); ?>;
    window.ticketsData = <?php echo json_encode($tickets); ?>;
    window.prioritiesData = <?php echo json_encode($priorities); ?>;
    window.categoriesData = <?php echo json_encode($categories); ?>;
    window.assignableUsersData = <?php echo json_encode($assignable_users); ?>;

    window.ticketCounts = {
        myTickets: <?php echo $myTicketsCount; ?>,
        pastDue: <?php echo $pastDueCount; ?>,
        unassigned: <?php echo $unassignedCount; ?>,
        highPriority: <?php echo $highPriorityCount; ?>,
        allTickets: <?php echo $allTicketsCount; ?>
    };
    window.sharedViewCounts = window.ticketCounts;

    function ticketTable() {
        return {
            sortColumn: 'created_at',
            sortDirection: 'desc',
            tickets: window.ticketsData || [],
            priorities: window.prioritiesData || [],
            categories: window.categoriesData || [],
            assignableUsers: window.assignableUsersData || [],
            expandedTicketId: null,
            isViewsListOpen: true,
            currentPages: {
                'unseen': 1,
                'seen': 1,
                'resolved': 1
            },
            itemsPerPage: 5,
            isAdmin: window.isAdmin || false,
            activeStatus: 'all', // Default to showing all statuses
            activeViewSection: window.activeViewSection || 'all-tickets', // Default to 'all-tickets'
            viewCounts: window.ticketCounts || {
                myTickets: 0,
                pastDue: 0,
                unassigned: 0,
                highPriority: 0,
                allTickets: 0
            },

            init() {
                this.updateTicketCounts();
                console.log('Is Admin:', this.isAdmin);
                console.log('Tickets:', this.tickets);
                console.log('Filtered Tickets Example:', this.getFilteredTickets());
                console.log('Tickets by Status:', {
                    unseen: this.tickets.filter(t => t.status === 'unseen'),
                    seen: this.tickets.filter(t => t.status === 'seen'),
                    resolved: this.tickets.filter(t => t.status === 'resolved')
                });

                // Ensure this event listener works properly
                window.addEventListener('view-section-changed', (event) => {
                    console.log('Received view-section-changed event:', event.detail.section);
                    this.activeViewSection = event.detail.section;
                    console.log('Updated activeViewSection:', this.activeViewSection);
                    this.getFilteredTickets(); // Refresh the filtered tickets
                    console.log('Filtered Tickets:', this.getFilteredTickets());
                });

                this.$watch('activeViewSection', (newValue) => {
                    this.activeViewSection = newValue;
                    this.currentPages = {
                        'unseen': 1,
                        'seen': 1,
                        'resolved': 1
                    };
                });

                this.$watch('activeStatus', (newValue) => {
                    this.activeStatus = newValue;
                    this.currentPages[newValue] = 1;
                });

                this.activeViewSection = window.activeViewSection || 'my-tickets';
                console.log('Active View Section:', this.activeViewSection);
                console.log('Active Status:', this.activeStatus);
            },

            toggleTicketExpand(ticketId) {
                this.expandedTicketId = this.expandedTicketId === ticketId ? null : ticketId;
            },

            // Add this to your ticketTable() function
            updateTicketCounts() {
                const userId = window.currentUserId || 0;

                const counts = {
                    myTickets: this.tickets.filter(ticket => ticket.assigned_to == userId).length,
                    pastDue: this.tickets.filter(ticket => {
                        const dueDate = this.calculateDueDate(ticket.created_at);
                        return dueDate < new Date() && ticket.status !== 'closed';
                    }).length,
                    unassigned: this.tickets.filter(ticket => !ticket.assigned_to).length,
                    highPriority: this.tickets.filter(ticket => ticket.priority_name === 'High').length,
                    allTickets: this.tickets.length
                };

                // Update local counts
                this.viewCounts = counts;

                // Update shared state for other components
                window.sharedViewCounts = counts;

                // Dispatch an event so other components can update
                window.dispatchEvent(new CustomEvent('ticket-counts-updated', {
                    detail: { counts: counts }
                }));
            },

            nextPage(status) {
                const filteredTickets = this.getFilteredTickets();
                if (this.currentPages[status] * this.itemsPerPage < filteredTickets.length) {
                    this.currentPages[status]++;
                }
            },

            prevPage(status) {
                if (this.currentPages[status] > 1) {
                    this.currentPages[status]--;
                }
            },

            calculateDueDate(createdAt) {
                const createdDate = new Date(createdAt);
                const dueDate = new Date(createdDate.setDate(createdDate.getDate() + 7));
                return dueDate;
            },

            getFilteredTickets() {
                // First, filter by active view section
                let filteredTickets = this.tickets;

                if (this.activeViewSection === 'my-tickets') {
                    const userId = window.currentUserId || 0;
                    filteredTickets = filteredTickets.filter(ticket => ticket.assigned_to == userId);
                } else if (this.activeViewSection === 'past-due') {
                    filteredTickets = filteredTickets.filter(ticket => {
                        const dueDate = this.calculateDueDate(ticket.created_at);
                        return dueDate < new Date() && ticket.status !== 'closed';
                    });
                } else if (this.activeViewSection === 'unassigned') {
                    filteredTickets = filteredTickets.filter(ticket => !ticket.assigned_to);
                } else if (this.activeViewSection === 'high-priority') {
                    filteredTickets = filteredTickets.filter(ticket => ticket.priority_name === 'High');
                } else if (this.activeViewSection === 'all-tickets') {
                }

                // Now, filter by ticket status
                if (this.activeStatus === 'unseen') {
                    filteredTickets = filteredTickets.filter(ticket => ticket.status === 'unseen');
                } else if (this.activeStatus === 'seen') {
                    filteredTickets = filteredTickets.filter(ticket => ticket.status === 'seen');
                } else if (this.activeStatus === 'resolved') {
                    filteredTickets = filteredTickets.filter(ticket => ticket.status === 'resolved');
                }

                console.log('Filtered tickets for view:', this.activeViewSection, 'status:', this.activeStatus, filteredTickets);
                return filteredTickets;
            },

            paginatedTickets(status) {
                this.activeStatus = status;
                let filteredTickets = this.getFilteredTickets();

                const start = (this.currentPages[status] - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                return filteredTickets.slice(start, end);
            },

            updateTicket(ticketId, ticketData) {
                fetch(`update_ticket.php?id=${ticketId}`, {
                    method: 'POST',
                    body: JSON.stringify(ticketData),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.ticket) {
                            const index = this.tickets.findIndex(ticket => ticket.id === ticketId);
                            if (index !== -1) {
                                this.tickets[index] = { ...this.tickets[index], ...data.ticket };
                            }
                            this.showNotification('Ticket updated successfully', 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Error updating ticket:', error);
                        this.showNotification(error.message, 'error');
                    });
            },

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.classList.add(
                    'fixed', 'top-4', 'right-4', 'z-50', 'px-4', 'py-2', 'rounded', 'text-white',
                    type === 'success' ? 'bg-green-500' : 'bg-red-500'
                );
                notification.textContent = message;

                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.classList.add('animate-fade-out');
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 500);
                }, 3000);
            },

            deleteTicket(ticketId) {
                fetch(`delete_ticket.php?id=${ticketId}`, {
                    method: 'DELETE'
                })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(errData => {
                                throw new Error(errData.message || 'Delete failed');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            this.tickets = this.tickets.filter(ticket => ticket.id !== ticketId);
                            this.updateTicketCounts();
                            this.showNotification('Ticket deleted successfully', 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting ticket:', error);
                        this.showNotification(error.message, 'error');
                    });
            },

            formatDate(datetime) {
                const date = new Date(datetime);
                const now = new Date();
                const diff = now - date;

                const seconds = Math.floor(diff / 1000);
                const minutes = Math.floor(seconds / 60);
                const hours = Math.floor(minutes / 60);
                const days = Math.floor(hours / 24);

                if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
                if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
                if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
                return 'just now';
            }
        };
    }
</script>

<head>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>


<div x-show="activeView === 'tickets'" class="flex gap-6" x-data="{ 
    isViewsSidebarOpen: true, 
    activeViewSection: 'my-tickets',
    isAdmin: window.isAdmin || false,
    viewCounts: window.ticketCounts || {
        myTickets: 0,
        pastDue: 0,
        unassigned: 0,
        highPriority: 0,
        allTickets: 0
    },
    toggleViewsSidebar() {
        this.isViewsSidebarOpen = !this.isViewsSidebarOpen;
    },
    setActiveViewSection(section) {
        console.log('Setting active view section to:', section);
        this.activeViewSection = section;
        window.activeViewSection = section;

        // Broadcast the view change event
        const event = new CustomEvent('view-section-changed', { 
            detail: { section: section } 
        });
        window.dispatchEvent(event);
    },
    init() {
        // Listen for ticket count updates from the ticket table component
        window.addEventListener('ticket-counts-updated', (event) => {
            this.viewCounts = event.detail.counts;
        });
    }
}">
    <!-- Left Sidebar - Views with Full Background -->
    <div class="w-1/6 bg-[#f9fafb] h-full min-h-screen transition-all duration-300 ease-in-out" :class="{ 
                    'translate-x-0': isViewsSidebarOpen, 
                    '-translate-x-full absolute': !isViewsSidebarOpen 
                }" x-show="isViewsSidebarOpen" x-transition>
        <div class="tickets-header flex justify-center py-4">
            <div class="flex items-center">
                <h1 class="text-xl font-bold text-gray-800">Tickets</h1>
            </div>
        </div>
        <div class="views-section h-full">
            <!-- Views Header with Dropdown Toggle -->
            <div class="views-header cursor-pointer" @click="isViewsListOpen = !isViewsListOpen">
                <i class="fas fa-chevron-down mr-2 transition-transform duration-200"
                    :class="{ 'rotate-180': !isViewsListOpen }"></i>
                <h3 class="text-sm font-medium text-gray-700">Views</h3>
            </div>

            <div class="py-2 px-3 mb-2 rounded" x-show="isAdmin"
                x-bind:class="isAdmin ? 'bg-blue-100 text-blue-800' : 'hidden'">
                <i class="fas fa-shield-alt mr-1"></i> Admin View
            </div>

            <div class="space-y-1 mt-4" x-show="isViewsListOpen" x-transition>
                <!-- My Tickets -->
                <div class="ticket-sidebar-item border-b border-gray-200"
                    :class="{ 'active': activeViewSection === 'my-tickets' }"
                    @click="setActiveViewSection('my-tickets')">
                    <span>My Tickets</span>
                    <span class="ticket-count" x-text="viewCounts.myTickets"></span>
                </div>

                <!-- Past Due -->
                <div class="ticket-sidebar-item border-b border-gray-200"
                    :class="{ 'active': activeViewSection === 'past-due' }" @click="setActiveViewSection('past-due')">
                    <span>Past Due</span>
                    <span class="ticket-count" x-text="viewCounts.pastDue"></span>
                </div>

                <!-- Unassigned -->
                <div class="ticket-sidebar-item border-b border-gray-200"
                    :class="{ 'active': activeViewSection === 'unassigned' }"
                    @click="setActiveViewSection('unassigned')">
                    <span>Unassigned</span>
                    <span class="ticket-count" x-text="viewCounts.unassigned"></span>
                </div>

                <!-- High Priority -->
                <div class="ticket-sidebar-item border-b border-gray-200"
                    :class="{ 'active': activeViewSection === 'high-priority' }"
                    @click="setActiveViewSection('high-priority')">
                    <span>High Priority</span>
                    <span class="ticket-count" x-text="viewCounts.highPriority"></span>
                </div>

                <!-- All Tickets -->
                <div class="ticket-sidebar-item" :class="{ 'active': activeViewSection === 'all-tickets' }"
                    @click="setActiveViewSection('all-tickets')">
                    <span>All Tickets</span>
                    <span class="ticket-count" x-text="viewCounts.allTickets"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Content - Kanban Columns -->
    <div class="flex-1 py-4 px-6">
        <!-- Aligned Tickets Nav -->
        <div class="flex items-center justify-between mb-4 border-b border-gray-200 pb-2">
            <div class="flex items-center">
                <i class="fas fa-bars mr-2 cursor-pointer" @click="isViewsSidebarOpen = !isViewsSidebarOpen"></i>
                <h1 class="text-xl font-bold text-gray-800" x-text="
                            activeViewSection === 'my-tickets' ? 'My Tickets' : 
                            activeViewSection === 'past-due' ? 'Past Due Tickets' : 
                            activeViewSection === 'unassigned' ? 'Unassigned Tickets' : 
                            activeViewSection === 'high-priority' ? 'High Priority Tickets' : 
                            'All Tickets'
                        ">My Tickets</h1>
                <i class="fas fa-chevron-down ml-2 text-xs"></i>
            </div>
            <div class="flex items-center space-x-4" style="margin-top: -2px">
                <button class="flex items-center text-gray-600">
                    <i class="fas fa-ticket-alt mr-1"></i>
                    <span>My Tickets</span>
                </button>
                <button class="flex items-center text-gray-600">
                    <i class="fas fa-star mr-1"></i>
                    <span>Priority</span>
                </button>
                <button class="flex items-center text-gray-600">
                    <i class="fas fa-calendar mr-1"></i>
                    <span>Date Added</span>
                </button>
                <button class="flex items-center text-gray-600">
                    <i class="fas fa-filter mr-1"></i>
                    <span>Search Filters</span>
                </button>
            </div>
        </div>

        <!-- Kanban Columns -->
        <div class="kanban-container">
            <div class="kanban-columns" x-data="ticketTable()">
                <!-- Unseen Column -->
                <div class="kanban-column">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Unseen</h2>
                    <div class="ticket-list">
                        <template x-for="ticket in paginatedTickets('unseen')" :key="ticket.id">
                            <!-- Ticket Card -->
                            <div class="bg-white rounded-lg shadow-md p-4 mb-4 hover:shadow-lg transition-shadow">
                                <!-- Ticket Header -->
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-md font-semibold text-gray-800" x-text="ticket.title"></h3>
                                    <button @click.stop="toggleTicketExpand(ticket.id)"
                                        class="text-gray-600 hover:text-gray-900">
                                        <i class="fas"
                                            :class="expandedTicketId === ticket.id ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                </div>

                                <div class="text-sm text-gray-600">
                                    <p><strong>Ticket #:</strong> <span x-text="ticket.id"></span></p>
                                    <p><strong>Category:</strong> <span x-text="ticket.category_name"></span></p>
                                    <p><strong>Priority:</strong>
                                        <span class="px-2 py-1 rounded text-sm" :class="{
                            'bg-red-500 text-white': ticket.priority_name === 'High',
                            'bg-yellow-500 text-white': ticket.priority_name === 'Medium',
                            'bg-green-500 text-white': ticket.priority_name === 'Low'
                            }" x-text="ticket.priority_name"></span>
                                    </p>
                                    <p><strong>Created:</strong> <span x-text="formatDate(ticket.created_at)"></span>
                                    </p>
                                    <p><strong>Assigned To:</strong> <span
                                            x-text="ticket.assigned_to_name || 'Unassigned'"></span></p>
                                </div>

                                <!-- Expanded Content -->
                                <div x-show="expandedTicketId === ticket.id" x-cloak class="mt-4 space-y-4">
                                    <!-- Description -->
                                    <div>
                                        <h4 class="text-md font-semibold text-gray-800">Description</h4>
                                        <p class="text-gray-600"
                                            x-text="ticket.description || 'No description available'"></p>
                                    </div>

                                    <!-- Actions -->
                                    <div>
                                        <h4 class="text-md font-semibold text-gray-800">Actions</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Set
                                                    Category</label>
                                                <select x-model="ticket.category_id"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Select Category</option>
                                                    <template x-for="category in categories" :key="category.id">
                                                        <option :value="category.id" x-text="category.name">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Assigned To Dropdown -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Assigned
                                                    To</label>
                                                <select x-model="ticket.assigned_to"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Unassigned</option>
                                                    <template x-for="user in assignableUsers" :key="user.id">
                                                        <option :value="user.id" x-text="user.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Priority Dropdown -->
                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                                <select x-model="ticket.priority_id"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Select Priority</option>
                                                    <template x-for="priority in priorities" :key="priority.id">
                                                        <option :value="priority.id" x-text="priority.name">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Update and Delete Buttons -->
                                            <div class="flex space-x-2">
                                                <button @click="updateTicket(ticket.id, ticket)"
                                                    class="flex-1 bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                                                    Update
                                                </button>
                                                <button @click="deleteTicket(ticket.id)"
                                                    class="flex-1 bg-red-500 text-white py-2 rounded hover:bg-red-600">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="pagination-controls">
                        <div class="flex justify-between">
                            <button @click="prevPage('unseen')" :disabled="currentPages['unseen'] === 1"
                                class="px-4 py-2 bg-gray-200 rounded">
                                Previous
                            </button>
                            <span x-text="`Page ${currentPages['unseen']}`"></span>
                            <button @click="nextPage('unseen')"
                                :disabled="currentPages['unseen'] * itemsPerPage >= getFilteredTickets().length"
                                class="px-4 py-2 bg-gray-200 rounded">
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Seen Column -->
                <div class="kanban-column">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Processing</h2>
                    <div class="ticket-list">
                        <template x-for="ticket in paginatedTickets('seen')" :key="ticket.id">
                            <!-- Ticket Card -->
                            <div class="bg-white rounded-lg shadow-md p-4 mb-4 hover:shadow-lg transition-shadow">
                                <!-- Ticket Header -->
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-md font-semibold text-gray-800" x-text="ticket.title"></h3>
                                    <button @click.stop="toggleTicketExpand(ticket.id)"
                                        class="text-gray-600 hover:text-gray-900">
                                        <i class="fas"
                                            :class="expandedTicketId === ticket.id ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                </div>

                                <!-- Ticket Details -->
                                <div class="text-sm text-gray-600">
                                    <p><strong>Ticket #:</strong> <span x-text="ticket.id"></span></p>
                                    <p><strong>Category:</strong> <span x-text="ticket.category_name"></span></p>
                                    <p><strong>Priority:</strong>
                                        <span class="px-2 py-1 rounded text-sm" :class="{
                            'bg-red-500 text-white': ticket.priority_name === 'High',
                            'bg-yellow-500 text-white': ticket.priority_name === 'Medium',
                            'bg-green-500 text-white': ticket.priority_name === 'Low'
                            }" x-text="ticket.priority_name"></span>
                                    </p>
                                    <p><strong>Created:</strong> <span x-text="formatDate(ticket.created_at)"></span>
                                    </p>
                                    <p><strong>Assigned To:</strong> <span
                                            x-text="ticket.assigned_to_name || 'Unassigned'"></span></p>
                                </div>

                                <!-- Expanded Content -->
                                <div x-show="expandedTicketId === ticket.id" x-cloak class="mt-4 space-y-4">
                                    <!-- Description -->
                                    <div>
                                        <h4 class="text-md font-semibold text-gray-800">Description</h4>
                                        <p class="text-gray-600"
                                            x-text="ticket.description || 'No description available'"></p>
                                    </div>

                                    <!-- Actions -->
                                    <div>
                                        <h4 class="text-md font-semibold text-gray-800">Actions</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Set
                                                    Category</label>
                                                <select x-model="ticket.category_id"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Select Category</option>
                                                    <template x-for="category in categories" :key="category.id">
                                                        <option :value="category.id" x-text="category.name">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Assigned To Dropdown -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Assigned
                                                    To</label>
                                                <select x-model="ticket.assigned_to"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Unassigned</option>
                                                    <template x-for="user in assignableUsers" :key="user.id">
                                                        <option :value="user.id" x-text="user.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Priority Dropdown -->
                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                                <select x-model="ticket.priority_id"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Select Priority</option>
                                                    <template x-for="priority in priorities" :key="priority.id">
                                                        <option :value="priority.id" x-text="priority.name">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Update and Delete Buttons -->
                                            <div class="flex space-x-2">
                                                <button @click="updateTicket(ticket.id, ticket)"
                                                    class="flex-1 bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                                                    Update
                                                </button>
                                                <button @click="deleteTicket(ticket.id)"
                                                    class="flex-1 bg-red-500 text-white py-2 rounded hover:bg-red-600">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <!-- Pagination Controls -->
                    <div class="pagination-controls">
                        <div class="flex justify-between">
                            <button @click="prevPage('seen')" :disabled="currentPages['seen'] === 1"
                                class="px-4 py-2 bg-gray-200 rounded">
                                Previous
                            </button>
                            <span x-text="`Page ${currentPages['seen']}`"></span>
                            <button @click="nextPage('seen')"
                                :disabled="currentPages['seen'] * itemsPerPage >= getFilteredTickets().length"
                                class="px-4 py-2 bg-gray-200 rounded">
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Resolved Column -->
                <div class="kanban-column">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Resolved</h2>
                    <div class="ticket-list">
                        <template x-for="ticket in paginatedTickets('resolved')" :key="ticket.id">
                            <!-- Ticket Card -->
                            <div class="bg-white rounded-lg shadow-md p-4 mb-4 hover:shadow-lg transition-shadow">
                                <!-- Ticket Header -->
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-md font-semibold text-gray-800" x-text="ticket.title"></h3>
                                    <button @click.stop="toggleTicketExpand(ticket.id)"
                                        class="text-gray-600 hover:text-gray-900">
                                        <i class="fas"
                                            :class="expandedTicketId === ticket.id ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                </div>

                                <!-- Ticket Details -->
                                <div class="text-sm text-gray-600">
                                    <p><strong>Ticket #:</strong> <span x-text="ticket.id"></span></p>
                                    <p><strong>Category:</strong> <span x-text="ticket.category_name"></span></p>
                                    <p><strong>Priority:</strong>
                                        <span class="px-2 py-1 rounded text-sm" :class="{
                            'bg-red-500 text-white': ticket.priority_name === 'High',
                            'bg-yellow-500 text-white': ticket.priority_name === 'Medium',
                            'bg-green-500 text-white': ticket.priority_name === 'Low'
                            }" x-text="ticket.priority_name"></span>
                                    </p>
                                    <p><strong>Created:</strong> <span x-text="formatDate(ticket.created_at)"></span>
                                    </p>
                                    <p><strong>Assigned To:</strong> <span
                                            x-text="ticket.assigned_to_name || 'Unassigned'"></span></p>
                                </div>

                                <!-- Expanded Content -->
                                <div x-show="expandedTicketId === ticket.id" x-cloak class="mt-4 space-y-4">
                                    <!-- Description -->
                                    <div>
                                        <h4 class="text-md font-semibold text-gray-800">Description</h4>
                                        <p class="text-gray-600"
                                            x-text="ticket.description || 'No description available'"></p>
                                    </div>

                                    <!-- Actions -->
                                    <div>
                                        <h4 class="text-md font-semibold text-gray-800">Actions</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Set
                                                    Category</label>
                                                <select x-model="ticket.category_id"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Select Category</option>
                                                    <template x-for="category in categories" :key="category.id">
                                                        <option :value="category.id" x-text="category.name">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Assigned To Dropdown -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Assigned
                                                    To</label>
                                                <select x-model="ticket.assigned_to"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Unassigned</option>
                                                    <template x-for="user in assignableUsers" :key="user.id">
                                                        <option :value="user.id" x-text="user.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Priority Dropdown -->
                                            <div>
                                                <label
                                                    class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                                <select x-model="ticket.priority_id"
                                                    class="w-full border rounded px-3 py-2 text-sm">
                                                    <option value="">Select Priority</option>
                                                    <template x-for="priority in priorities" :key="priority.id">
                                                        <option :value="priority.id" x-text="priority.name">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Update and Delete Buttons -->
                                            <div class="flex space-x-2">
                                                <button @click="updateTicket(ticket.id, ticket)"
                                                    class="flex-1 bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                                                    Update
                                                </button>
                                                <button @click="deleteTicket(ticket.id)"
                                                    class="flex-1 bg-red-500 text-white py-2 rounded hover:bg-red-600">
                                                    Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <!-- Pagination Controls -->
                    <button @click="prevPage('resolved')" :disabled="currentPages['resolved'] === 1"
                        class="px-4 py-2 bg-gray-200 rounded">
                        Previous
                    </button>
                    <span x-text="`Page ${currentPages['resolved']}`"></span>
                    <button @click="nextPage('resolved')"
                        :disabled="currentPages['resolved'] * itemsPerPage >= getFilteredTickets().length"
                        class="px-4 py-2 bg-gray-200 rounded">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>