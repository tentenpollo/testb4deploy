<?php
require_once '../includes/config.php';

// Function to fetch ticket statistics
function getTicketStatistics() {
    global $conn;

    $stats = [];

    // Total tickets
    $sql = "SELECT COUNT(*) as total_tickets FROM tickets";
    $result = mysqli_query($conn, $sql);
    $stats['total_tickets'] = mysqli_fetch_assoc($result)['total_tickets'];

    // Open tickets
    $sql = "SELECT COUNT(*) as open_tickets FROM tickets WHERE status = 'open'";
    $result = mysqli_query($conn, $sql);
    $stats['open_tickets'] = mysqli_fetch_assoc($result)['open_tickets'];

    // Closed tickets
    $sql = "SELECT COUNT(*) as closed_tickets FROM tickets WHERE status = 'closed'";
    $result = mysqli_query($conn, $sql);
    $stats['closed_tickets'] = mysqli_fetch_assoc($result)['closed_tickets'];

    // Tickets by priority
    $sql = "SELECT p.name as priority, COUNT(t.id) as count 
            FROM tickets t 
            JOIN priorities p ON t.priority_id = p.id 
            GROUP BY p.name";
    $result = mysqli_query($conn, $sql);
    $stats['tickets_by_priority'] = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Tickets by category
    $sql = "SELECT c.name as category, COUNT(t.id) as count 
            FROM tickets t 
            JOIN categories c ON t.category_id = c.id 
            GROUP BY c.name";
    $result = mysqli_query($conn, $sql);
    $stats['tickets_by_category'] = mysqli_fetch_all($result, MYSQLI_ASSOC);

    // Tickets by status
    $sql = "SELECT status, COUNT(id) as count 
            FROM tickets 
            GROUP BY status";
    $result = mysqli_query($conn, $sql);
    $stats['tickets_by_status'] = mysqli_fetch_all($result, MYSQLI_ASSOC);

    return $stats;
}

// Fetch ticket statistics
$ticketStats = getTicketStatistics();
?>

<!-- Reports Content -->
<div x-data="reportManager()" x-show="activeView === 'reports'" class="p-6 space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Reports</h1>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700">Total Tickets</h2>
            <p class="text-3xl font-bold text-blue-600"><?php echo $ticketStats['total_tickets']; ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700">Open Tickets</h2>
            <p class="text-3xl font-bold text-yellow-600"><?php echo $ticketStats['open_tickets']; ?></p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700">Closed Tickets</h2>
            <p class="text-3xl font-bold text-green-600"><?php echo $ticketStats['closed_tickets']; ?></p>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Tickets by Priority Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Tickets by Priority</h2>
            <canvas id="priorityChart"></canvas>
        </div>

        <!-- Tickets by Category Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Tickets by Category</h2>
            <canvas id="categoryChart"></canvas>
        </div>

        <!-- Tickets by Status Chart -->
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Tickets by Status</h2>
            <canvas id="statusChart"></canvas>
        </div>
    </div>

    <!-- Tickets by Priority Table -->
    <div class="bg-white p-6 rounded-lg shadow-md mt-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Tickets by Priority</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Count</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($ticketStats['tickets_by_priority'] as $priority): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $priority['priority']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $priority['count']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Tickets by Category Table -->
    <div class="bg-white p-6 rounded-lg shadow-md mt-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">Tickets by Category</h2>
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Count</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($ticketStats['tickets_by_category'] as $category): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $category['category']; ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?php echo $category['count']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Chart.js Configuration
    const priorityData = {
        labels: <?php echo json_encode(array_column($ticketStats['tickets_by_priority'], 'priority')); ?>,
        datasets: [{
            label: 'Tickets by Priority',
            data: <?php echo json_encode(array_column($ticketStats['tickets_by_priority'], 'count')); ?>,
            backgroundColor: ['#3b82f6', '#f59e0b', '#ef4444', '#10b981'],
        }]
    };

    const categoryData = {
        labels: <?php echo json_encode(array_column($ticketStats['tickets_by_category'], 'category')); ?>,
        datasets: [{
            label: 'Tickets by Category',
            data: <?php echo json_encode(array_column($ticketStats['tickets_by_category'], 'count')); ?>,
            backgroundColor: ['#3b82f6', '#f59e0b', '#ef4444', '#10b981'],
        }]
    };

    const statusData = {
        labels: <?php echo json_encode(array_column($ticketStats['tickets_by_status'], 'status')); ?>,
        datasets: [{
            label: 'Tickets by Status',
            data: <?php echo json_encode(array_column($ticketStats['tickets_by_status'], 'count')); ?>,
            backgroundColor: ['#3b82f6', '#f59e0b', '#ef4444', '#10b981'],
        }]
    };

    // Render Charts
    new Chart(document.getElementById('priorityChart'), {
        type: 'pie',
        data: priorityData,
    });

    new Chart(document.getElementById('categoryChart'), {
        type: 'bar',
        data: categoryData,
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: statusData,
    });
</script>