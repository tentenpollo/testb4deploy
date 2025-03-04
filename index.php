<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
$pageTitle = "Welcome to Help Desk";
include 'includes/header.php';
?>

<div class="welcome-container">
    <div class="welcome-card">
        <h1>Welcome to Our Help Desk</h1>
        <p>Get support by creating a ticket or track an existing one.</p>
        
        <div class="button-group">
            <a href="register.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Create Account
            </a>
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
            <a href="guest.php" class="btn btn-outline">
                <i class="fas fa-ticket-alt"></i> Continue as Guest
            </a>
        </div>
        
        <div class="info-box">
            <h3>Why Create an Account?</h3>
            <ul>
                <li>Track all your support tickets in one place</li>
                <li>Receive email notifications on ticket updates</li>
                <li>Access your ticket history anytime</li>
            </ul>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>