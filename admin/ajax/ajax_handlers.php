<?php
header('Content-Type: application/json');

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../../includes/config.php';
require_once '../ticket_functions.php';


// Only start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];


if (isset($_GET['action'])) {
    $action = $_GET['action'];

    switch ($action) {
        case 'get_ticket_details':
            if (isset($_GET['ticket_id'])) {
                $ticket_id = $_GET['ticket_id'];
                $ticket = get_ticket_details($ticket_id);

                if ($ticket) {
                    echo json_encode(['success' => true, 'ticket' => $ticket]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Ticket not found']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
            }
            break;

        case 'get_ticket_history':
            if (isset($_GET['ticket_id'])) {
                $ticket_id = $_GET['ticket_id'];
                $history = get_ticket_history($ticket_id);

                echo json_encode(['success' => true, 'history' => $history]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
            }
            break;

        case 'get_ticket_attachments':
            if (isset($_GET['ticket_id'])) {
                $ticket_id = $_GET['ticket_id'];
                $attachments = get_ticket_attachments($ticket_id);

                echo json_encode(['success' => true, 'attachments' => $attachments]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Ticket ID required']);
            }
            break;

        case 'update_ticket_status':
            if (isset($_POST['ticket_id']) && isset($_POST['status'])) {
                $ticket_id = $_POST['ticket_id'];
                $new_status = $_POST['status'];

                $result = update_ticket_status($ticket_id, $user_id, $new_status);

                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to update status']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            }
            break;

        case 'update_ticket_priority':
            if (isset($_POST['ticket_id']) && isset($_POST['priority_id'])) {
                $ticket_id = $_POST['ticket_id'];
                $priority_id = $_POST['priority_id'];

                $result = update_ticket_priority($ticket_id, $user_id, $priority_id);

                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to update priority']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            }
            break;

        case 'upload_attachment':
            error_log("Starting upload_attachment process");

            $uploader_id = 0;

            // Check if there's a logged-in staff member
            if (isset($_SESSION['staff_id'])) {
                $uploader_id = $_SESSION['staff_id'];
            }
            // If not a staff member, check if there's a logged-in user
            else if (isset($_SESSION['user_id'])) {
                $uploader_id = $_SESSION['user_id'];
            }

            if (isset($_FILES['attachment']) && isset($_POST['ticket_id'])) {
                $ticket_id = $_POST['ticket_id'];
                error_log("Ticket ID: " . $ticket_id);
                $file = $_FILES['attachment'];
                error_log("File data: " . print_r($file, true));

                if ($file['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $file['tmp_name'];
                    $name = basename($file['name']);
                    $size = $file['size'];

                    $upload_dir = '../../uploads/tickets/' . $ticket_id;
                    error_log("Upload directory: " . $upload_dir);

                    if (!file_exists($upload_dir)) {
                        error_log("Creating directory: " . $upload_dir);
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_path = $upload_dir . '/' . time() . '_' . $name;
                    error_log("Target file path: " . $file_path);

                    if (move_uploaded_file($tmp_name, $file_path)) {
                        error_log("File moved successfully");
                        $attachment_id = add_ticket_attachment($ticket_id, $uploader_id, $name, $file_path, $size);
                        error_log("add_ticket_attachment returned: " . ($attachment_id ? $attachment_id : "false"));

                        if ($attachment_id) {
                            echo json_encode(['success' => true, 'attachment_id' => $attachment_id]);
                            error_log("Success response sent");
                        } else {
                            echo json_encode(['success' => false, 'error' => 'Failed to save attachment to database']);
                            error_log("Database error response sent");
                        }
                    } else {
                        $move_error = error_get_last();
                        error_log("Failed to move uploaded file. Error: " . print_r($move_error, true));
                        echo json_encode(['success' => false, 'error' => 'Failed to upload file: ' . $move_error['message']]);
                    }
                } else {
                    error_log("File upload error code: " . $file['error']);
                    echo json_encode(['success' => false, 'error' => 'File upload error: ' . $file['error']]);
                }
            } else {
                error_log("Missing file or ticket ID. POST data: " . print_r($_POST, true));
                error_log("FILES data: " . print_r($_FILES, true));
                echo json_encode(['success' => false, 'error' => 'Missing file or ticket ID']);
            }
            error_log("End of upload_attachment process");
            break;

        case 'assign_ticket':
            if (isset($_POST['ticket_id']) && isset($_POST['assignee_id'])) {
                $ticket_id = $_POST['ticket_id'];
                $assignee_id = $_POST['assignee_id'];

                $result = assign_ticket($ticket_id, $user_id, $assignee_id);

                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to assign ticket']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            }
            break;

        case 'add_comment':
            if (isset($_POST['ticket_id']) && isset($_POST['content'])) {
                $ticket_id = $_POST['ticket_id'];
                $content = $_POST['content'];
                $is_private = isset($_POST['is_private']) ? (bool) $_POST['is_private'] : false;

                $result = add_ticket_comment($ticket_id, $user_id, $content, $is_private);

                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to add comment']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
            }
            break;

        case 'archive_ticket':
            if (isset($_POST['ticket_id'])) {
                $ticket_id = $_POST['ticket_id'];

                $result = archive_ticket($ticket_id, $user_id);

                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to archive ticket']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing ticket ID']);
            }
            break;

        case 'get_priorities':
            $priorities = get_all_priorities();
            echo json_encode(['success' => true, 'priorities' => $priorities]);
            break;

        case 'get_staff_members':
            $staff_members = get_staff_members();
            echo json_encode([
                'success' => true,
                'staff_members' => $staff_members
            ]);
            break;

        case 'download_attachment':
            if (isset($_GET['ticket_id']) && isset($_GET['filename'])) {
                $ticket_id = $_GET['ticket_id'];
                $filename = $_GET['filename'];

                // Validate that this file belongs to this ticket (security check)
                $ticket_attachments = get_ticket_attachments($ticket_id);
                $valid_attachment = false;

                foreach ($ticket_attachments as $attachment) {
                    if (basename($attachment['file_path']) === $filename) {
                        $valid_attachment = true;
                        $file_path = $attachment['file_path'];
                        break;
                    }
                }

                if ($valid_attachment && file_exists($file_path)) {
                    // Set appropriate content type based on file extension
                    $file_ext = pathinfo($file_path, PATHINFO_EXTENSION);
                    $content_type = 'application/octet-stream'; // Default

                    // Set specific content types for common file types
                    if (strtolower($file_ext) === 'jpg' || strtolower($file_ext) === 'jpeg') {
                        $content_type = 'image/jpeg';
                    } elseif (strtolower($file_ext) === 'png') {
                        $content_type = 'image/png';
                    } elseif (strtolower($file_ext) === 'pdf') {
                        $content_type = 'application/pdf';
                    }

                    header('Content-Description: File Transfer');
                    header('Content-Type: ' . $content_type);
                    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($file_path));
                    // Clear output buffer
                    ob_clean();
                    flush();
                    // Output file
                    readfile($file_path);
                    exit;
                } else {
                    echo json_encode(['success' => false, 'error' => 'File not found']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing ticket ID or filename']);
            }
            break;

        case 'get_assignable_users':
            $users = get_assignable_users();
            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'get_all_tickets':
            try {
                // Log the action for debugging
                error_log("Processing get_all_tickets action");

                // Call the function
                ajax_get_all_tickets();
            } catch (Exception $e) {
                // Log the error
                error_log("Error in get_all_tickets action: " . $e->getMessage());

                // Return error response
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'Server error processing ticket list: ' . $e->getMessage()
                ]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    exit;
}


?>