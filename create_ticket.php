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

// Categories and subcategories
$categories = [
    'Technical' => ['Website Issue', 'Login Problem', 'Performance'],
    'Billing' => ['Payment Issue', 'Refund Request', 'Invoice Problem'],
    'General' => ['Feedback', 'Account Inquiry', 'Other']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = sanitize($_POST['category'] ?? '');
    $subcategory = sanitize($_POST['subcategory'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
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

    // If no errors, save the ticket and attachment
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

        // Insert ticket into the database
        if (empty($errors)) {
            $stmt = $db->prepare("
                INSERT INTO tickets (guest_id, category, subcategory, subject, description, attachment_path, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            if ($stmt->execute([$_SESSION['guest_id'], $category, $subcategory, $subject, $description, $attachmentPath])) {
                $success = true;
            } else {
                $errors[] = "Failed to create ticket. Please try again.";
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
    // JavaScript to dynamically populate subcategories based on selected category
    const categories = <?php echo json_encode($categories); ?>;
    const categorySelect = document.getElementById('category');
    const subcategorySelect = document.getElementById('subcategory');

    categorySelect.addEventListener('change', function () {
        const selectedCategory = this.value;
        subcategorySelect.innerHTML = '<option value="">Select a subcategory</option>';

        if (selectedCategory && categories[selectedCategory]) {
            categories[selectedCategory].forEach(subcat => {
                const option = document.createElement('option');
                option.value = subcat;
                option.textContent = subcat;
                subcategorySelect.appendChild(option);
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>