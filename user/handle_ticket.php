<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/email_functions.php'; // Make sure this includes your send_email_smtp function

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

if (!$is_logged_in) {
    header('Location: ../guest_ticket.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $category_id = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_NUMBER_INT);
    $subcategory_id = filter_input(INPUT_POST, 'subcategory_id', FILTER_SANITIZE_NUMBER_INT);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);

    $mysqli = db_connect();

    // Start transaction
    $mysqli->begin_transaction();

    try {
        // Generate a unique reference ID
        $reference_id = generate_reference_id();

        $query = "INSERT INTO tickets (
            user_id, 
            category_id, 
            subcategory_id,
            title, 
            description, 
            status, 
            created_by, 
            created_at, 
            updated_at, 
            ref_id
        ) VALUES (?, ?, ?, ?, ?, 'unseen', 'user', NOW(), NOW(), ?)";

        $stmt = $mysqli->prepare($query);
        $stmt->bind_param("iiisss", $user_id, $category_id, $subcategory_id, $subject, $description, $reference_id);

        if ($stmt->execute()) {
            $ticket_id = $mysqli->insert_id;

            // Handle file upload if present
            $attachment_file_path = null;
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
                        $attachment_file_path = $file_path; // Save for email attachment
                        
                        $attach_query = "INSERT INTO attachments (ticket_id, filename, file_path, uploaded_by_user_id, created_at) 
                                       VALUES (?, ?, ?, ?, NOW())";
                        $attach_stmt = $mysqli->prepare($attach_query);
                        $attach_stmt->bind_param("issi", $ticket_id, $filename, $file_path, $user_id);
                        $attach_stmt->execute();
                    }
                }
            }

            // Get user information for the email
            $user_query = "SELECT first_name, last_name, email FROM users WHERE user_id = ?";
            $user_stmt = $mysqli->prepare($user_query);
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            
            // Get category name
            $category_query = "SELECT name FROM categories WHERE id = ?";
            $category_stmt = $mysqli->prepare($category_query);
            $category_stmt->bind_param("i", $category_id);
            $category_stmt->execute();
            $category_result = $category_stmt->get_result();
            $category_data = $category_result->fetch_assoc();
            
            $mysqli->commit();
            
            // Send email notification
            $customer_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
            $customer_email = $user_data['email'];
            
            // Prepare replacements for email template
            $replacements = [
                'customer_name' => $customer_name,
                'reference_id' => $reference_id,
                'ticket_subject' => $subject,
                'category_name' => $category_data['name'] ?? 'Uncategorized',
                'created_date' => date('F j, Y, g:i a'),
                'ticket_description' => nl2br(htmlspecialchars($description)),
                'ticket_url' => 'https://yourdomain.com/view_ticket.php?id=' . $ticket_id,
                'current_year' => date('Y')
            ];
            
            // Get email template
            $email_body = get_email_template('new_ticket', $replacements);
            
            // Prepare attachments for the email
            $email_attachments = [];
            if ($attachment_file_path && file_exists($attachment_file_path)) {
                $email_attachments[] = $attachment_file_path;
            }
            
            // Send the email
            if (!empty($email_body) && !empty($customer_email)) {
                send_email_smtp(
                    $customer_email,
                    "Your Ticket Has Been Created - " . $reference_id,
                    $email_body,
                    '',
                    $email_attachments
                );
                
                // Also send a copy to the support team if needed
                // send_email_smtp('support@yourcompany.com', "New Ticket Created - " . $reference_id, $email_body);
            }
            
            $_SESSION['success'] = "Ticket created successfully! Your reference ID is: $reference_id";
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

/**
 * Generate a unique reference ID for tickets
 * Format: TKT-YYMM-XXXXX (where XXXXX is a random alphanumeric string)
 *
 * @return string The generated reference ID
 */
function generate_reference_id()
{
    // Get current year and month
    $year_month = date('ym');

    // Generate a random string of 5 alphanumeric characters
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $random_string = '';

    for ($i = 0; $i < 5; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }

    // Create the reference ID in format TKT-YYMM-XXXXX
    $reference_id = "TKT-{$year_month}-{$random_string}";

    // Check if this reference ID already exists in the database
    $mysqli = db_connect();
    $stmt = $mysqli->prepare("SELECT id FROM tickets WHERE ref_id = ?");
    $stmt->bind_param("s", $reference_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // If the reference ID already exists, generate a new one recursively
    if ($result->num_rows > 0) {
        return generate_reference_id();
    }

    return $reference_id;
}