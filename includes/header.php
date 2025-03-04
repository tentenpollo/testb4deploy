<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - Help Desk' : 'Help Desk'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/normalize.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <script>
        // Add this script to ensure logout works
        document.addEventListener('DOMContentLoaded', function() {
            const logoutLink = document.getElementById('logout-link');
            if (logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent any default behavior
                    window.location.href = 'logout.php'; // Force redirect to logout.php
                });
            }
        });
    </script>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="index.php">
                        <span class="logo-icon"><i class="fas fa-headset"></i></span>
                        <span class="logo-text">Help Desk</span>
                    </a>
                </div>
                
                <nav class="main-nav">
                    <ul>
                        <?php if (is_logged_in()): ?>
                            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <li><a href="create_ticket.php"><i class="fas fa-plus-circle"></i> New Ticket</a></li>
                            <li>
                                <a href="#" class="user-menu-toggle">
                                    <i class="fas fa-user-circle"></i>
                                    <?php 
                                    $user = get_user_data($_SESSION['user_id']);
                                    echo htmlspecialchars($user['first_name']);
                                    ?>
                                    <i class="fas fa-chevron-down"></i>
                                </a>
                                <ul class="dropdown-menu">
                                    <li><a href="profile.php"><i class="fas fa-id-card"></i> My Profile</a></li>
                                    <li><a href="logout.php" id="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                </ul>
                            </li>
                        <?php elseif (is_guest()): ?>
                            <li><a href="create_ticket.php"><i class="fas fa-plus-circle"></i> New Ticket</a></li>
                            <li><a href="register.php"><i class="fas fa-user-plus"></i> Create Account</a></li>
                        <?php else: ?>
                            <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                            <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                            <li><a href="guest.php"><i class="fas fa-ticket-alt"></i> Guest Ticket</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>
    
    <main class="site-main">
        <div class="container">