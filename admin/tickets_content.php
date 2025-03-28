<?php
ob_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
$tickets = getAllTickets();
$priorities = getAllPriorities();
$categories = getAllCategories();
$assignable_users = getAssignableUsers();

$isAdmin = isset($_SESSION['staff_role']) && $_SESSION['staff_role'] === 'admin';

$myTicketsCount = count(array_filter($tickets, function ($ticket) {
    return $ticket['assigned_to'] == $_SESSION['user_id'];
}));

$unassignedCount = count(array_filter($tickets, function ($ticket) {
    return empty($ticket['assigned_to']);
}));

$highPriorityCount = count(array_filter($tickets, function ($ticket) {
    return $ticket['priority_name'] === 'High';
}));

$allTicketsCount = count($tickets);

$pastDueCount = count(array_filter($tickets, function ($ticket) {
    $createdAt = new DateTime($ticket['created_at']);
    $dueDate = $createdAt->modify('+1 week')->format('Y-m-d H:i:s');

    return $dueDate < date('Y-m-d H:i:s') && $ticket['status'] != 'resolved';
}));

?>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('ticketSystem', {
            openTicketDetails(ticketId) {
                // This will be our global method to open the modal
                window.dispatchEvent(new CustomEvent('open-ticket-modal', {
                    detail: { ticketId }
                }));
            }
        });
    });
    window.currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    window.staffRole = <?php echo json_encode($_SESSION['staff_role'] ?? ''); ?>;
    window.isAdmin = <?php echo json_encode($isAdmin); ?>;
    window.ticketsData = <?php echo json_encode($tickets); ?>;
    window.prioritiesData = <?php echo json_encode($priorities); ?>;
    window.categoriesData = <?php echo json_encode($categories); ?>;
    window.assignableUsersData = <?php echo json_encode($assignable_users); ?>;

    window.ticketCounts = {
        myTickets: <?php echo $myTicketsCount; ?>,
        pastDue: <?php echo $pastDueCount; ?>,
        unassigned: <?php echo $unassignedCount; ?>,
        highPriority: <?php echo $highPriorityCount; ?>,
        allTickets: <?php echo $allTicketsCount; ?>
    };
    window.sharedViewCounts = window.ticketCounts;

    function basename(path) {
        return path.split('/').pop().split('\\').pop();
    }

    function ticketDetailsModal() {
        return {
            isOpen: false,
            currentTicket: null,
            ticketHistory: [],
            attachments: [],
            priorities: [],
            assignableUsers: [],
            commentAttachments: [],
            editorInitialized: false,
            editor: null,
            activeTab: 'user',
            isGuestUser: false,
            pendingAttachments: [],

            openTicketDetailsModal(ticketId) {
                window.dispatchEvent(new CustomEvent('open-ticket-modal', {
                    detail: { ticketId: ticketId }
                }));
            },

            init() {
                window.addEventListener('open-ticket-modal', (event) => {
                    this.openModal(event.detail.ticketId);
                });

                console.log("Ticket modal initialized and listening for events");
            },

            get filteredTicketHistory() {
                if (this.activeTab === 'user') {
                    return this.ticketHistory.filter(activity =>
                        activity.type === 'comment' && activity.is_internal === '0'
                    );
                } else if (this.activeTab === 'internal') {
                    return this.ticketHistory.filter(activity =>
                        activity.type === 'comment' && activity.is_internal === '1'
                    );
                }
                return this.ticketHistory;
            },

            downloadAttachment(attachment) {
                // Create download URL with consistent parameters
                let downloadUrl = `ajax/ajax_handlers.php?action=download_attachment&ticket_id=${this.currentTicket.id}`;

                // Add identifier parameters based on what's available
                if (attachment.id) {
                    downloadUrl += `&attachment_id=${attachment.id}`;
                } else if (attachment.comment_id) {
                    const filename = attachment.filename || attachment.name || this.basename(attachment.file_path);
                    downloadUrl += `&comment_id=${attachment.comment_id}&filename=${encodeURIComponent(filename)}`;
                } else {
                    const filename = attachment.filename || attachment.name || this.basename(attachment.file_path);
                    downloadUrl += `&filename=${encodeURIComponent(filename)}`;
                }

                // Add a force_download parameter to ensure consistent download behavior
                downloadUrl += `&force_download=1`;

                // Use a hidden anchor element with download attribute for more reliable downloading
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.setAttribute('download', ''); // This tells browser to download instead of navigate
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();

                // Clean up after a delay
                setTimeout(() => {
                    document.body.removeChild(link);
                }, 1000);
            },

            isImageAttachment(attachment) {
                // Get the filename
                const filename = attachment.filename || attachment.name || basename(attachment.file_path);
                const ext = filename.split('.').pop().toLowerCase();

                // List of image extensions that can be previewed
                const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp'];

                return imageExtensions.includes(ext);
            },

            openImageViewer(attachment) {
                // Construct the image URL
                let imageUrl = `ajax/ajax_handlers.php?action=view_attachment&ticket_id=${this.currentTicket.id}`;

                if (attachment.id) {
                    imageUrl += `&attachment_id=${attachment.id}`;
                } else if (attachment.comment_id) {
                    const filename = attachment.filename || attachment.name || basename(attachment.file_path);
                    imageUrl += `&comment_id=${attachment.comment_id}&filename=${encodeURIComponent(filename)}`;
                } else {
                    const filename = attachment.filename || attachment.name || basename(attachment.file_path);
                    imageUrl += `&filename=${encodeURIComponent(filename)}`;
                }

                // Dispatch event to open the image viewer modal
                window.dispatchEvent(new CustomEvent('open-image-viewer', {
                    detail: {
                        imageUrl: imageUrl,
                        title: attachment.filename || attachment.name || basename(attachment.file_path)
                    }
                }));
            },

            async deleteAttachment(attachmentId, index) {
                if (!confirm('Are you sure you want to delete this attachment?')) {
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('attachment_id', attachmentId);
                    formData.append('ticket_id', this.currentTicket.id);

                    const response = await fetch('ajax/ajax_handlers.php?action=delete_attachment', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Remove from local array
                        this.attachments.splice(index, 1);
                        // Reload ticket history to show the deletion in the history
                        await this.loadTicketHistory(this.currentTicket.id);
                        alert('Attachment deleted successfully');
                    } else {
                        alert('Error deleting attachment: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to delete attachment:', error);
                    alert('Failed to delete attachment. Please try again.');
                }
            },

            async openModal(ticketId) {
                console.log("Opening modal for ticket ID:", ticketId);
                this.isOpen = true;
                await this.loadTicketDetails(ticketId);
                await this.loadTicketHistory(ticketId);
                await this.loadTicketAttachments(ticketId);
                await this.loadPriorities();
                await this.loadAssignableUsers();
                document.body.classList.add('overflow-hidden');

                // Initialize editor after DOM has updated, with proper cleanup first
                this.$nextTick(() => {
                    // Complete editor teardown before creating a new one
                    this.destroyEditor();
                    // Create a fresh editor container
                    this.createFreshEditorContainer();
                    // Initialize new editor
                    this.initializeEditor();
                });
            },

            destroyEditor() {
                // Remove any existing editor completely
                if (this.editor) {
                    try {
                        // Quill specific cleanup
                        this.editor.removeAllListeners();
                        // Delete the editor instance
                        this.editor = null;
                    } catch (error) {
                        console.error("Error destroying editor:", error);
                    }
                }

                // Remove ALL Quill-related elements in our container
                try {
                    const container = document.querySelector('#editor-container-wrapper');
                    if (container) {
                        container.innerHTML = '';
                    }
                } catch (err) {
                    console.error("Error clearing editor container:", err);
                }
            },

            createFreshEditorContainer() {
                // Find the wrapper
                const wrapper = document.querySelector('#editor-container-wrapper');
                if (!wrapper) return;

                // Create a brand new container with a unique ID
                const newContainerId = 'editor-container-' + Date.now();
                wrapper.innerHTML = `<div id="${newContainerId}" class="min-h-[200px]"></div>`;

                // Store the ID for later use
                this.editorContainerId = newContainerId;
            },

            initializeEditor() {
                // Make sure we have a container ID
                if (!this.editorContainerId) return;

                // Load Quill if needed
                if (!window.Quill) {
                    // Check if we're already loading Quill
                    if (document.querySelector('script[src*="quill.min.js"]')) {
                        // Wait a bit and try again
                        setTimeout(() => this.initializeEditor(), 200);
                        return;
                    }

                    // Load Quill CSS if not present
                    if (!document.querySelector('link[href*="quill.snow.min.css"]')) {
                        const quillCSS = document.createElement('link');
                        quillCSS.rel = 'stylesheet';
                        quillCSS.href = 'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css';
                        document.head.appendChild(quillCSS);
                    }

                    // Load Quill JS
                    const quillScript = document.createElement('script');
                    quillScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js';
                    quillScript.onload = () => this.setupQuillEditor();
                    document.head.appendChild(quillScript);
                } else {
                    this.setupQuillEditor();
                }
            },

            setupQuillEditor() {
                try {
                    // Make sure the container still exists
                    const container = document.getElementById(this.editorContainerId);
                    if (!container) {
                        console.error("Editor container not found");
                        return;
                    }

                    // Configure Quill toolbar options - simpler to match your screenshot
                    const toolbarOptions = [
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote', 'code-block'],
                        [{ 'header': [1, 2, false] }],
                        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                        [{ 'script': 'sub' }, { 'script': 'super' }],
                        [{ 'indent': '-1' }, { 'indent': '+1' }],
                        [{ 'size': ['small', false, 'large', 'huge'] }],
                        [{ 'color': [] }, { 'background': [] }],
                        [{ 'font': [] }],
                        [{ 'align': [] }],
                        ['link', 'image']
                    ];

                    // Initialize Quill with direct selector to avoid multiple toolbar issue
                    this.editor = new Quill(`#${this.editorContainerId}`, {
                        modules: {
                            toolbar: toolbarOptions
                        },
                        placeholder: 'Type your response here...',
                        theme: 'snow'
                    });

                    // Add image handler
                    const toolbar = this.editor.getModule('toolbar');
                    toolbar.addHandler('image', () => {
                        this.handleImageInsertion();
                    });

                    console.log("Quill editor initialized successfully");
                } catch (error) {
                    console.error("Error initializing Quill:", error);
                }
            },

            handleImageInsertion() {
                const fileInput = document.createElement('input');
                fileInput.setAttribute('type', 'file');
                fileInput.setAttribute('accept', 'image/*');
                fileInput.click();

                fileInput.onchange = async () => {
                    const file = fileInput.files[0];
                    if (file) {
                        // Show loading indicator in editor
                        const range = this.editor.getSelection(true);
                        this.editor.insertText(range.index, 'Uploading image...', { 'italic': true, 'color': '#999' });

                        try {
                            // Upload the image
                            await this.uploadAttachment(file);

                            // Get the URL (assuming your upload function returns the URL)
                            // For now we'll use a placeholder
                            const imageUrl = URL.createObjectURL(file);

                            // Remove loading text and insert image
                            this.editor.deleteText(range.index, 'Uploading image...'.length);
                            this.editor.insertEmbed(range.index, 'image', imageUrl);
                        } catch (error) {
                            console.error('Failed to upload image:', error);
                            // Remove loading text and show error
                            this.editor.deleteText(range.index, 'Uploading image...'.length);
                            this.editor.insertText(range.index, 'Error uploading image', { 'italic': true, 'color': '#ff0000' });
                        }
                    }
                };
            },

            closeModal() {
                this.destroyEditor();

                this.isOpen = false;
                this.currentTicket = null;
                this.ticketHistory = [];
                this.attachments = [];
                document.body.classList.remove('overflow-hidden');
            },

            // Cancel a pending attachment
            cancelPendingAttachment(index) {
                this.pendingAttachments.splice(index, 1);
            },

            // Confirm and upload a pending attachment
            async confirmAttachment(index) {
                const pending = this.pendingAttachments[index];
                if (!pending || !pending.file) return;

                try {
                    const formData = new FormData();
                    formData.append('attachment', pending.file);
                    formData.append('ticket_id', this.currentTicket.id);

                    // Show a loading indicator
                    this.pendingAttachments[index].uploading = true;

                    const response = await fetch('ajax/ajax_handlers.php?action=upload_attachment', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Remove from pending
                        this.pendingAttachments.splice(index, 1);
                        // Reload ticket attachments and history
                        await this.loadTicketAttachments(this.currentTicket.id);
                        await this.loadTicketHistory(this.currentTicket.id);
                        this.showNotification('Attachment uploaded successfully', 'success');
                    } else {
                        this.showNotification('Error uploading attachment: ' + data.error, 'error');
                        // Remove uploading flag
                        this.pendingAttachments[index].uploading = false;
                    }
                } catch (error) {
                    console.error('Failed to upload attachment:', error);
                    this.showNotification('Failed to upload attachment. Please try again.', 'error');
                    // Remove uploading flag
                    this.pendingAttachments[index].uploading = false;
                }
            },

            async loadTicketAttachments(ticketId) {
                try {
                    const response = await fetch(`ajax/ajax_handlers.php?action=get_ticket_attachments&ticket_id=${ticketId}`);
                    const data = await response.json();

                    if (data.success) {
                        this.attachments = data.attachments;
                    } else {
                        alert('Error loading ticket attachments: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to load ticket attachments:', error);
                    alert('Failed to load ticket attachments. Please try again.');
                }
            },

            async loadTicketDetails(ticketId) {
                console.log("Loading ticket details for ticket ID:", ticketId); // Debugging statement
                try {
                    const response = await fetch(`ajax/ajax_handlers.php?action=get_ticket_details&ticket_id=${ticketId}`);
                    const text = await response.text();
                    console.log('Raw response:', text);

                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            this.currentTicket = data.ticket;
                            console.log(data.ticket)
                            if (this.currentTicket.assigned_to || !this.currentTicket.assigned_to_name || assigned_to_name == "Unassigned") {
                                if (this.staffMembers && this.staffMembers.length > 0) {
                                    const assignedStaff = this.staffMembers.find(staff => staff.id == this.currentTicket.assigned_to);
                                    this.currentTicket.assigned_to_name = assignedStaff ? assignedStaff.name : 'Unassigned';
                                } else {
                                    this.currentTicket.assigned_to_name = 'Staff ID: ' + this.currentTicket.assigned_to;

                                    this.loadStaffMembers().then(() => {
                                        const assignedStaff = this.staffMembers.find(staff => staff.id == this.currentTicket.assigned_to);
                                        this.currentTicket.assigned_to_name = assignedStaff ? assignedStaff.name : 'Unassigned';
                                    });
                                }
                            } else if (!this.currentTicket.assigned_to) {
                                this.currentTicket.assigned_to_name = 'Unassigned';
                            }
                        } else {
                            alert('Error loading ticket details: ' + data.error);
                        }
                    } catch (jsonError) {
                        console.error('JSON parse error:', jsonError);
                        alert('Invalid response format from server');
                    }
                } catch (error) {
                    console.error('Failed to load ticket details:', error);
                    alert('Failed to load ticket details. Please try again.');
                }
            },

            async loadStaffMembers() {
                if (!this.staffMembers || this.staffMembers.length === 0) {
                    try {
                        const response = await fetch('ajax/ajax_handlers.php?action=get_staff_members');
                        const data = await response.json();
                        if (data.success) {
                            this.staffMembers = data.staff_members;
                        }
                    } catch (error) {
                        console.error('Failed to load staff members:', error);
                    }
                }
            },

            async loadTicketHistory(ticketId) {
                try {
                    const response = await fetch(`ajax/ajax_handlers.php?action=get_ticket_history&ticket_id=${ticketId}`);
                    const data = await response.json();

                    if (data.success) {
                        console.log("Raw history data:", data.history);
                        // Log each item's is_internal value
                        data.history.forEach(item => {
                            console.log(`Comment "${item.content.substring(0, 20)}..." is_internal:`,
                                item.is_internal,
                                "type:", typeof item.is_internal);
                        });
                        this.ticketHistory = data.history;
                    } else {
                        alert('Error loading ticket history: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to load ticket history:', error);
                    alert('Failed to load ticket history. Please try again.');
                }
            },

            async loadPriorities() {
                try {
                    const response = await fetch('ajax/ajax_handlers.php?action=get_priorities');
                    const data = await response.json();

                    if (data.success) {
                        this.priorities = data.priorities;
                    }
                } catch (error) {
                    console.error('Failed to load priorities:', error);
                }
            },

            async loadAssignableUsers() {
                try {
                    const response = await fetch('ajax/ajax_handlers.php?action=get_assignable_users');
                    const data = await response.json();

                    if (data.success) {
                        this.assignableUsers = data.users;
                        console.log(this.assignableUsers);
                    }
                } catch (error) {
                    console.error('Failed to load assignable users:', error);
                }
            },

            async updateStatus(newStatus) {
                if (!this.currentTicket) return;

                try {
                    const formData = new FormData();
                    formData.append('ticket_id', this.currentTicket.id);
                    formData.append('status', newStatus);

                    const response = await fetch('ajax/ajax_handlers.php?action=update_ticket_status', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.currentTicket.status = newStatus;
                        await this.loadTicketHistory(this.currentTicket.id);
                        alert('Status updated successfully');

                        // Emit an event to notify other components
                        window.dispatchEvent(new CustomEvent('ticket-status-updated', {
                            detail: { ticketId: this.currentTicket.id, newStatus: newStatus }
                        }));
                    } else {
                        alert('Error updating status: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to update status:', error);
                    alert('Failed to update status. Please try again.');
                }
            },

            async updatePriority(newPriorityId) {
                if (!this.currentTicket) return;

                try {
                    const formData = new FormData();
                    formData.append('ticket_id', this.currentTicket.id);
                    formData.append('priority_id', newPriorityId);

                    const response = await fetch('ajax/ajax_handlers.php?action=update_ticket_priority', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update the local priority name from the priorities array
                        const priority = this.priorities.find(p => p.id === newPriorityId);
                        this.currentTicket.priority_id = newPriorityId;
                        this.currentTicket.priority_name = priority ? priority.name : 'Unknown';
                        await this.loadTicketHistory(this.currentTicket.id);
                        alert('Priority updated successfully');
                    } else {
                        alert('Error updating priority: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to update priority:', error);
                    alert('Failed to update priority. Please try again.');
                }
            },

            async assignTicket(assigneeId) {
                if (!this.currentTicket) return;

                try {
                    const formData = new FormData();
                    formData.append('ticket_id', this.currentTicket.id);
                    formData.append('assignee_id', assigneeId);

                    const response = await fetch('ajax/ajax_handlers.php?action=assign_ticket', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update the local assignee name from the assignable users array
                        const assignee = this.assignableUsers.find(u => u.id === assigneeId);
                        this.currentTicket.assigned_to = assigneeId;
                        this.currentTicket.assigned_to_name = assignee ? assignee.name : 'Testing';
                        await this.loadTicketHistory(this.currentTicket.id);
                        alert('Ticket assigned successfully');
                    } else {
                        alert('Error assigning ticket: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to assign ticket:', error);
                    alert('Failed to assign ticket. Please try again.');
                }
            },

            async addCommentAttachment() {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.multiple = false;

                fileInput.onchange = async (e) => {
                    if (e.target.files.length === 0) return;

                    const file = e.target.files[0];

                    this.commentAttachments.push({
                        name: file.name,
                        size: file.size,
                        file: file,
                        id: 'comment-' + Date.now()
                    });
                };

                fileInput.click();
            },

            async addComment(isPrivate = false) {
                if (!this.currentTicket || !this.editor) return;

                const commentContent = this.editor.root.innerHTML;

                if (!this.editor.getText().trim()) {
                    this.showNotification('Please enter a comment', 'error');
                    return;
                }

                try {
                    this.isSubmitting = true;

                    const formData = new FormData();
                    formData.append('ticket_id', this.currentTicket.id);
                    formData.append('content', commentContent);
                    formData.append('is_private', isPrivate ? 1 : 0);

                    // Add any uploaded attachments from commentAttachments array only
                    if (this.commentAttachments && this.commentAttachments.length > 0) {
                        this.commentAttachments.forEach((attachment, index) => {
                            if (attachment.file) {
                                formData.append(`attachments[]`, attachment.file);
                            }
                        });
                    }

                    // Log the form data for debugging
                    console.log('Submitting comment with ticket ID:', this.currentTicket.id);
                    console.log('Is private:', isPrivate);
                    console.log('Content length:', commentContent.length);
                    console.log('Attachments count:', this.commentAttachments.length);

                    const response = await fetch('ajax/ajax_handlers.php?action=add_comment', {
                        method: 'POST',
                        body: formData
                    });

                    const responseText = await response.text();
                    console.log('Raw response:', responseText);

                    let data;
                    try {
                        data = JSON.parse(responseText);
                    } catch (jsonError) {
                        console.error('JSON parse error:', jsonError);
                        this.showNotification('Invalid response format from server', 'error');
                        return;
                    }

                    if (data.success) {
                        this.editor.setText('');
                        this.commentAttachments = [];
                        await this.loadTicketHistory(this.currentTicket.id);
                        this.showNotification('Comment added successfully', 'success');
                    } else {
                        this.showNotification('Error adding comment: ' + (data.error || 'Unknown error'), 'error');
                    }
                } catch (error) {
                    console.error('Failed to add comment:', error);
                    this.showNotification('Failed to add comment. Please try again.', 'error');
                } finally {
                    this.isSubmitting = false;
                }
            },

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.classList.add(
                    'fixed', 'top-4', 'right-4', 'z-50', 'px-4', 'py-2', 'rounded', 'text-white',
                    type === 'success' ? 'bg-green-500' : 'bg-red-500'
                );
                notification.textContent = message;

                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.classList.add('animate-fade-out');
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 500);
                }, 3000);
            },

            async archiveTicket() {
                if (!this.currentTicket) return;

                if (!confirm('Are you sure you want to archive this ticket?')) {
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('ticket_id', this.currentTicket.id);

                    const response = await fetch('ajax/ajax_handlers.php?action=archive_ticket', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert('Ticket archived successfully');
                        this.closeModal();
                        // Reload the ticket list if there's a callback for it
                        if (typeof loadTickets === 'function') {
                            loadTickets();
                        }
                    } else {
                        alert('Error archiving ticket: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to archive ticket:', error);
                    alert('Failed to archive ticket. Please try again.');
                }
            },

            formatDate(dateString) {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleString();
            },

            async addAttachment() {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.multiple = false;

                fileInput.onchange = async (e) => {
                    if (e.target.files.length === 0) return;

                    const file = e.target.files[0];

                    this.pendingAttachments.push({
                        name: file.name,
                        size: file.size,
                        file: file,
                        id: 'pending-' + Date.now()
                    });
                };

                fileInput.click();
            },

            async uploadAttachment(file) {
                try {
                    const formData = new FormData();
                    formData.append('attachment', file);
                    formData.append('ticket_id', this.currentTicket.id);

                    console.log("Uploading attachment for ticket ID:", this.currentTicket.id);
                    console.log("Attachment file:", file);
                    const response = await fetch('ajax/ajax_handlers.php?action=upload_attachment', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Reload ticket history to show the new attachment
                        await this.loadTicketHistory(this.currentTicket.id);
                    } else {
                        alert('Error uploading attachment: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to upload attachment:', error);
                    alert('Failed to upload attachment. Please try again.');
                }
            },

            removeCommentAttachment(index) {
                this.commentAttachments.splice(index, 1);
            },


        };
    }

    function ticketTable() {
        return {
            sortColumn: 'created_at',
            sortDirection: 'desc',
            tickets: window.ticketsData || [],
            priorities: window.prioritiesData || [],
            categories: window.categoriesData || [],
            assignableUsers: window.assignableUsersData || [],
            expandedTicketId: null,
            isViewsListOpen: true,
            tempAssignments: {},
            currentPages: {
                'open': 1,
                'seen': 1,
                'resolved': 1
            },
            itemsPerPage: 5,
            isAdmin: window.isAdmin || false,
            activeStatus: 'all', // Default to showing all statuses
            activeViewSection: window.activeViewSection || 'all-tickets', // Default to 'all-tickets'
            viewCounts: window.ticketCounts || {
                myTickets: 0,
                pastDue: 0,
                unassigned: 0,
                highPriority: 0,
                allTickets: 0
            },

            init() {
                if (this.tickets && this.tickets.length) {
                    this.tickets.forEach(ticket => {
                        this.initTempAssignment(ticket.id);
                    });
                }
                this.updateTicketCounts();
                console.log('Is Admin:', this.isAdmin);
                console.log('Tickets:', this.tickets);
                console.log('Filtered Tickets Example:', this.getFilteredTickets());
                console.log('Tickets by Status:', {
                    open: this.tickets.filter(t => t.status === 'open'),
                    seen: this.tickets.filter(t => t.status === 'seen'),
                    resolved: this.tickets.filter(t => t.status === 'resolved')
                });

                // Ensure this event listener works properly
                window.addEventListener('view-section-changed', (event) => {
                    console.log('Received view-section-changed event:', event.detail.section);
                    this.activeViewSection = event.detail.section;
                    console.log('Updated activeViewSection:', this.activeViewSection);
                    this.getFilteredTickets(); // Refresh the filtered tickets
                    console.log('Filtered Tickets:', this.getFilteredTickets());
                });

                this.$watch('activeViewSection', (newValue) => {
                    this.activeViewSection = newValue;
                    this.currentPages = {
                        'open': 1,
                        'seen': 1,
                        'resolved': 1
                    };
                });

                this.$watch('activeStatus', (newValue) => {
                    this.activeStatus = newValue;
                    this.currentPages[newValue] = 1;
                });

                this.activeViewSection = window.activeViewSection || 'my-tickets';
                console.log('Active View Section:', this.activeViewSection);
                console.log('Active Status:', this.activeStatus);
            },

            initTempAssignment(ticketId) {
                const ticket = this.tickets.find(t => t.id === ticketId);
                if (ticket) {
                    this.tempAssignments[ticketId] = {
                        assigned_to: ticket.assigned_to || '',
                        category_id: ticket.category_id || '',
                        priority_id: ticket.priority_id || ''
                    };
                }
            },

            toggleTicketExpand(ticketId) {
                this.expandedTicketId = this.expandedTicketId === ticketId ? null : ticketId;
                if (this.expandedTicketId === ticketId) {
                    // Initialize temp values when expanding
                    this.tempAssignments[ticketId] = {
                        assigned_to: this.tickets.find(t => t.id === ticketId)?.assigned_to || '',
                        category_id: this.tickets.find(t => t.id === ticketId)?.category_id || '',
                        priority_id: this.tickets.find(t => t.id === ticketId)?.priority_id || ''
                    };
                }
            },

            updateTicketCounts() {
                const userId = window.currentUserId || 0;

                const counts = {
                    myTickets: this.tickets.filter(ticket => ticket.assigned_to == userId).length,
                    pastDue: this.tickets.filter(ticket => {
                        const dueDate = this.calculateDueDate(ticket.created_at);
                        return dueDate < new Date() && ticket.status !== 'closed';
                    }).length,
                    unassigned: this.tickets.filter(ticket => !ticket.assigned_to).length,
                    highPriority: this.tickets.filter(ticket => ticket.priority_name === 'High').length,
                    allTickets: this.tickets.length
                };

                // Update local counts
                this.viewCounts = counts;

                // Update shared state for other components
                window.sharedViewCounts = counts;

                // Dispatch an event so other components can update
                window.dispatchEvent(new CustomEvent('ticket-counts-updated', {
                    detail: { counts: counts }
                }));
            },

            nextPage(status) {
                const filteredTickets = this.getFilteredTickets();
                if (this.currentPages[status] * this.itemsPerPage < filteredTickets.length) {
                    this.currentPages[status]++;
                }
            },

            prevPage(status) {
                if (this.currentPages[status] > 1) {
                    this.currentPages[status]--;
                }
            },

            calculateDueDate(createdAt) {
                const createdDate = new Date(createdAt);
                const dueDate = new Date(createdDate.setDate(createdDate.getDate() + 7));
                return dueDate;
            },

            getFilteredTickets() {
                let filteredTickets = this.tickets;

                if (this.activeViewSection === 'my-tickets') {
                    const userId = window.currentUserId || 0;
                    filteredTickets = filteredTickets.filter(ticket => ticket.assigned_to == userId);
                } else if (this.activeViewSection === 'past-due') {
                    filteredTickets = filteredTickets.filter(ticket => {
                        const dueDate = this.calculateDueDate(ticket.created_at);
                        return dueDate < new Date() && ticket.status !== 'closed';
                    });
                } else if (this.activeViewSection === 'unassigned') {
                    filteredTickets = filteredTickets.filter(ticket => !ticket.assigned_to);
                } else if (this.activeViewSection === 'high-priority') {
                    filteredTickets = filteredTickets.filter(ticket => ticket.priority_name === 'High');
                } else if (this.activeViewSection === 'all-tickets') {
                    // No additional filtering needed
                }

                if (this.activeStatus === 'open') {
                    filteredTickets = filteredTickets.filter(ticket => ticket.status === 'open');
                } else if (this.activeStatus === 'seen') {
                    filteredTickets = filteredTickets.filter(ticket =>
                        ticket.status === 'seen' || ticket.status === 'pending');
                } else if (this.activeStatus === 'resolved') {
                    filteredTickets = filteredTickets.filter(ticket => ticket.status === 'resolved');
                }

                console.log('Filtered tickets for view:', this.activeViewSection, 'status:', this.activeStatus, filteredTickets);
                return filteredTickets;
            },

            paginatedTickets(status) {
                this.activeStatus = status;
                let filteredTickets = this.getFilteredTickets();

                const start = (this.currentPages[status] - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                return filteredTickets.slice(start, end);
            },

            updateTicket(ticketId) {
                const ticketData = this.tempAssignments[ticketId];

                fetch(`update_ticket.php?id=${ticketId}`, {
                    method: 'POST',
                    body: JSON.stringify(ticketData),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.ticket) {
                            const index = this.tickets.findIndex(ticket => ticket.id === ticketId);
                            if (index !== -1) {
                                let assignedToName = 'Unassigned';
                                if (data.ticket.assigned_to) {
                                    const assignedUser = this.assignableUsers.find(user => user.id == data.ticket.assigned_to);
                                    assignedToName = assignedUser ? assignedUser.name : 'Unassigned';
                                }

                                // Get the priority name if needed
                                let priorityName = '';
                                if (data.ticket.priority_id) {
                                    const priority = this.priorities.find(p => p.id == data.ticket.priority_id);
                                    priorityName = priority ? priority.name : '';
                                }

                                // Get the category name if needed
                                let categoryName = '';
                                if (data.ticket.category_id) {
                                    const category = this.categories.find(c => c.id == data.ticket.category_id);
                                    categoryName = category ? category.name : '';
                                }

                                // Update the ticket with all necessary display data
                                this.tickets[index] = {
                                    ...this.tickets[index],
                                    ...data.ticket,
                                    assigned_to_name: assignedToName,
                                    priority_name: priorityName,
                                    category_name: categoryName
                                };

                                // Reset expanded state to collapse the ticket
                                this.expandedTicketId = null;

                                // Update ticket counts
                                this.updateTicketCounts();
                            }
                            this.showNotification('Ticket updated successfully', 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Error updating ticket:', error);
                        this.showNotification(error.message, 'error');
                    });
            },

            showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.classList.add(
                    'fixed', 'top-4', 'right-4', 'z-50', 'px-4', 'py-2', 'rounded', 'text-white',
                    type === 'success' ? 'bg-green-500' : 'bg-red-500'
                );
                notification.textContent = message;

                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.classList.add('animate-fade-out');
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 500);
                }, 3000);
            },

            deleteTicket(ticketId) {
                fetch(`delete_ticket.php?id=${ticketId}`, {
                    method: 'DELETE'
                })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(errData => {
                                throw new Error(errData.message || 'Delete failed');
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            this.tickets = this.tickets.filter(ticket => ticket.id !== ticketId);
                            this.updateTicketCounts();
                            this.showNotification('Ticket deleted successfully', 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Error deleting ticket:', error);
                        this.showNotification(error.message, 'error');
                    });
            },

            formatDate(datetime) {
                const date = new Date(datetime);
                const now = new Date();
                const diff = now - date;

                const seconds = Math.floor(diff / 1000);
                const minutes = Math.floor(seconds / 60);
                const hours = Math.floor(minutes / 60);
                const days = Math.floor(hours / 24);

                if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
                if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
                if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
                return 'just now';
            },

            openTicketDetailsModal(ticketId) {
                Alpine.store('ticketSystem').openTicketDetails(ticketId);
            },
        };
    }

    function ticketsTableView() {
        return {
            tickets: [],
            filteredTickets: [],
            paginatedTickets: [],
            staffMembers: [],
            isLoading: true,
            error: null,

            // Sorting
            sortField: 'created_at',
            sortDirection: 'desc',

            // Pagination
            currentPage: 1,
            itemsPerPage: 10,
            totalPages: 1,
            pageNumbers: [],

            // Filters
            filters: {
                status: '',
                priority: '',
                category: '',
                assignedTo: '',
                searchQuery: ''
            },

            init() {
                this.fetchTickets();

                this.fetchStaffMembers().then(() => {
                    this.fetchTickets();
                });

                this.$watch('filters', () => {
                    this.applyFilters();
                }, { deep: true }); // Add deep: true to watch nested properties

                this.$watch('currentPage', () => {
                    this.updatePaginatedTickets();
                });

                window.addEventListener('ticket-status-updated', (event) => {
                    const { ticketId, newStatus } = event.detail;
                    const ticketIndex = this.tickets.findIndex(ticket => ticket.id === ticketId);
                    if (ticketIndex !== -1) {
                        this.tickets[ticketIndex].status = newStatus;
                        this.updateTicketCounts(); // Update the counts if necessary
                        this.getFilteredTickets(); // Refresh the filtered tickets
                    }
                });

            },

            async fetchStaffMembers() {
                try {
                    const response = await fetch('ajax/ajax_handlers.php?action=get_staff_members');
                    const data = await response.json();

                    if (data.success) {
                        this.staffMembers = data.staff_members;
                        console.log("Staff members loaded:", this.staffMembers);
                    } else {
                        console.error('Error loading staff members:', data.error);
                    }
                } catch (error) {
                    console.error('Failed to fetch staff members:', error);
                }
            },

            async fetchTickets() {
                this.isLoading = true;
                try {
                    const response = await fetch('ajax/ajax_handlers.php?action=get_all_tickets');
                    const data = await response.json();

                    if (data.success) {
                        console.log("Received tickets:", data.tickets);

                        // Map assigned_to IDs to actual names
                        this.tickets = data.tickets.map(ticket => {
                            if (ticket.assigned_to && (!ticket.assigned_to_name || ticket.assigned_to_name === '')) {
                                const staffMember = this.staffMembers.find(staff => staff.id == ticket.assigned_to);
                                ticket.assigned_to_name = staffMember ? staffMember.name : 'Unassigned';
                            }
                            return ticket;
                        });

                        this.applyFilters();
                    } else {
                        this.error = data.error || 'Failed to load tickets';
                        console.error('Error loading tickets:', this.error);
                    }
                } catch (error) {
                    this.error = 'Network error while loading tickets';
                    console.error('Failed to fetch tickets:', error);
                } finally {
                    this.isLoading = false;
                }
            },

            getStaffNameById(staffId) {
                if (!staffId) return 'Testing';
                const staffMember = this.staffMembers.find(staff => staff.id == staffId);
                console.log(`Looking for staff ID ${staffId}, found:`, staffMember);
                return staffMember ? staffMember.name : 'Testing';
            },

            getUniqueValues(field) {
                const values = this.tickets
                    .map(ticket => ticket[field])
                    .filter(value => value !== null && value !== undefined && value !== '');

                // Remove duplicates using Set
                return [...new Set(values)].sort();
            },

            // Reset all filters
            resetFilters() {
                this.filters = {
                    status: '',
                    priority: '',
                    category: '',
                    assignedTo: '',
                    searchQuery: ''
                };
                // applyFilters will be triggered by the watcher
            },

            applyFilters() {
                console.log("Starting filter with:", this.filters);
                console.log("Before filtering, ticket count:", this.tickets.length);
                let result = [...this.tickets];

                if (this.filters.status) {
                    console.log(`Filtering for status "${this.filters.status}"`);
                    const beforeCount = result.length;
                    result = result.filter(ticket => ticket.status === this.filters.status);
                    console.log(`Status filter: ${beforeCount} -> ${result.length} tickets`);
                }

                if (this.filters.priority) {
                    console.log(`Filtering for priority "${this.filters.priority}"`);
                    const beforeCount = result.length;
                    result = result.filter(ticket => ticket.priority_name === this.filters.priority);
                    console.log(`Priority filter: ${beforeCount} -> ${result.length} tickets`);
                }

                // Apply category filter
                if (this.filters.category) {
                    result = result.filter(ticket => ticket.category_name === this.filters.category);
                }

                // Apply assigned to filter
                if (this.filters.assignedTo) {
                    result = result.filter(ticket => ticket.assigned_to_name === this.filters.assignedTo);
                }

                // Apply search query filter (across title and description)
                if (this.filters.searchQuery) {
                    const query = this.filters.searchQuery.toLowerCase();
                    result = result.filter(ticket =>
                        (ticket.title && ticket.title.toLowerCase().includes(query)) ||
                        (ticket.description && ticket.description.toLowerCase().includes(query))
                    );
                }

                result = this.sortTickets(result);

                console.log("After filtering, ticket count:", result.length);

                this.filteredTickets = result;

                this.currentPage = 1;
                this.updatePagination();
                this.updatePaginatedTickets();

                console.log("paginatedTickets count:", this.paginatedTickets.length);
            },

            sortTickets(tickets) {
                return [...tickets].sort((a, b) => {
                    let valueA = a[this.sortField];
                    let valueB = b[this.sortField];

                    // Handle string comparison
                    if (typeof valueA === 'string') {
                        valueA = valueA.toLowerCase();
                        valueB = valueB.toLowerCase();
                    }

                    // Handle date comparison
                    if (this.sortField === 'created_at') {
                        valueA = new Date(valueA);
                        valueB = new Date(valueB);
                    }

                    // Compare values
                    if (valueA < valueB) {
                        return this.sortDirection === 'asc' ? -1 : 1;
                    }
                    if (valueA > valueB) {
                        return this.sortDirection === 'asc' ? 1 : -1;
                    }
                    return 0;
                });
            },

            sortBy(field) {
                // If clicking the same field, toggle direction
                if (this.sortField === field) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    // New field, default to ascending
                    this.sortField = field;
                    this.sortDirection = 'asc';
                }

                this.applyFilters();
            },

            updatePagination() {
                this.totalPages = Math.ceil(this.filteredTickets.length / this.itemsPerPage);

                // Generate page numbers to display (up to 5 pages)
                let startPage = Math.max(1, this.currentPage - 2);
                let endPage = Math.min(this.totalPages, startPage + 4);

                if (endPage - startPage < 4 && this.totalPages > 5) {
                    startPage = Math.max(1, endPage - 4);
                }

                this.pageNumbers = Array.from(
                    { length: endPage - startPage + 1 },
                    (_, i) => startPage + i
                );
            },

            updatePaginatedTickets() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                const end = start + this.itemsPerPage;
                this.paginatedTickets = this.filteredTickets.slice(start, end);
            },

            prevPage() {
                if (this.currentPage > 1) {
                    this.currentPage--;
                }
            },

            nextPage() {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                }
            },

            goToPage(page) {
                if (page >= 1 && page <= this.totalPages) {
                    this.currentPage = page;
                }
            },

            formatDate(dateString) {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            },

            openTicketDetailsModal(ticketId) {
                window.dispatchEvent(new CustomEvent('open-ticket-modal', {
                    detail: { ticketId: ticketId }
                }));
            },

            editTicket(ticketId) {
                window.location.href = `edit_ticket.php?id=${ticketId}`;
            },

            deleteTicket(ticketId) {
                if (confirm('Are you sure you want to delete this ticket?')) {
                    this.performDeleteTicket(ticketId);
                }
            },

            async performDeleteTicket(ticketId) {
                try {
                    const formData = new FormData();
                    formData.append('ticket_id', ticketId);

                    const response = await fetch('ajax/ajax_handlers.php?action=delete_ticket', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Remove from local data
                        this.tickets = this.tickets.filter(ticket => ticket.id !== ticketId);
                        this.applyFilters();
                        alert('Ticket deleted successfully');
                    } else {
                        alert('Error deleting ticket: ' + data.error);
                    }
                } catch (error) {
                    console.error('Failed to delete ticket:', error);
                    alert('Failed to delete ticket. Please try again.');
                }
            }
        };
    }

</script>

<div x-show="activeView === 'tickets'" class="flex gap-8" x-data="{ 
    isViewsSidebarOpen: true, 
    activeViewSection: 'my-tickets',
    isAdmin: window.isAdmin || false,
    viewCounts: window.ticketCounts || {
        myTickets: 0,
        pastDue: 0,
        unassigned: 0,
        highPriority: 0,
        allTickets: 0
    },
    toggleViewsSidebar() {
        this.isViewsSidebarOpen = !this.isViewsSidebarOpen;
    },
    setActiveViewSection(section) {
        console.log('Setting active view section to:', section);
        this.activeViewSection = section;
        window.activeViewSection = section;

        // Broadcast the view change event
        const event = new CustomEvent('view-section-changed', { 
            detail: { section: section } 
        });
        window.dispatchEvent(event);
    },
    init() {
        // Listen for ticket count updates from the ticket table component
        window.addEventListener('ticket-counts-updated', (event) => {
            this.viewCounts = event.detail.counts;
        });
    }
}">
    <!-- Left Sidebar - Views with Full Background -->
    <div class="w-1/6 bg-[#f9fafb] h-full min-h-screen transition-all duration-300 ease-in-out" :class="{ 
                    'translate-x-0': isViewsSidebarOpen, 
                    '-translate-x-full absolute': !isViewsSidebarOpen 
                }" x-show="isViewsSidebarOpen" x-transition>
        <div class="tickets-header flex justify-center py-4">
            <div class="flex items-center">
                <h1 class="text-lg font-bold text-gray-800">Tickets</h1>
            </div>
        </div>
        <div class="views-section h-full">
            <!-- Views Header with Dropdown Toggle -->
            <div class="views-header cursor-pointer" @click="isViewsListOpen = !isViewsListOpen">
                <i class="fas fa-chevron-down mr-2 transition-transform duration-200"
                    :class="{ 'rotate-180': !isViewsListOpen }"></i>
                <h3 class="text-sm font-medium text-gray-700">Views</h3>
            </div>

            <div class="py-2 px-3 mb-2 rounded" x-show="isAdmin"
                x-bind:class="isAdmin ? 'bg-blue-100 text-blue-800' : 'hidden'">
                <i class="fas fa-shield-alt mr-1"></i> Admin View
            </div>

            <div class="space-y-2 mt-4" x-show="isViewsListOpen" x-transition>
                <!-- My Tickets -->
                <div class="ticket-sidebar-item border-b border-gray-200"
                    :class="{ 'active': activeViewSection === 'my-tickets' }"
                    @click="setActiveViewSection('my-tickets')">
                    <span>My Tickets</span>
                    <span class="ticket-count" x-text="viewCounts.myTickets"></span>
                </div>

                <!-- Past Due -->
                <div class="ticket-sidebar-item border-b border-gray-200"
                    :class="{ 'active': activeViewSection === 'past-due' }" @click="setActiveViewSection('past-due')">
                    <span>Past Due</span>
                    <span class="ticket-count" x-text="viewCounts.pastDue"></span>
                </div>

                <!-- Unassigned -->
                <div class="ticket-sidebar-item border-b border-gray-200"
                    :class="{ 'active': activeViewSection === 'unassigned' }"
                    @click="setActiveViewSection('unassigned')">
                    <span>Unassigned</span>
                    <span class="ticket-count" x-text="viewCounts.unassigned"></span>
                </div>

                <!-- High Priority -->
                <div class="ticket-sidebar-item border-b border-gray-200"
                    :class="{ 'active': activeViewSection === 'high-priority' }"
                    @click="setActiveViewSection('high-priority')">
                    <span>High Priority</span>
                    <span class="ticket-count" x-text="viewCounts.highPriority"></span>
                </div>

                <!-- All Tickets -->
                <div class="ticket-sidebar-item" :class="{ 'active': activeViewSection === 'all-tickets' }"
                    @click="setActiveViewSection('all-tickets')">
                    <span>All Tickets</span>
                    <span class="ticket-count" x-text="viewCounts.allTickets"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Content - Kanban Columns -->
    <div x-show="activeViewSection !== 'all-tickets'" class="flex-1 py-4 px-8">
        <!-- Aligned Tickets Nav -->
        <div class="flex items-center justify-between mb-6 border-b border-gray-200 pb-3">
            <div class="flex items-center">
                <i class="fas fa-bars mr-2 cursor-pointer" @click="isViewsSidebarOpen = !isViewsSidebarOpen"></i>
                <h1 class="text-lg font-bold text-gray-800" x-text="
                            activeViewSection === 'my-tickets' ? 'My Tickets' : 
                            activeViewSection === 'past-due' ? 'Past Due Tickets' : 
                            activeViewSection === 'unassigned' ? 'Unassigned Tickets' : 
                            activeViewSection === 'high-priority' ? 'High Priority Tickets' : 
                            'All Tickets'
                        ">My Tickets</h1>
                <i class="fas fa-chevron-down ml-2 text-xs"></i>
            </div>
            <div class="flex items-center space-x-6" style="margin-top: -2px">
                <button class="flex items-center text-gray-600">
                    <i class="fas fa-ticket-alt mr-1"></i>
                    <span>My Tickets</span>
                </button>
                <button class="flex items-center text-gray-600">
                    <i class="fas fa-star mr-1"></i>
                    <span>Priority</span>
                </button>
                <button class="flex items-center text-gray-600">
                    <i class="fas fa-calendar mr-1"></i>
                    <span>Date Added</span>
                </button>
                <button class="flex items-center text-gray-600">
                    <i class="fas fa-filter mr-1"></i>
                    <span>Search Filters</span>
                </button>
            </div>
        </div>

        <div x-show="activeViewSection !== 'all-tickets'" class="kanban-container">
            <div class="kanban-columns" x-data="ticketTable()">
                <!-- open Column -->
                <div class="kanban-column">
                    <h2 class="text-md font-semibold text-gray-800 mb-4">Open</h2>
                    <div class="ticket-list">
                        <template x-for="ticket in paginatedTickets('open')" :key="ticket.id">
                            <!-- Ticket Card -->
                            <div class="bg-white rounded-lg shadow-md p-3 mb-4 hover:shadow-lg transition-shadow">
                                <!-- Ticket Header -->
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-sm font-semibold text-gray-800" x-text="ticket.title"></h3>
                                    <button @click.stop="toggleTicketExpand(ticket.id)"
                                        class="text-gray-600 hover:text-gray-900">
                                        <i class="fas"
                                            :class="expandedTicketId === ticket.id ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                </div>

                                <div class="text-xs text-gray-600">
                                    <p><strong>Ticket #:</strong> <span x-text="ticket.id"></span></p>
                                    <p><strong>Category:</strong> <span x-text="ticket.category_name"></span></p>
                                    <p><strong>Priority:</strong>
                                        <span class="px-2 py-1 rounded text-xs" :class="{
                    'bg-red-500 text-white': ticket.priority_name === 'High',
                    'bg-yellow-500 text-white': ticket.priority_name === 'Medium',
                    'bg-green-500 text-white': ticket.priority_name === 'Low'
                }" x-text="ticket.priority_name"></span>
                                    </p>
                                    <p><strong>Created:</strong> <span x-text="formatDate(ticket.created_at)"></span>
                                    </p>
                                    <p><strong>Assigned To:</strong> <span
                                            x-text="ticket.assigned_to_name || 'Unassigned'"></span></p>
                                </div>

                                <!-- Expanded Content -->
                                <div x-show="expandedTicketId === ticket.id" x-cloak class="mt-4 space-y-4">
                                    <!-- Description -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Description</h4>
                                        <p class="text-gray-600"
                                            x-text="ticket.description || 'No description available'"></p>
                                    </div>

                                    <!-- Actions -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Actions</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Set
                                                    Category</label>
                                                <select
                                                    x-init="if(!tempAssignments[ticket.id]) initTempAssignment(ticket.id)"
                                                    x-model="tempAssignments[ticket.id] ? tempAssignments[ticket.id].category_id : ''"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">Select Category</option>
                                                    <template x-for="category in categories" :key="category.id">
                                                        <option :value="category.id" x-text="category.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Assigned To Dropdown -->
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Assigned
                                                    To</label>
                                                <select x-model="tempAssignments[ticket.id].assigned_to"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">unassigned</option>
                                                    <template x-for="user in assignableUsers" :key="user.id">
                                                        <option :value="user.id" x-text="user.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-xs font-medium text-gray-700 mb-1">Priority</label>
                                                <select x-model="tempAssignments[ticket.id].priority_id"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">Select Priority</option>
                                                    <template x-for="priority in priorities" :key="priority.id">
                                                        <option :value="priority.id" x-text="priority.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Update and View Details Buttons -->
                                            <div class="flex space-x-2">
                                                <button @click="updateTicket(ticket.id, ticket)"
                                                    class="flex-1 bg-blue-500 text-white py-1 rounded hover:bg-blue-600">
                                                    Update
                                                </button>
                                                <!-- Replace Delete Button with View Details Button -->
                                                <button @click="openTicketDetailsModal(ticket.id)"
                                                    class="flex-1 bg-green-500 text-white py-1 rounded hover:bg-green-600">
                                                    View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="pagination-controls">
                        <div class="flex justify-between">
                            <button @click="prevPage('open')" :disabled="currentPages['open'] === 1"
                                class="px-3 py-1 bg-gray-200 rounded">
                                Previous
                            </button>
                            <span x-text="`Page ${currentPages['open']}`"></span>
                            <button @click="nextPage('open')"
                                :disabled="currentPages['open'] * itemsPerPage >= getFilteredTickets().length"
                                class="px-3 py-1 bg-gray-200 rounded">
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Seen Column -->
                <div class="kanban-column">
                    <h2 class="text-md font-semibold text-gray-800 mb-4">Processing & Pending</h2>
                    <div class="ticket-list">
                        <template x-for="ticket in paginatedTickets('seen')" :key="ticket.id">
                            <!-- Ticket Card -->
                            <div class="bg-white rounded-lg shadow-md p-3 mb-4 hover:shadow-lg transition-shadow">
                                <!-- Ticket Header -->
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-sm font-semibold text-gray-800" x-text="ticket.title"></h3>
                                    <button @click.stop="toggleTicketExpand(ticket.id)"
                                        class="text-gray-600 hover:text-gray-900">
                                        <i class="fas"
                                            :class="expandedTicketId === ticket.id ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                </div>

                                <div class="text-xs text-gray-600">
                                    <p><strong>Ticket #:</strong> <span x-text="ticket.id"></span></p>
                                    <p><strong>Category:</strong> <span x-text="ticket.category_name"></span></p>
                                    <p><strong>Priority:</strong>
                                        <span class="px-2 py-1 rounded text-xs" :class="{
                    'bg-red-500 text-white': ticket.priority_name === 'High',
                    'bg-yellow-500 text-white': ticket.priority_name === 'Medium',
                    'bg-green-500 text-white': ticket.priority_name === 'Low'
                }" x-text="ticket.priority_name"></span>
                                    </p>
                                    <p><strong>Created:</strong> <span x-text="formatDate(ticket.created_at)"></span>
                                    </p>
                                    <p><strong>Assigned To:</strong> <span
                                            x-text="ticket.assigned_to_name || 'Unassigned'"></span></p>
                                </div>

                                <!-- Expanded Content -->
                                <div x-show="expandedTicketId === ticket.id" x-cloak class="mt-4 space-y-4">
                                    <!-- Description -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Description</h4>
                                        <p class="text-gray-600"
                                            x-text="ticket.description || 'No description available'"></p>
                                    </div>

                                    <!-- Actions -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Actions</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Set
                                                    Category</label>
                                                <select
                                                    x-init="if(!tempAssignments[ticket.id]) initTempAssignment(ticket.id)"
                                                    x-model="tempAssignments[ticket.id] ? tempAssignments[ticket.id].category_id : ''"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">Select Category</option>
                                                    <template x-for="category in categories" :key="category.id">
                                                        <option :value="category.id" x-text="category.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Assigned
                                                    To</label>
                                                <select x-model="tempAssignments[ticket.id].assigned_to"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">unassigned</option>
                                                    <template x-for="user in assignableUsers" :key="user.id">
                                                        <option :value="user.id" x-text="user.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-xs font-medium text-gray-700 mb-1">Priority</label>
                                                <select x-model="tempAssignments[ticket.id].priority_id"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">Select Priority</option>
                                                    <template x-for="priority in priorities" :key="priority.id">
                                                        <option :value="priority.id" x-text="priority.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Update and View Details Buttons -->
                                            <div class="flex space-x-2">
                                                <button @click="updateTicket(ticket.id, ticket)"
                                                    class="flex-1 bg-blue-500 text-white py-1 rounded hover:bg-blue-600">
                                                    Update
                                                </button>
                                                <!-- Replace Delete Button with View Details Button -->
                                                <button @click="openTicketDetailsModal(ticket.id)"
                                                    class="flex-1 bg-green-500 text-white py-1 rounded hover:bg-green-600">
                                                    View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <!-- Pagination Controls -->
                    <div class="pagination-controls">
                        <div class="flex justify-between">
                            <button @click="prevPage('seen')" :disabled="currentPages['seen'] === 1"
                                class="px-3 py-1 bg-gray-200 rounded">
                                Previous
                            </button>
                            <span x-text="`Page ${currentPages['seen']}`"></span>
                            <button @click="nextPage('seen')"
                                :disabled="currentPages['seen'] * itemsPerPage >= getFilteredTickets().length"
                                class="px-3 py-1 bg-gray-200 rounded">
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Resolved Column -->
                <div class="kanban-column">
                    <h2 class="text-md font-semibold text-gray-800 mb-4">Resolved</h2>
                    <div class="ticket-list">
                        <template x-for="ticket in paginatedTickets('resolved')" :key="ticket.id">
                            <!-- Ticket Card -->
                            <div class="bg-white rounded-lg shadow-md p-3 mb-4 hover:shadow-lg transition-shadow">
                                <!-- Ticket Header -->
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-sm font-semibold text-gray-800" x-text="ticket.title"></h3>
                                    <button @click.stop="toggleTicketExpand(ticket.id)"
                                        class="text-gray-600 hover:text-gray-900">
                                        <i class="fas"
                                            :class="expandedTicketId === ticket.id ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                                    </button>
                                </div>

                                <div class="text-xs text-gray-600">
                                    <p><strong>Ticket #:</strong> <span x-text="ticket.id"></span></p>
                                    <p><strong>Category:</strong> <span x-text="ticket.category_name"></span></p>
                                    <p><strong>Priority:</strong>
                                        <span class="px-2 py-1 rounded text-xs" :class="{
                    'bg-red-500 text-white': ticket.priority_name === 'High',
                    'bg-yellow-500 text-white': ticket.priority_name === 'Medium',
                    'bg-green-500 text-white': ticket.priority_name === 'Low'
                }" x-text="ticket.priority_name"></span>
                                    </p>
                                    <p><strong>Created:</strong> <span x-text="formatDate(ticket.created_at)"></span>
                                    </p>
                                    <p><strong>Assigned To:</strong> <span
                                            x-text="ticket.assigned_to_name || 'Unassigned'"></span></p>
                                </div>

                                <!-- Expanded Content -->
                                <div x-show="expandedTicketId === ticket.id" x-cloak class="mt-4 space-y-4">
                                    <!-- Description -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Description</h4>
                                        <p class="text-gray-600"
                                            x-text="ticket.description || 'No description available'"></p>
                                    </div>

                                    <!-- Actions -->
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800">Actions</h4>
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Set
                                                    Category</label>
                                                <select
                                                    x-init="if(!tempAssignments[ticket.id]) initTempAssignment(ticket.id)"
                                                    x-model="tempAssignments[ticket.id] ? tempAssignments[ticket.id].category_id : ''"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">Select Category</option>
                                                    <template x-for="category in categories" :key="category.id">
                                                        <option :value="category.id" x-text="category.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Assigned To Dropdown -->
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 mb-1">Assigned
                                                    To</label>
                                                <select x-model="tempAssignments[ticket.id].assigned_to"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">unassigned</option>
                                                    <template x-for="user in assignableUsers" :key="user.id">
                                                        <option :value="user.id" x-text="user.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <div>
                                                <label
                                                    class="block text-xs font-medium text-gray-700 mb-1">Priority</label>
                                                <select x-model="tempAssignments[ticket.id].priority_id"
                                                    class="w-full border rounded px-2 py-1 text-xs">
                                                    <option value="">Select Priority</option>
                                                    <template x-for="priority in priorities" :key="priority.id">
                                                        <option :value="priority.id" x-text="priority.name"></option>
                                                    </template>
                                                </select>
                                            </div>

                                            <!-- Update and View Details Buttons -->
                                            <div class="flex space-x-2">
                                                <button @click="updateTicket(ticket.id, ticket)"
                                                    class="flex-1 bg-blue-500 text-white py-1 rounded hover:bg-blue-600">
                                                    Update
                                                </button>
                                                <!-- Replace Delete Button with View Details Button -->
                                                <button @click="openTicketDetailsModal(ticket.id)"
                                                    class="flex-1 bg-green-500 text-white py-1 rounded hover:bg-green-600">
                                                    View Details
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <!-- Pagination Controls -->
                    <button @click="prevPage('resolved')" :disabled="currentPages['resolved'] === 1"
                        class="px-3 py-1 bg-gray-200 rounded">
                        Previous
                    </button>
                    <span x-text="`Page ${currentPages['resolved']}`"></span>
                    <button @click="nextPage('resolved')"
                        :disabled="currentPages['resolved'] * itemsPerPage >= getFilteredTickets().length"
                        class="px-3 py-1 bg-gray-200 rounded">
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ALL TICKETS TABLE -->
    <div class="flex-1 py-4 px-8" x-show="activeViewSection === 'all-tickets'" x-data="ticketsTableView()">
        <!-- Aligned Tickets Nav -->
        <div class="flex items-center justify-between mb-6 border-b border-gray-200 pb-3">
            <div class="flex items-center">
                <i class="fas fa-bars mr-2 cursor-pointer" @click="isViewsSidebarOpen = !isViewsSidebarOpen"></i>
                <h1 class="text-lg font-bold text-gray-800">All Tickets</h1>
                <i class="fas fa-chevron-down ml-2 text-xs"></i>
            </div>
            <!-- Controls for table view -->
            <div class="bg-white p-4 mb-4 rounded-lg shadow"">
                <div class=" grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search input -->
                <div>
                    <label for="searchQuery" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" name="searchQuery" id="searchQuery"
                            class="focus:ring-blue-500 focus:border-blue-500 block w-full pl-10 pr-3 py-2 sm:text-sm border-gray-300 rounded-md"
                            placeholder="Search tickets..." x-model="filters.searchQuery">
                    </div>
                </div>

                <!-- Status filter -->
                <div>
                    <label for="statusFilter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="statusFilter"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        x-model="filters.status" @change="applyFilters()">
                        <option value="">All Statuses</option>
                        <option value="open">Open</option>
                        <option value="seen">Processing</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>

                <!-- Priority filter -->
                <div>
                    <label for="priorityFilter" class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select id="priorityFilter"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        x-model="filters.priority" @change="applyFilters()">
                        <option value="">All Priorities</option>
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>

                <!-- Category filter -->
                <div>
                    <label for="categoryFilter" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select id="categoryFilter"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        x-model="filters.category" @change="applyFilters()">
                        <option value="">All Categories</option>
                        <template x-for="category in getUniqueValues('category_name')" :key="category">
                            <option :value="category" x-text="category"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- Additional filters row -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                <div>
                    <label for="assignedToFilter" class="block text-sm font-medium text-gray-700 mb-1">Assigned
                        To</label>
                    <select id="assignedToFilter"
                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                        x-model="filters.assignedTo">
                        <option value="">All Agents</option>
                        <template x-for="staff in staffMembers" :key="staff.id">
                            <option :value="staff.name" x-text="staff.name"></option>
                        </template>
                    </select>
                </div>

                <!-- Reset filters button -->
                <div class="flex items-end">
                    <button @click="resetFilters()"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        <i class="fas fa-times-circle mr-2"></i>
                        Reset Filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- All Tickets Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <!-- Table Header -->
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="sortBy('id')">
                        <div class="flex items-center">
                            Ticket #
                            <i class="fas fa-sort ml-1" :class="{
                                'fa-sort-up': sortField === 'id' && sortDirection === 'asc',
                                'fa-sort-down': sortField === 'id' && sortDirection === 'desc',
                            }"></i>
                        </div>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="sortBy('title')">
                        <div class="flex items-center">
                            Title
                            <i class="fas fa-sort ml-1" :class="{
                                'fa-sort-up': sortField === 'title' && sortDirection === 'asc',
                                'fa-sort-down': sortField === 'title' && sortDirection === 'desc',
                            }"></i>
                        </div>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="sortBy('category_name')">
                        <div class="flex items-center">
                            Category
                            <i class="fas fa-sort ml-1" :class="{
                                'fa-sort-up': sortField === 'category_name' && sortDirection === 'asc',
                                'fa-sort-down': sortField === 'category_name' && sortDirection === 'desc',
                            }"></i>
                        </div>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="sortBy('status')">
                        <div class="flex items-center">
                            Status
                            <i class="fas fa-sort ml-1" :class="{
                                'fa-sort-up': sortField === 'status' && sortDirection === 'asc',
                                'fa-sort-down': sortField === 'status' && sortDirection === 'desc',
                            }"></i>
                        </div>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="sortBy('priority_name')">
                        <div class="flex items-center">
                            Priority
                            <i class="fas fa-sort ml-1" :class="{
                                'fa-sort-up': sortField === 'priority_name' && sortDirection === 'asc',
                                'fa-sort-down': sortField === 'priority_name' && sortDirection === 'desc',
                            }"></i>
                        </div>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="sortBy('assigned_to_name')">
                        <div class="flex items-center">
                            Assigned To
                            <i class="fas fa-sort ml-1" :class="{
                                'fa-sort-up': sortField === 'assigned_to_name' && sortDirection === 'asc',
                                'fa-sort-down': sortField === 'assigned_to_name' && sortDirection === 'desc',
                            }"></i>
                        </div>
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer"
                        @click="sortBy('created_at')">
                        <div class="flex items-center">
                            Created
                            <i class="fas fa-sort ml-1" :class="{
                                'fa-sort-up': sortField === 'created_at' && sortDirection === 'asc',
                                'fa-sort-down': sortField === 'created_at' && sortDirection === 'desc',
                            }"></i>
                        </div>
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <template x-for="ticket in paginatedTickets" :key="ticket.id">
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" x-text="ticket.id">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" x-text="ticket.title"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                            x-text="ticket.category_name || 'Uncategorized'"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="{
                                      'bg-yellow-100 text-yellow-800': ticket.status === 'open',
                                      'bg-blue-100 text-blue-800': ticket.status === 'seen',
                                      'bg-green-100 text-green-800': ticket.status === 'resolved'
                                  }" x-text="ticket.status === 'open' ? 'open' : 
                                          ticket.status === 'seen' ? 'Processing' : 'Resolved'">
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full" :class="{
                                      'bg-red-100 text-red-800': ticket.priority_name === 'High',
                                      'bg-yellow-100 text-yellow-800': ticket.priority_name === 'Medium',
                                      'bg-green-100 text-green-800': ticket.priority_name === 'Low'
                                  }" x-text="ticket.priority_name || 'Unset'">
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                            x-text="ticket.assigned_to_name || getStaffNameById(ticket.assigned_to) ">
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                            x-text="formatDate(ticket.created_at)"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button @click="openTicketDetailsModal(ticket.id)"
                                class="text-blue-600 hover:text-blue-900 mr-3">
                                View
                            </button>
                            <button @click="editTicket(ticket.id)" class="text-green-600 hover:text-green-900 mr-3">
                                Edit
                            </button>
                            <button @click="deleteTicket(ticket.id)" class="text-red-600 hover:text-red-900">
                                Delete
                            </button>
                        </td>
                    </tr>
                </template>
                <!-- Empty state when no tickets are found -->
                <tr x-show="paginatedTickets.length === 0">
                    <td colspan="8" class="px-6 py-10 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-ticket-alt text-4xl mb-3 text-gray-300"></i>
                            <p class="text-lg font-medium">No tickets found</p>
                            <p class="text-sm">Try adjusting your search or filter criteria</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination Controls -->
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            <div class="flex-1 flex justify-between sm:hidden">
                <button @click="prevPage()" :disabled="currentPage === 1"
                    class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }">
                    Previous
                </button>
                <button @click="nextPage()" :disabled="currentPage >= totalPages"
                    class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                    :class="{ 'opacity-50 cursor-not-allowed': currentPage >= totalPages }">
                    Next
                </button>
            </div>
            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700">
                        Showing
                        <span class="font-medium" x-text="(currentPage - 1) * itemsPerPage + 1"></span>
                        to
                        <span class="font-medium"
                            x-text="Math.min(currentPage * itemsPerPage, filteredTickets.length)"></span>
                        of
                        <span class="font-medium" x-text="filteredTickets.length"></span>
                        results
                    </p>
                </div>
                <div>
                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                        <button @click="prevPage()" :disabled="currentPage === 1"
                            class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                            :class="{ 'opacity-50 cursor-not-allowed': currentPage === 1 }">
                            <span class="sr-only">Previous</span>
                            <i class="fas fa-chevron-left h-5 w-5"></i>
                        </button>

                        <!-- Page number buttons (dynamic) -->
                        <template x-for="page in pageNumbers" :key="page">
                            <button @click="goToPage(page)"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50"
                                :class="page === currentPage ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'text-gray-500'">
                                <span x-text="page"></span>
                            </button>
                        </template>

                        <button @click="nextPage()" :disabled="currentPage >= totalPages"
                            class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
                            :class="{ 'opacity-50 cursor-not-allowed': currentPage >= totalPages }">
                            <span class="sr-only">Next</span>
                            <i class="fas fa-chevron-right h-5 w-5"></i>
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div x-data="ticketDetailsModal()" x-show="isOpen" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
    <div class="flex items-center justify-center min-h-screen p-4">
        <!-- Modal Backdrop -->
        <div x-show="isOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="closeModal()"
            class="fixed inset-0 bg-black bg-opacity-50">
        </div>

        <!-- Modal Content -->
        <div x-show="isOpen" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95"
            class="relative bg-white rounded-lg shadow-xl w-full max-w-4xl mx-auto max-h-[90vh] overflow-y-auto">

            <div class="flex justify-between items-center p-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                <h2 class="text-xl font-bold text-gray-800" x-text="'Ticket #' + (currentTicket?.id || '')"></h2>
                <p class="text-sm text-gray-600"><strong>Reference ID:</strong> <span
                        x-text="currentTicket?.ref_id"></span></p>
                <div class="flex items-center space-x-4">
                    <button @click="archiveTicket()"
                        class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded">
                        <i class="fas fa-archive mr-2"></i>Archive
                    </button>
                    <button @click="closeModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="p-6 grid grid-cols-3 gap-6">
                <!-- Left Column - Ticket Details -->
                <div class="col-span-2 space-y-6">
                    <!-- Ticket Info -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="text-lg font-semibold mb-2" x-text="currentTicket?.title || 'Loading...'"></h3>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-sm text-gray-600"><strong>Created:</strong> <span
                                        x-text="formatDate(currentTicket?.created_at)"></span></p>
                                <p class="text-sm text-gray-600"><strong>Status:</strong> <span
                                        x-text="currentTicket?.status || 'Unknown'"></span></p>
                                <p class="text-sm text-gray-600"><strong>Priority:</strong> <span
                                        x-text="currentTicket?.priority_name || 'Unknown'"></span></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600"><strong>Category:</strong> <span
                                        x-text="currentTicket?.category_name || 'Unknown'"></span></p>
                                <p class="text-sm text-gray-600"><strong>Assigned To:</strong> <span
                                        x-text="currentTicket?.assigned_to_name || 'Unassigned'"></span></p>
                                <p class="text-sm text-gray-600"><strong>Created By:</strong> <span
                                        x-text="currentTicket?.created_by_name || 'Unknown'"></span></p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium mb-1">Description:</h4>
                            <div class="p-3 bg-white rounded border border-gray-200 text-sm"
                                x-html="currentTicket?.description || 'No description provided.'"></div>
                        </div>
                    </div>

                    <!-- Ticket Activity Timeline -->
                    <div>
                        <h3 class="text-lg font-semibold mb-2">Activity & Comments</h3>

                        <div class="flex border-b border-gray-200 mb-4">
                            <button @click="activeTab = 'user'"
                                :class="activeTab === 'user' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700'"
                                class="py-2 px-4 font-medium">
                                User Communication
                            </button>
                            <button @click="activeTab = 'internal'"
                                :class="activeTab === 'internal' ? 'border-b-2 border-blue-500 text-blue-600' : 'text-gray-500 hover:text-gray-700 disabled:opacity-50'"
                                class="py-2 px-4 font-medium" :disabled="isGuestUser"
                                :title="isGuestUser ? 'Agent access only' : ''">
                                Internal Notes
                            </button>
                        </div>

                        <!-- This is the tab content area where comments should be displayed -->
                        <div class="bg-gray-50 rounded-lg border border-gray-200 h-96 flex flex-col">
                            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                                <!-- This is where comments should appear -->
                                <template x-for="(activity, index) in filteredTicketHistory" :key="index">
                                    <!-- Comment display code -->
                                    <div class="flex flex-col" :class="{
                'items-end': activity.is_agent && !activity.is_internal, 
                'items-start': !activity.is_agent || activity.is_internal
            }">
                                        <div class="max-w-[80%] mb-1">
                                            <div class="p-3 rounded-lg shadow-sm" :class="{
                        'bg-blue-100 border border-blue-200 rounded-tr-none': activity.is_agent && !activity.is_internal, 
                        'bg-white border border-gray-200 rounded-tl-none': !activity.is_agent,
                        'bg-yellow-50 border border-yellow-200 rounded-tl-none': activity.is_internal
                    }">
                                                <!-- Comment content -->
                                                <div class="flex items-start gap-2 mb-2">
                                                    <!-- Avatar -->
                                                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                                        :class="{
                                    'bg-blue-500 text-white': activity.is_agent && !activity.is_internal,
                                    'bg-gray-500 text-white': !activity.is_agent,
                                    'bg-yellow-500 text-white': activity.is_internal
                                }">
                                                        <span class="text-xs font-bold"
                                                            x-text="activity.user_name?.charAt(0) || 'U'"></span>
                                                    </div>
                                                    <!-- Username and timestamp -->
                                                    <div class="flex-1">
                                                        <div class="flex justify-between items-center">
                                                            <span class="font-medium text-sm"
                                                                x-text="activity.user_name || 'Unknown User'"></span>
                                                            <span class="text-xs text-gray-500"
                                                                x-text="formatDate(activity.created_at)"></span>
                                                        </div>
                                                        <div x-show="activity.is_internal"
                                                            class="text-xs bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded inline-block mb-1">
                                                            Internal Note
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Comment text -->
                                                <div class="text-sm email-content break-words"
                                                    x-html="activity.content"></div>

                                                <!-- Attachments section -->
                                                <div x-show="activity.attachments && activity.attachments.length > 0"
                                                    class="mt-3 pt-2 border-t border-gray-200">
                                                    <p class="text-xs font-medium text-gray-500 mb-1">Attachments:</p>
                                                    <div class="flex flex-wrap gap-2">
                                                        <!-- In your ticket details modal, where attachments are displayed -->
                                                        <template x-for="(file, fileIndex) in activity.attachments"
                                                            :key="fileIndex">
                                                            <div
                                                                class="flex items-center bg-gray-100 rounded px-2 py-1 text-xs">
                                                                <i class="fas fa-paperclip mr-1 text-gray-500"></i>
                                                                <!-- Make filename clickable -->
                                                                <span x-text="file.filename || file.name"
                                                                    @click="downloadAttachment(file)"
                                                                    class="mr-1 truncate max-w-[120px] cursor-pointer hover:text-blue-500"></span>
                                                                <button @click="downloadAttachment(file)"
                                                                    class="text-blue-500 hover:text-blue-700"
                                                                    title="Download">
                                                                    <i class="fas fa-download"></i>
                                                                </button>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="filteredTicketHistory.length === 0"
                                    class="flex flex-col items-center justify-center h-full text-gray-500">
                                    <i class="fas fa-comments text-4xl mb-2"></i>
                                    <p>No activity in this category yet</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-2">Add Response</h3>

                        <div id="editor-container-wrapper" class="mb-4">
                        </div>

                        <!-- Attachments -->
                        <div x-show="commentAttachments.length > 0" class="mt-3 space-y-2">
                            <h4 class="text-sm font-medium">Attachments:</h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <template x-for="(attachment, index) in commentAttachments" :key="index">
                                    <div
                                        class="flex items-center justify-between bg-gray-50 p-2 rounded border border-gray-200">
                                        <div class="flex items-center overflow-hidden">
                                            <i class="fas fa-file mr-2 text-gray-500"></i>
                                            <span class="text-sm truncate" x-text="attachment.name"></span>
                                        </div>
                                        <button @click="removeCommentAttachment(index)"
                                            class="text-red-500 hover:text-red-700 ml-2 flex-shrink-0">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3 justify-between items-center">
                            <button @click="addCommentAttachment()"
                                class="flex items-center px-3 py-2 border border-gray-300 rounded hover:bg-gray-50">
                                <i class="fas fa-paperclip mr-2"></i>
                                <span>Add Attachment</span>
                            </button>

                            <div class="flex space-x-3">
                                <button @click="addComment(true)"
                                    class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                                    Internal Note
                                </button>
                                <button @click="addComment(false)"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                    Post Reply
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Actions & Info -->
                <div class="col-span-1 space-y-6">
                    <!-- Ticket Actions -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4">Ticket Actions</h3>

                        <div class="mb-4">
                            <h4 class="font-medium mb-2">Update Status</h4>
                            <div class="flex flex-wrap gap-2">
                                <button @click="updateStatus('open')" class="px-3 py-1 text-sm rounded-full"
                                    :class="currentTicket?.status === 'open' ? 'bg-green-500 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                    Open
                                </button>
                                <button @click="updateStatus('seen')" class="px-3 py-1 text-sm rounded-full"
                                    :class="currentTicket?.status === 'seen' ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                    In Progress
                                </button>
                                <button @click="updateStatus('pending')" class="px-3 py-1 text-sm rounded-full"
                                    :class="currentTicket?.status === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                    Pending
                                </button>
                                <button @click="updateStatus('resolved')" class="px-3 py-1 text-sm rounded-full"
                                    :class="currentTicket?.status === 'resolved' ? 'bg-purple-500 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                    Resolved
                                </button>
                                <button @click="updateStatus('resolved')" class="px-3 py-1 text-sm rounded-full"
                                    :class="currentTicket?.status === 'resolved' ? 'bg-gray-600 text-white' : 'bg-gray-200 hover:bg-gray-300'">
                                    Closed
                                </button>
                            </div>
                        </div>

                        <!-- Priority Update -->
                        <div class="mb-4">
                            <h4 class="font-medium mb-2">Update Priority</h4>
                            <select x-model="currentTicket.priority_id" @change="updatePriority($event.target.value)"
                                class="w-full p-2 border border-gray-300 rounded">
                                <template x-for="priority in priorities" :key="priority.id">
                                    <option :value="priority.id" :selected="priority.id === currentTicket?.priority_id"
                                        x-text="priority.name"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Assign To -->
                        <div>
                            <h4 class="font-medium mb-2">Assign To</h4>
                            <select x-model="currentTicket.assigned_to" @change="assignTicket($event.target.value)"
                                class="w-full p-2 border border-gray-300 rounded">
                                <option value="" :selected="!currentTicket?.assigned_to">-- Unassigned --</option>
                                <template x-for="user in assignableUsers" :key="user.id">
                                    <option :value="user.id" :selected="user.id === currentTicket?.assigned_to"
                                        x-text="user.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <!-- Ticket Attachments -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-3">Attachments</h3>
                        <button @click="addAttachment()"
                            class="w-full flex items-center justify-center p-2 mb-3 bg-gray-100 hover:bg-gray-200 border border-dashed border-gray-300 rounded">
                            <i class="fas fa-plus-circle mr-2"></i> Add Attachment
                        </button>

                        <!-- In the Ticket Attachments section -->
                        <div class="space-y-2">
                            <!-- Existing attachments -->
                            <template x-for="(attachment, index) in attachments" :key="index">
                                <div
                                    class="p-2 bg-gray-50 rounded border border-gray-200 flex items-center justify-between">
                                    <div class="flex items-center overflow-hidden">
                                        <i class="fas fa-paperclip mr-2 text-gray-500"></i>
                                        <!-- Make the filename clickable -->
                                        <span class="text-sm truncate cursor-pointer hover:text-blue-600"
                                            x-text="attachment.filename" @click="downloadAttachment(attachment)"></span>
                                    </div>
                                    <div class="flex items-center">
                                        <a :href="attachment.file_path"
                                            class="ml-2 text-gray-500 hover:text-blue-600 transition-colors p-1"
                                            download @click.stop="downloadAttachment(attachment)"
                                            title="Download attachment">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <button @click="deleteAttachment(attachment.id, index)"
                                            class="ml-1 text-red-500 hover:text-red-700 p-1" title="Delete attachment">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Pending attachments -->
                            <template x-for="(attachment, index) in pendingAttachments" :key="attachment.id">
                                <div
                                    class="p-2 bg-yellow-50 rounded border border-yellow-200 flex items-center justify-between">
                                    <div class="flex items-center overflow-hidden">
                                        <i class="fas fa-paperclip mr-2 text-yellow-500"></i>
                                        <span class="text-sm truncate" x-text="attachment.name"></span>
                                        <span class="ml-2 text-xs text-yellow-600">(Pending)</span>
                                    </div>
                                    <div class="flex items-center">
                                        <button @click="confirmAttachment(index)"
                                            class="ml-2 text-green-500 hover:text-green-700 p-1" title="Confirm upload">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button @click="cancelPendingAttachment(index)"
                                            class="ml-1 text-red-500 hover:text-red-700 p-1" title="Cancel upload">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div x-show="attachments.length === 0 && pendingAttachments.length === 0"
                                class="text-gray-500 text-sm text-center py-2">
                                No attachments
                            </div>
                        </div>
                    </div>

                    <!-- Related Information -->
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-3">Additional Information</h3>
                        <div class="space-y-2 text-sm">
                            <p><strong>Last Updated:</strong> <span
                                    x-text="formatDate(currentTicket?.updated_at)"></span>
                            </p>
                            <p><strong>SLA Status:</strong> <span class="text-green-600">Within SLA</span></p>
                            <p><strong>Department:</strong> <span x-text="currentTicket?.department || 'N/A'"></span>
                            </p>
                            <p><strong>Product:</strong> <span x-text="currentTicket?.product || 'N/A'"></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div x-data="enhancedImageViewer()" x-show="isOpen" class="fixed inset-0 z-50 overflow-hidden" x-cloak>
    <!-- Modal Backdrop -->
    <div x-show="isOpen" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="closeViewer()"
        class="fixed inset-0 bg-black bg-opacity-80">
    </div>

    <!-- Image Viewer Content -->
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="relative bg-white rounded-lg shadow-xl overflow-hidden"
            style="max-width: 1000px; max-height: 90vh; width: 95%;">
            <!-- Header -->
            <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 truncate" x-text="imageTitle"></h3>
                <div class="flex space-x-3">
                    <!-- Zoom controls -->
                    <button @click="zoomOut()" class="text-gray-600 hover:text-gray-900 px-2">
                        <i class="fas fa-search-minus"></i>
                    </button>
                    <button @click="resetZoom()" class="text-gray-600 hover:text-gray-900 px-2">
                        <span x-text="Math.round(zoomLevel * 100) + '%'"></span>
                    </button>
                    <button @click="zoomIn()" class="text-gray-600 hover:text-gray-900 px-2">
                        <i class="fas fa-search-plus"></i>
                    </button>
                    <a :href="imageUrl" download class="text-blue-500 hover:text-blue-700 px-2">
                        <i class="fas fa-download"></i>
                    </a>
                    <button @click="closeViewer()" class="text-gray-500 hover:text-gray-700 px-2">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Image Container with pan and zoom -->
            <div class="overflow-auto bg-gray-800 relative" style="max-height: calc(90vh - 70px);" @mousedown="startPan"
                @mousemove="pan" @mouseup="endPan" @mouseleave="endPan" @wheel.prevent="handleZoomWheel">
                <div class="flex items-center justify-center min-h-[400px]"
                    :style="`transform: scale(${zoomLevel}); transform-origin: 0 0; cursor: ${isPanning ? 'grabbing' : 'grab'};`"
                    :class="{'transition-transform duration-200': !isPanning}">
                    <img :src="imageUrl" class="max-w-full object-contain" style="transform-origin: center center;"
                        :style="`transform: translate(${panX}px, ${panY}px);`" alt="Image preview">
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function enhancedImageViewer() {
        return {
            isOpen: false,
            imageUrl: '',
            imageTitle: '',
            zoomLevel: 1,
            minZoom: 0.5,
            maxZoom: 5,
            zoomStep: 0.2,
            panX: 0,
            panY: 0,
            isPanning: false,
            lastPanX: 0,
            lastPanY: 0,

            init() {
                window.addEventListener('open-image-viewer', (event) => {
                    this.openViewer(event.detail.imageUrl, event.detail.title);
                });
            },

            openViewer(url, title) {
                this.imageUrl = url;
                this.imageTitle = title || 'Image Preview';
                this.resetZoom();
                this.isOpen = true;
                document.body.classList.add('overflow-hidden');
            },

            closeViewer() {
                this.isOpen = false;
                document.body.classList.remove('overflow-hidden');
            },

            zoomIn() {
                this.zoomLevel = Math.min(this.maxZoom, this.zoomLevel + this.zoomStep);
            },

            zoomOut() {
                this.zoomLevel = Math.max(this.minZoom, this.zoomLevel - this.zoomStep);
            },

            resetZoom() {
                this.zoomLevel = 1;
                this.panX = 0;
                this.panY = 0;
            },

            handleZoomWheel(e) {
                if (e.deltaY < 0) {
                    this.zoomIn();
                } else {
                    this.zoomOut();
                }
            },

            startPan(e) {
                this.isPanning = true;
                this.lastPanX = e.clientX;
                this.lastPanY = e.clientY;
            },

            pan(e) {
                if (!this.isPanning) return;

                const deltaX = e.clientX - this.lastPanX;
                const deltaY = e.clientY - this.lastPanY;

                this.panX += deltaX;
                this.panY += deltaY;

                this.lastPanX = e.clientX;
                this.lastPanY = e.clientY;
            },

            endPan() {
                this.isPanning = false;
            }
        };
    }
</script>
</div>