<?php
require_once '../includes/config.php';  
require_once '../includes/functions.php'; 

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

if (!$is_logged_in) {
    header('Location: ../guest_ticket.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $category_id = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_NUMBER_INT);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    
    $mysqli = db_connect();
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // First insert the ticket
        $query = "INSERT INTO tickets (user_id, category_id, title, description, status, created_by, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, 'unseen', 'user', NOW(), NOW())";
        
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("iiss", $user_id, $category_id, $subject, $description);
        
        if ($stmt->execute()) {
            $ticket_id = $mysqli->insert_id;
            
            // Handle file upload if present
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/tickets/' . $ticket_id . '/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'txt'];
                
                if (in_array($file_extension, $allowed_extensions) && $_FILES['attachment']['size'] <= 5242880) {
                    $filename = uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $file_path)) {
                        $attach_query = "INSERT INTO attachments (ticket_id, filename, file_path, uploaded_by_user_id, created_at) 
                                       VALUES (?, ?, ?, ?, NOW())";
                        $attach_stmt = $mysqli->prepare($attach_query);
                        $attach_stmt->bind_param("issi", $ticket_id, $filename, $file_path, $user_id);
                        $attach_stmt->execute();
                    }
                }
            }
            
            $mysqli->commit();
            $_SESSION['success'] = "Ticket created successfully!";
            header("Location: user_dashboard.php?activeTab=my-tickets");
            exit;
            
        } else {
            throw new Exception("Failed to create ticket");
        }
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error'] = "Failed to create ticket. Please try again.";
        header("Location: user_dashboard.php");
        exit;
    }
}