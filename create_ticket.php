<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect if not a guest or logged-in user
if (!is_guest() && !is_logged_in()) {
    header("Location: guest.php");
    exit;
}

$errors = [];
$success = false;

// Get the guest email from session if available
$guest_email = '';
if (is_guest() && isset($_SESSION['guest_email'])) {
    $guest_email = $_SESSION['guest_email'];
}

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
    $db = db_connect();
    $stmt = $db->prepare("SELECT id FROM tickets WHERE ref_id = ?");
    $stmt->bind_param("s", $reference_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // If the reference ID already exists, generate a new one recursively
    if ($result->num_rows > 0) {
        return generate_reference_id();
    }

    return $reference_id;
}

function getCategories()
{
    $db = db_connect();
    $result = $db->query("SELECT id, name FROM categories ORDER BY name");
    $categories = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[$row['name']] = [];
            $categoryId = $row['id'];

            // Get subcategories for this category
            $subcatStmt = $db->prepare("
                SELECT s.id, s.name 
                FROM subcategories s
                WHERE s.category_id = ?
                ORDER BY s.name
            ");

            if ($subcatStmt) {
                $subcatStmt->bind_param("i", $categoryId);
                if ($subcatStmt->execute()) {
                    $subcatResult = $subcatStmt->get_result();
                    while ($subcatRow = $subcatResult->fetch_assoc()) {
                        $categories[$row['name']][$subcatRow['id']] = $subcatRow['name'];
                    }
                }
            }
        }
    }

    if (empty($categories)) {
        $categories = [
            'Technical' => ['Website Issue', 'Login Problem', 'Performance'],
            'Billing' => ['Payment Issue', 'Refund Request', 'Invoice Problem'],
            'General Inquiry' => ['Feedback', 'Account Inquiry', 'Other']
        ];
    }

    return $categories;
}

$categories = getCategories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = sanitize($_POST['category'] ?? '');
    $subcategory = sanitize($_POST['subcategory'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $guest_email = sanitize($_POST['guest_email'] ?? $guest_email);
    $attachment = $_FILES['attachment'] ?? null;

    // Validate inputs
    if (empty($category)) {
        $errors[] = "Category is required";
    }

    if (empty($subcategory)) {
        $errors[] = "Subcategory is required";
    }

    if (empty($subject)) {
        $errors[] = "Subject is required";
    }

    if (empty($description)) {
        $errors[] = "Description is required";
    }

    if (empty($guest_email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }

    // Validate file attachment (if provided)
    if ($attachment && $attachment['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf', 'text/plain'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($attachment['type'], $allowedTypes)) {
            $errors[] = "Invalid file type. Allowed types: JPEG, PNG, PDF, TXT.";
        }

        if ($attachment['size'] > $maxFileSize) {
            $errors[] = "File size exceeds the maximum limit of 5MB.";
        }
    }

    if (empty($errors)) {
        $db = db_connect();

        // Handle file upload
        $attachmentPath = null;
        if ($attachment && $attachment['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileName = uniqid() . '_' . basename($attachment['name']);
            $attachmentPath = $uploadDir . $fileName;

            if (!move_uploaded_file($attachment['tmp_name'], $attachmentPath)) {
                $errors[] = "Failed to upload file. Please try again.";
            }
        }

        // Get category ID
        $categoryId = null;
        $categoryStmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
        if ($categoryStmt) {
            $categoryStmt->bind_param("s", $category);
            if ($categoryStmt->execute()) {
                $categoryResult = $categoryStmt->get_result();
                if ($row = $categoryResult->fetch_assoc()) {
                    $categoryId = $row['id'];
                }
            }
        }

        if (!$categoryId) {
            $errors[] = "Invalid category. Please try again.";
        }

        // Get subcategory ID
        $subcategoryId = null;
        $subcategoryStmt = $db->prepare("SELECT id FROM subcategories WHERE name = ? AND category_id = ?");
        if ($subcategoryStmt) {
            $subcategoryStmt->bind_param("si", $subcategory, $categoryId);
            if ($subcategoryStmt->execute()) {
                $subcategoryResult = $subcategoryStmt->get_result();
                if ($row = $subcategoryResult->fetch_assoc()) {
                    $subcategoryId = $row['id'];
                }
            }
        }

        if (!$subcategoryId) {
            $errors[] = "Invalid subcategory. Please try again.";
        }

        // Set a default priority (1 for low)
        $defaultPriority = 1;

        // Insert ticket into the database
        if (empty($errors)) {
            // Generate a unique reference ID
            $reference_id = generate_reference_id();

            $stmt = $db->prepare("
            INSERT INTO tickets (
                title, 
                description, 
                status, 
                created_by, 
                guest_email, 
                category_id,
                subcategory_id,
                priority_id, 
                created_at, 
                updated_at,
                ref_id
            ) 
            VALUES (?, ?, 'unseen', 'guest', ?, ?, ?, ?, NOW(), NOW(), ?)
        ");

            if ($stmt === false) {
                $errors[] = "Database error: " . $db->error;
            } else {
                $stmt->bind_param("sssiisi", $subject, $description, $guest_email, $categoryId, $subcategoryId, $defaultPriority, $reference_id);
                if ($stmt->execute()) {
                    $ticketId = $db->insert_id;

                    // If there's an attachment, store its reference in a separate table
                    if ($attachmentPath) {
                        $attachStmt = $db->prepare("
                            INSERT INTO ticket_attachments (ticket_id, file_path, created_at) 
                            VALUES (?, ?, NOW())
                        ");

                        if ($attachStmt) {
                            $attachStmt->bind_param("is", $ticketId, $attachmentPath);
                            $attachStmt->execute();
                        }
                    }

                    $success = true;
                    $successMessage = "Your ticket has been created successfully! Your reference ID is: $reference_id";
                } else {
                    $errors[] = "Failed to create ticket. Please try again.";
                }
            }
        }
    }
}

$pageTitle = "Create Ticket";
include 'includes/header.php';
?>

<div class="form-container">
    <div class="form-card">
        <h1>Create a Support Ticket</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <p>Your ticket has been created successfully!</p>
            </div>
        <?php endif; ?>

        <form method="post" action="create_ticket.php" class="ticket-form" enctype="multipart/form-data">
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat => $subcats): ?>
                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="subcategory">Subcategory</label>
                <select id="subcategory" name="subcategory" class="form-control" required>
                    <option value="">Select a subcategory</option>
                    <!-- Subcategories will be populated dynamically using JavaScript -->
                </select>
            </div>

            <div class="form-group">
                <label for="subject">Subject</label>
                <input type="text" id="subject" name="subject" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="5" required></textarea>
            </div>

            <div class="form-group">
                <label for="guest_email">Your Email</label>
                <input type="email" id="guest_email" name="guest_email" class="form-control"
                    value="<?php echo htmlspecialchars($guest_email); ?>" <?php echo !empty($guest_email) ? 'readonly' : 'required'; ?>>
                <?php if (!empty($guest_email)): ?>
                    <small class="form-text">Email from your guest session</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="attachment">Attachment (Optional)</label>
                <input type="file" id="attachment" name="attachment" class="form-control">
                <small class="form-text">Allowed file types: JPEG, PNG, PDF, TXT. Max size: 5MB.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Ticket</button>
            </div>
        </form>
    </div>
</div>

<script>
    const categories = <?php echo json_encode($categories); ?>;
    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');

    categorySelect.addEventListener('change', function () {
        const selectedCategory = this.value;
        subcategorySelect.innerHTML = '<option value="">Select a subcategory</option>';

        if (selectedCategory && categories[selectedCategory]) {
            Object.entries(categories[selectedCategory]).forEach(([id, name]) => {
                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                subcategorySelect.appendChild(option);
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>