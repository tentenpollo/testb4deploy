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

        case 'delete_attachment':
            if (isset($_POST['attachment_id']) && isset($_POST['ticket_id'])) {
                $attachment_id = $_POST['attachment_id'];
                $ticket_id = $_POST['ticket_id'];

                $result = delete_ticket_attachment($attachment_id, $ticket_id);

                if ($result) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to delete attachment']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
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
                $user_id = $_SESSION['user_id'];

                $user_type = 'user';

                if (isset($_SESSION['is_staff'])) {
                    $user_type = 'staff';
                    $user_id = $_SESSION['user_id'];
                } elseif (isset($_SESSION['guest_email'])) {
                    $user_type = 'guest';
                    $user_id = $_SESSION['guest_email']; // Use email as identifier
                }

                // Additional admin check
                $isAdmin = isset($_SESSION['staff_role']) && $_SESSION['staff_role'] === 'admin';

                // Process any attachments
                $attachments = [];
                if (isset($_FILES['attachments'])) {
                    // If multiple files were uploaded
                    if (is_array($_FILES['attachments']['name'])) {
                        for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                            if ($_FILES['attachments']['name'][$i] !== '' && $_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                                $attachments[] = [
                                    'name' => $_FILES['attachments']['name'][$i],
                                    'type' => $_FILES['attachments']['type'][$i],
                                    'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                                    'error' => $_FILES['attachments']['error'][$i],
                                    'size' => $_FILES['attachments']['size'][$i]
                                ];
                            }
                        }
                    } else {
                        // Single file upload
                        if ($_FILES['attachments']['name'] !== '' && $_FILES['attachments']['error'] === UPLOAD_ERR_OK) {
                            $attachments[] = $_FILES['attachments'];
                        }
                    }
                }

                // Call the modified function with updated parameter order
                $result = add_ticket_comment($ticket_id, $user_id, $content, $attachments, $user_type, $is_private);

                if ($result) {
                    echo json_encode(['success' => true, 'comment_id' => $result]);
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

        case 'view_attachment':
            if (isset($_GET['ticket_id'])) {
                $ticket_id = $_GET['ticket_id'];
                $db = db_connect();
                $ticket_id = $db->real_escape_string($ticket_id);

                if (isset($_GET['attachment_id'])) {
                    $attachment_id = $_GET['attachment_id'];
                    $attachment_id = $db->real_escape_string($attachment_id);
                    $sql = "SELECT * FROM attachments WHERE id = '$attachment_id' AND ticket_id = '$ticket_id'";
                } elseif (isset($_GET['filename']) && isset($_GET['comment_id'])) {
                    $filename = urldecode($_GET['filename']);
                    $comment_id = $_GET['comment_id'];
                    $comment_id = $db->real_escape_string($comment_id);
                    $sql = "SELECT * FROM attachments WHERE ticket_id = '$ticket_id' AND comment_id = '$comment_id'";
                } elseif (isset($_GET['filename'])) {
                    $filename = urldecode($_GET['filename']);
                    $sql = "SELECT * FROM attachments WHERE ticket_id = '$ticket_id'";
                } else {
                    header('HTTP/1.1 400 Bad Request');
                    echo 'Missing required parameters';
                    break;
                }

                $result = $db->query($sql);

                if (!$result) {
                    header('HTTP/1.1 500 Internal Server Error');
                    echo 'Database error';
                    break;
                }

                $attachment = null;

                // Direct attachment ID lookup should return just one row
                if (isset($_GET['attachment_id'])) {
                    if ($result->num_rows == 1) {
                        $attachment = $result->fetch_assoc();
                    }
                }
                // Filename lookup might need to check multiple attachments
                elseif (isset($_GET['filename'])) {
                    $filename = urldecode($_GET['filename']);

                    while ($row = $result->fetch_assoc()) {
                        $stored_filename = basename($row['file_path']);

                        // Try multiple matching methods (exact, prefix, case-insensitive)
                        if (
                            $stored_filename === $filename ||
                            preg_match('/^\d+_' . preg_quote($filename, '/') . '$/', $stored_filename) ||
                            strcasecmp($stored_filename, $filename) === 0
                        ) {
                            $attachment = $row;
                            break;
                        }
                    }
                }

                if ($attachment) {
                    $file_path = $attachment['file_path'];

                    if (file_exists($file_path)) {
                        // Get file extension and determine content type
                        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                        $content_types = [
                            'png' => 'image/png',
                            'jpg' => 'image/jpeg',
                            'jpeg' => 'image/jpeg',
                            'gif' => 'image/gif',
                            'svg' => 'image/svg+xml',
                            'webp' => 'image/webp',
                            'bmp' => 'image/bmp'
                        ];

                        // Only serve image types for viewing
                        if (isset($content_types[$file_ext])) {
                            $content_type = $content_types[$file_ext];

                            // Clean output buffer
                            while (ob_get_level()) {
                                ob_end_clean();
                            }

                            // Set headers for displaying the image
                            header('Content-Type: ' . $content_type);
                            header('Content-Length: ' . filesize($file_path));

                            // Output file
                            readfile($file_path);
                            exit;
                        } else {
                            // Not an image type
                            header('HTTP/1.1 400 Bad Request');
                            echo 'Not a viewable image type';
                        }
                    } else {
                        // Try alternative paths as in the download handler
                        $alternative_paths = [
                            $_SERVER['DOCUMENT_ROOT'] . '/' . $file_path,
                            $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . basename($file_path),
                            dirname($_SERVER['SCRIPT_FILENAME']) . '/../uploads/' . basename($file_path),
                            // Add more as needed
                        ];

                        $found = false;
                        foreach ($alternative_paths as $alt_path) {
                            if (file_exists($alt_path)) {
                                $file_ext = strtolower(pathinfo($alt_path, PATHINFO_EXTENSION));

                                if (isset($content_types[$file_ext])) {
                                    $content_type = $content_types[$file_ext];

                                    while (ob_get_level()) {
                                        ob_end_clean();
                                    }

                                    header('Content-Type: ' . $content_type);
                                    header('Content-Length: ' . filesize($alt_path));
                                    header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
                                    readfile($alt_path);
                                    $found = true;
                                    exit;
                                }
                            }
                        }

                        if (!$found) {
                            header('HTTP/1.1 404 Not Found');
                            echo 'File not found';
                        }
                    }
                } else {
                    header('HTTP/1.1 404 Not Found');
                    echo 'Attachment not found';
                }
            } else {
                header('HTTP/1.1 400 Bad Request');
                echo 'Missing ticket ID';
            }
            break;

        case 'download_attachment':
            if (isset($_GET['ticket_id'])) {
                $ticket_id = $_GET['ticket_id'];
                $db = db_connect();
                $ticket_id = $db->real_escape_string($ticket_id);

                error_log("Download request - Ticket ID: $ticket_id");

                // If attachment_id is provided, use that for direct lookup
                if (isset($_GET['attachment_id'])) {
                    $attachment_id = $_GET['attachment_id'];
                    $attachment_id = $db->real_escape_string($attachment_id);

                    error_log("Looking up by attachment ID: $attachment_id");
                    $sql = "SELECT * FROM attachments WHERE id = '$attachment_id' AND ticket_id = '$ticket_id'";

                } elseif (isset($_GET['filename']) && isset($_GET['comment_id'])) {
                    // Legacy format - using filename and comment_id
                    $filename = urldecode($_GET['filename']);
                    $comment_id = $_GET['comment_id'];
                    $comment_id = $db->real_escape_string($comment_id);

                    error_log("Looking up by filename and comment_id: $filename, $comment_id");
                    $sql = "SELECT * FROM attachments WHERE ticket_id = '$ticket_id' AND comment_id = '$comment_id'";

                } elseif (isset($_GET['filename'])) {
                    // Just using filename without comment_id
                    $filename = urldecode($_GET['filename']);

                    error_log("Looking up by filename only: $filename");
                    $sql = "SELECT * FROM attachments WHERE ticket_id = '$ticket_id'";

                } else {
                    header('HTTP/1.1 400 Bad Request');
                    echo 'Missing required parameters';
                    break;
                }

                error_log("SQL Query: $sql");
                $result = $db->query($sql);

                if (!$result) {
                    error_log("Database error: " . $db->error);
                    header('HTTP/1.1 500 Internal Server Error');
                    echo 'Database error';
                    break;
                }

                $attachment = null;

                // Direct attachment ID lookup should return just one row
                if (isset($_GET['attachment_id'])) {
                    if ($result->num_rows == 1) {
                        $attachment = $result->fetch_assoc();
                    }
                }
                // Filename lookup might need to check multiple attachments
                elseif (isset($_GET['filename'])) {
                    $filename = urldecode($_GET['filename']);

                    while ($row = $result->fetch_assoc()) {
                        $stored_filename = basename($row['file_path']);

                        // Try multiple matching methods for filename
                        if (
                            $stored_filename === $filename ||
                            preg_match('/^\d+_' . preg_quote($filename, '/') . '$/', $stored_filename) ||
                            strcasecmp($stored_filename, $filename) === 0
                        ) {
                            $attachment = $row;
                            error_log("Found filename match: $stored_filename");
                            break;
                        }
                    }
                }

                if ($attachment) {
                    $file_path = $attachment['file_path'];
                    error_log("Found attachment with path: $file_path");

                    // Define content types for various file types
                    $content_types = [
                        'png' => 'image/png',
                        'jpg' => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'gif' => 'image/gif',
                        'svg' => 'image/svg+xml',
                        'webp' => 'image/webp',
                        'bmp' => 'image/bmp',
                        'pdf' => 'application/pdf',
                        'doc' => 'application/msword',
                        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'xls' => 'application/vnd.ms-excel',
                        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'txt' => 'text/plain',
                        'csv' => 'text/csv',
                        'zip' => 'application/zip',
                        'rar' => 'application/x-rar-compressed'
                    ];

                    // Function to output a file for download
                    $serve_file = function ($path) use ($content_types) {
                        // Ensure we have a valid path
                        if (!file_exists($path) || !is_readable($path)) {
                            error_log("File not readable: $path");
                            header('HTTP/1.1 404 Not Found');
                            echo 'File not readable';
                            exit;
                        }

                        // Get file extension and determine content type
                        $file_ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                        error_log("Serving file with extension: $file_ext");

                        // Get proper content type or default to binary
                        $content_type = isset($content_types[$file_ext]) ?
                            $content_types[$file_ext] : 'application/octet-stream';

                        // Clean output buffer - very important!
                        while (ob_get_level()) {
                            ob_end_clean();
                        }

                        // Get the file size
                        $filesize = filesize($path);

                        // Set proper headers for file download
                        header('Content-Description: File Transfer');
                        header('Content-Type: ' . $content_type);
                        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
                        header('Content-Transfer-Encoding: binary');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                        header('Pragma: public');
                        header('Content-Length: ' . $filesize);

                        // Disable output buffering completely
                        if (ob_get_level()) {
                            ob_end_clean();
                        }

                        // Read the file and output it directly
                        $handle = fopen($path, 'rb');
                        if ($handle) {
                            while (!feof($handle)) {
                                echo fread($handle, 8192);
                                flush(); // Flush after each chunk
                            }
                            fclose($handle);
                        } else {
                            error_log("Could not open file handle for: $path");
                        }

                        exit;
                    };

                    // Check if file exists at the stored path
                    if (file_exists($file_path)) {
                        error_log("File exists at: $file_path");
                        $serve_file($file_path);
                    } else {
                        error_log("File not found at path: $file_path");

                        // Try alternative paths
                        $alt_paths = [
                            $_SERVER['DOCUMENT_ROOT'] . '/' . $file_path,
                            $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . basename($file_path),
                            dirname($_SERVER['SCRIPT_FILENAME']) . '/../uploads/' . basename($file_path),
                            dirname($_SERVER['SCRIPT_FILENAME']) . '/../uploads/tickets/' . $ticket_id . '/' . basename($file_path)
                        ];

                        $found = false;
                        foreach ($alt_paths as $alt_path) {
                            error_log("Checking alternative path: $alt_path");
                            if (file_exists($alt_path)) {
                                error_log("File found at alternative path: $alt_path");
                                $serve_file($alt_path);
                                $found = true;
                                break; // No need for exit since serve_file already calls exit
                            }
                        }

                        if (!$found) {
                            header('HTTP/1.1 404 Not Found');
                            echo 'File not found on server';
                        }
                    }
                } else {
                    error_log("No matching attachment found in database");
                    header('HTTP/1.1 404 Not Found');
                    echo 'No matching attachment found';
                }
            } else {
                header('HTTP/1.1 400 Bad Request');
                echo 'Missing ticket ID';
            }
            break;

        case 'get_assignable_users':
            $users = get_assignable_users();
            echo json_encode(['success' => true, 'users' => $users]);
            break;

        case 'get_all_tickets':
            try {
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
<script>
    // JavaScript:
    downloadAttachment(attachment) {
        // Determine if this is an image file that can be previewed
        const isImage = this.isImageAttachment(attachment);

        // Base URL and parameters are the same for both types
        let params = `ticket_id=${this.currentTicket.id}`;

        // Add identifier parameters based on what's available
        if (attachment.id) {
            params += `&attachment_id=${attachment.id}`;
        } else if (attachment.comment_id) {
            const filename = attachment.filename || attachment.name || this.basename(attachment.file_path);
            params += `&comment_id=${attachment.comment_id}&filename=${encodeURIComponent(filename)}`;
        } else {
            const filename = attachment.filename || attachment.name || this.basename(attachment.file_path);
            params += `&filename=${encodeURIComponent(filename)}`;
        }

        if (isImage) {
            // For images, use a special case
            const downloadUrl = `ajax/image_download.php?${params}`;
            window.location.href = downloadUrl;
        } else {
            // For non-images, use the regular download_attachment
            const downloadUrl = `ajax/ajax_handlers.php?action=download_attachment&${params}`;
            window.location.href = downloadUrl;
        }
    }
</script>