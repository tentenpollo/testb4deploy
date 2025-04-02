<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

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
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>

<body x-data="{ 
    sidebarOpen: true,
    searchExpanded: false,
    profileMenuOpen: false,
    activeTab: 'my-tickets',
    showCreateTicket: false,
    ticketModalOpen: false // Add this line
}" x-init="$watch('ticketModalOpen', value => {
        if (value) document.body.classList.add('overflow-hidden');
        else document.body.classList.remove('overflow-hidden');
    })">
    <!-- Top Navigation Bar -->
    <nav class="navy-bg h-16 flex items-center px-6 fixed w-full z-50">
        <!-- Logo -->
        <div class="text-white font-bold text-xl">HelpDesk</div>

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
        <div x-data="{ show: true }" x-show="show"
            class="fixed top-4 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded shadow-lg">
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
                    <button @click="activeTab = 'overview'" :class="{ 'bg-white/10': activeTab === 'overview' }"
                        class="w-full flex items-center space-x-3 px-3 py-2 rounded-lg text-white hover:bg-white/10">
                        <i class="fas fa-home"></i>
                        <span>Overview</span>
                    </button>

                    <!-- My Tickets -->
                    <button @click="activeTab = 'my-tickets'" :class="{ 'bg-white/10': activeTab === 'my-tickets' }"
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

            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 bg-gray-50 p-8">
            <!-- Overview Tab -->
            <div x-show="activeTab === 'overview'" class="space-y-6">
                <h1 class="text-2xl font-bold text-gray-800">Welcome,
                    <?php echo htmlspecialchars($user['first_name']); ?>!
                </h1>

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
                            $query = "SELECT t.*, 
                            c.name as category_name,
                            s.name as subcategory_name  -- Added this line
                            FROM tickets t 
                            LEFT JOIN categories c ON t.category_id = c.id 
                            LEFT JOIN subcategories s ON t.subcategory_id = s.id  -- Added this line
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
                                            <?php if (!empty($ticket['subcategory_name'])): ?>
                                                • Subcategory: <?php echo htmlspecialchars($ticket['subcategory_name']); ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-3 py-1 rounded-full text-sm 
        <?php echo match ($ticket['status']) {
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
                                <label for="search" class="block text-sm font-medium text-gray-700">Search by
                                    Title</label>
                                <input type="text" id="search" name="search"
                                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <!-- Filter by Status -->
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700">Filter by
                                    Status</label>
                                <select id="status" name="status"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">All Statuses</option>
                                    <option value="open" <?php echo (isset($_GET['status']) && $_GET['status'] === 'open' ? 'selected' : ''); ?>>Open</option>
                                    <option value="closed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'closed' ? 'selected' : ''); ?>>Closed</option>
                                    <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''); ?>>Pending</option>
                                </select>
                            </div>

                            <!-- Filter by Category -->
                            <div>
                                <label for="category" class="block text-sm font-medium text-gray-700">Filter by
                                    Category</label>
                                <select id="category" name="category"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
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
                                    <div class="flex items-center p-4 border rounded-lg cursor-pointer"
                                        @click="$dispatch('open-ticket-modal', { ticketId: <?php echo $ticket['id']; ?> })">
                                        <div class="flex-1">
                                            <h3 class="font-semibold"><?php echo htmlspecialchars($ticket['title']); ?></h3>
                                            <p class="text-sm text-gray-600">
                                                Category: <?php echo htmlspecialchars($ticket['category_name']); ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span class="px-3 py-1 rounded-full text-sm 
                                    <?php echo match ($ticket['status']) {
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
                            <select id="category" name="category"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                                <option value="">Select a category</option>
                                <?php
                                $categories_query = $mysqli->query("SELECT * FROM categories ORDER BY name");
                                while ($category = $categories_query->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($category['id']) . "'>" . htmlspecialchars($category['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Add Subcategory field -->
                        <div>
                            <label for="subcategory" class="block text-sm font-medium text-gray-700">Subcategory</label>
                            <select id="subcategory" name="subcategory_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                                <option value="">Select a subcategory</option>
                                <!-- Will be populated via JavaScript -->
                            </select>
                        </div>

                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700">Subject</label>
                            <input type="text" id="subject" name="subject"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea id="description" name="description" rows="4"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                required></textarea>
                        </div>

                        <div>
                            <label for="attachment" class="block text-sm font-medium text-gray-700">Attachment
                                (Optional)</label>
                            <input type="file" id="attachment" name="attachment"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="mt-1 text-sm text-gray-500">Allowed file types: JPEG, PNG, PDF, TXT. Max size: 5MB
                            </p>
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

    <!-- Ticket Details Modal -->
    <div x-data="ticketDetailsModal()" x-show="isOpen" x-on:open-ticket-modal.window="openModal($event.detail.ticketId)"
        class="fixed inset-0 z-50 flex items-center justify-center ticket-detail-modal" x-cloak>
        <div class="absolute inset-0 bg-black opacity-50"></div>

        <div class="relative bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-y-auto ticket-modal-content"
            x-transition:enter="ticket-modal-enter" x-transition:leave="ticket-modal-leave">
            <!-- Modal Header -->
            <div class="sticky top-0 bg-white z-10 flex justify-between items-center p-4 border-b">
                <div class="flex items-center">
                    <h2 class="text-xl font-bold" x-text="currentTicket ? currentTicket.title : 'Ticket Details'"></h2>
                    <span x-show="currentTicket" class="ml-3 px-2 py-1 text-sm rounded-full" :class="{
                          'bg-blue-100 text-blue-800': currentTicket && currentTicket.status === 'open',
                          'bg-green-100 text-green-800': currentTicket && currentTicket.status === 'closed',
                          'bg-yellow-100 text-yellow-800': currentTicket && ['pending', 'in_progress'].includes(currentTicket.status)
                      }" x-text="currentTicket ? ucfirst(currentTicket.status) : ''">
                    </span>
                </div>
                <button @click="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="p-6">
                <template x-if="currentTicket">
                    <!-- Ticket Details -->
                    <div class="space-y-6">
                        <!-- Ticket Info Section -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg">
                            <div>
                                <p class="text-sm text-gray-600"><strong>Ticket ID:</strong> <span
                                        x-text="currentTicket.id"></span></p>
                                <p class="text-sm text-gray-600"><strong>Reference ID:</strong> <span
                                        x-text="currentTicket.ref_id || 'N/A'"></span></p>
                                <p class="text-sm text-gray-600"><strong>Category:</strong> <span
                                        x-text="currentTicket.category_name || 'Uncategorized'"></span></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><strong>Created:</strong> <span
                                        x-text="formatDate(currentTicket.created_at)"></span></p>
                                <p class="text-sm text-gray-600"><strong>Last Updated:</strong> <span
                                        x-text="formatDate(currentTicket.updated_at)"></span></p>
                                <p class="text-sm text-gray-600"><strong>Subcategory:</strong> <span
                                        x-text="currentTicket.subcategory_name || 'N/A'"></span></p>
                            </div>
                            <div class="col-span-1 md:col-span-2">
                                <h3 class="text-sm font-medium text-gray-500 mt-2">Description</h3>
                                <div class="mt-1 p-3 bg-white rounded border border-gray-200"
                                    x-html="currentTicket.description"></div>
                            </div>
                        </div>

                        <!-- Original Ticket Attachments -->
                        <div x-show="attachments.length > 0" class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-500 mb-2">Original Attachments</h3>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="attachment in attachments" :key="attachment.id">
                                    <div class="flex items-center ticket-attachment">
                                        <i class="fas fa-paperclip ticket-attachment-icon"></i>
                                        <span x-text="attachment.filename" @click="downloadAttachment(attachment)"
                                            class="ticket-attachment-name"></span>
                                        <button @click="downloadAttachment(attachment)" class="ticket-attachment-action"
                                            title="Download">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Ticket Chat History -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-500 mb-4">Conversation History</h3>

                            <!-- Chat Container -->
                            <div class="ticket-chat-container space-y-4">
                                <template x-for="item in ticketHistory" :key="item.id">
                                    <div class="flex flex-col" :class="{
                                    'items-end': !item.is_agent, 
                                    'items-start': item.is_agent
                                }">
                                        <div class="max-w-[80%] mb-1" :class="{
                                        'ticket-msg-user': !item.is_agent,
                                        'ticket-msg-agent': item.is_agent,
                                        'ticket-msg-internal': item.is_internal
                                    }">
                                            <!-- Message Header -->
                                            <div class="flex items-center justify-between mb-2">
                                                <div class="flex items-center gap-2">
                                                    <!-- Avatar -->
                                                    <div class="ticket-msg-avatar" :class="{
                                                    'ticket-msg-avatar-user': !item.is_agent,
                                                    'ticket-msg-avatar-agent': item.is_agent,
                                                    'ticket-msg-avatar-internal': item.is_internal
                                                }">
                                                        <span x-text="item.user_name?.charAt(0) || 'U'"></span>
                                                    </div>

                                                    <!-- Username -->
                                                    <div>
                                                        <span class="font-medium"
                                                            x-text="item.user_name || 'Unknown'"></span>
                                                        <div x-show="item.is_agent"
                                                            class="ticket-badge ticket-badge-agent">
                                                            Support Agent
                                                        </div>
                                                        <div x-show="item.is_internal"
                                                            class="ticket-badge ticket-badge-internal">
                                                            Internal Note
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Timestamp -->
                                                <span class="ticket-msg-time"
                                                    x-text="formatDate(item.created_at)"></span>
                                            </div>

                                            <!-- Message Content -->
                                            <div class="text-sm mt-1" x-html="item.content"></div>

                                            <!-- Attachments -->
                                            <div x-show="item.attachments && item.attachments.length > 0"
                                                class="mt-3 pt-2 border-t border-gray-200">
                                                <p class="text-xs font-medium text-gray-500 mb-1">Attachments:</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <template x-for="(file, fileIndex) in item.attachments"
                                                        :key="fileIndex">
                                                        <div class="ticket-attachment">
                                                            <i class="fas fa-paperclip ticket-attachment-icon"></i>
                                                            <span x-text="file.filename || file.name"
                                                                @click="isImageAttachment(file) ? openImageViewer(file) : downloadAttachment(file)"
                                                                class="ticket-attachment-name"></span>
                                                            <button @click="downloadAttachment(file)"
                                                                class="ticket-attachment-action" title="Download">
                                                                <i class="fas fa-download"></i>
                                                            </button>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- Empty state when no history exists -->
                                <div x-show="ticketHistory.length === 0" class="text-center py-6 text-gray-500">
                                    <i class="fas fa-comments text-4xl mb-2"></i>
                                    <p>No message history yet</p>
                                </div>
                            </div>
                        </div>

                        <!-- Reply Form only shown for open tickets -->
                        <div x-show="currentTicket && currentTicket.status !== 'closed'" class="mt-6 border-t pt-6">
                            <h3 class="text-sm font-medium text-gray-500 mb-4">Reply to Ticket</h3>
                            <div class="space-y-4">
                                <div>
                                    <textarea x-ref="replyContent"
                                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                        rows="4" placeholder="Type your reply here..."></textarea>
                                </div>
                                <div>
                                    <input type="file" x-ref="replyAttachment"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                    <p class="mt-1 text-xs text-gray-500">Allowed file types: JPEG, PNG, PDF, TXT. Max
                                        size: 5MB</p>
                                </div>
                                <div class="flex justify-end">
                                    <button @click="submitReply()"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        :disabled="isSubmitting"
                                        :class="{ 'opacity-70 cursor-not-allowed': isSubmitting }">
                                        <span x-show="isSubmitting">
                                            <i class="fas fa-spinner fa-spin mr-2"></i>Sending...
                                        </span>
                                        <span x-show="!isSubmitting">Send Reply</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Closed ticket message -->
                        <div x-show="currentTicket && currentTicket.status === 'closed'"
                            class="bg-gray-50 p-4 rounded-lg text-center">
                            <i class="fas fa-lock text-gray-400 text-2xl mb-2"></i>
                            <p class="text-gray-600">This ticket is closed. Please create a new ticket if you need
                                further assistance.</p>
                        </div>
                    </div>
                </template>

                <!-- Loading state -->
                <div x-show="!currentTicket" class="flex flex-col items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
                    <p class="mt-4 text-gray-600">Loading ticket details...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.ticketDetailsModal = function () {
            return {
                isOpen: false,
                currentTicket: null,
                ticketHistory: [],
                attachments: [],
                isSubmitting: false,

                openModal(ticketId) {
                    console.log("Opening modal for ticket:", ticketId);
                    this.isOpen = true;
                    document.body.classList.add('overflow-hidden');
                    this.loadTicketDetails(ticketId);
                    this.loadTicketHistory(ticketId);
                    this.loadTicketAttachments(ticketId);
                },

                closeModal() {
                    this.isOpen = false;
                    this.currentTicket = null;
                    this.ticketHistory = [];
                    this.attachments = [];
                    document.body.classList.remove('overflow-hidden');
                },

                async loadTicketDetails(ticketId) {
                    try {
                        const response = await fetch(`../admin/ajax/ajax_handlers.php?action=get_ticket_details&ticket_id=${ticketId}`);
                        const data = await response.json();

                        if (data.success) {
                            this.currentTicket = data.ticket;
                            console.log("Loaded ticket details:", this.currentTicket);
                        } else {
                            this.showNotification('Error loading ticket details: ' + (data.error || 'Unknown error'), 'error');
                        }
                    } catch (error) {
                        console.error('Failed to load ticket details:', error);
                        this.showNotification('Failed to load ticket details. Please try again.', 'error');
                    }
                },

                async loadTicketHistory(ticketId) {
                    try {
                        const response = await fetch(`../admin/ajax/ajax_handlers.php?action=get_ticket_history&ticket_id=${ticketId}`);
                        const data = await response.json();

                        if (data.success) {
                            console.log("Raw history data:", data.history);

                            // Process history data to identify agent vs user messages
                            this.ticketHistory = data.history.map(item => {
                                // Use commenter_type to determine if it's an agent or not
                                const isAgent = item.commenter_type === 'staff' || item.staff_id;
                                return {
                                    ...item,
                                    is_agent: isAgent,
                                    is_internal: item.is_internal === '1' || item.is_internal === true
                                };
                            });

                            console.log("Processed history:", this.ticketHistory);
                        } else {
                            this.showNotification('Error loading ticket history: ' + (data.error || 'Unknown error'), 'error');
                        }
                    } catch (error) {
                        console.error('Failed to load ticket history:', error);
                        this.showNotification('Failed to load ticket history. Please try again.', 'error');
                    }
                },

                async loadTicketAttachments(ticketId) {
                    try {
                        const response = await fetch(`../admin/ajax/ajax_handlers.php?action=get_ticket_attachments&ticket_id=${ticketId}`);
                        const data = await response.json();

                        if (data.success) {
                            this.attachments = data.attachments;
                            console.log("Loaded attachments:", this.attachments);
                        } else {
                            console.error('Error loading attachments:', data.error);
                        }
                    } catch (error) {
                        console.error('Failed to load attachments:', error);
                    }
                },

                async downloadAttachment(attachment) {
                    if (!attachment || !attachment.id) {
                        console.error('Invalid attachment:', attachment);
                        return;
                    }

                    window.location.href = `../admin/ajax/ajax_handlers.php?action=download_attachment&ticket_id=${this.currentTicket.id}&attachment_id=${attachment.id}`;
                },

                isImageAttachment(attachment) {
                    if (!attachment || !attachment.filename) return false;

                    const filename = attachment.filename;
                    const ext = filename.split('.').pop().toLowerCase();
                    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

                    return imageExtensions.includes(ext);
                },

                openImageViewer(attachment) {
                    // For simple implementation, just open the image in a new tab
                    // You could implement a proper image viewer modal later
                    const url = `../admin/ajax/ajax_handlers.php?action=view_attachment&ticket_id=${this.currentTicket.id}&attachment_id=${attachment.id}`;
                    window.open(url, '_blank');
                },

                formatDate(dateString) {
                    if (!dateString) return '';

                    const date = new Date(dateString);
                    const now = new Date();
                    const diffMs = now - date;
                    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

                    if (diffDays < 1) {
                        // Today - show time only
                        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    } else if (diffDays < 2) {
                        // Yesterday
                        return `Yesterday at ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
                    } else if (diffDays < 7) {
                        // Within a week
                        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        return `${days[date.getDay()]} at ${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
                    } else {
                        // Older than a week
                        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    }
                },

                async submitReply() {
                    if (!this.currentTicket || this.isSubmitting) return;

                    const content = this.$refs.replyContent.value.trim();
                    if (!content) {
                        this.showNotification('Please enter a reply', 'error');
                        return;
                    }

                    this.isSubmitting = true;

                    const formData = new FormData();
                    formData.append('ticket_id', this.currentTicket.id);
                    formData.append('content', content);

                    // Also send the user ID explicitly if available in the window object
                    if (window.currentUserId) {
                        formData.append('user_id', window.currentUserId);
                    }

                    // Add attachment if present
                    const attachment = this.$refs.replyAttachment.files[0];
                    if (attachment) {
                        formData.append('attachment', attachment);
                    }

                    try {
                        const response = await fetch('../admin/ajax/ajax_handlers.php?action=add_ticket_reply', {
                            method: 'POST',
                            body: formData,
                            // Include credentials to ensure cookies (and thus session) are sent
                            credentials: 'same-origin'
                        });

                        const data = await response.json();

                        if (data.success) {
                            // Clear form
                            this.$refs.replyContent.value = '';
                            this.$refs.replyAttachment.value = '';

                            // Reload ticket information
                            await this.loadTicketDetails(this.currentTicket.id);
                            await this.loadTicketHistory(this.currentTicket.id);

                            this.showNotification('Reply sent successfully', 'success');
                        } else {
                            this.showNotification('Error sending reply: ' + (data.error || 'Unknown error'), 'error');
                        }
                    } catch (error) {
                        console.error('Failed to send reply:', error);
                        this.showNotification('Failed to send reply. Please try again.', 'error');
                    } finally {
                        this.isSubmitting = false;
                    }
                },

                showNotification(message, type = 'info') {
                    const notification = document.createElement('div');
                    notification.classList.add(
                        'ticket-notification',
                        type === 'success' ? 'ticket-notification-success' : 'ticket-notification-error'
                    );
                    notification.textContent = message;

                    document.body.appendChild(notification);

                    setTimeout(() => {
                        notification.classList.add('ticket-notification-fade');
                        setTimeout(() => {
                            if (document.body.contains(notification)) {
                                document.body.removeChild(notification);
                            }
                        }, 500);
                    }, 3000);
                },

                // Helper function to capitalize first letter (for status display)
                ucfirst(string) {
                    if (!string) return '';
                    return string.charAt(0).toUpperCase() + string.slice(1);
                }
            };
        };

        document.addEventListener('DOMContentLoaded', function () {
            const categorySelect = document.getElementById('category');
            const subcategorySelect = document.getElementById('subcategory');

            if (categorySelect && subcategorySelect) {
                categorySelect.addEventListener('change', function () {
                    const categoryId = this.value;
                    subcategorySelect.innerHTML = '<option value="">Select a subcategory</option>';

                    if (categoryId) {
                        // Fetch subcategories for the selected category
                        fetch(`get_subcategories.php?category_id=${categoryId}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.length > 0) {
                                    data.forEach(subcategory => {
                                        const option = document.createElement('option');
                                        option.value = subcategory.id;
                                        option.textContent = subcategory.name;
                                        subcategorySelect.appendChild(option);
                                    });
                                }
                            })
                            .catch(error => console.error('Error fetching subcategories:', error));
                    }
                });
            }
        });
    </script>
</body>

</html>