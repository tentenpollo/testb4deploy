function ticketDetailsModal() {
    console.log("INITIALIZING TICKET DETAILS MODAL");
    return {
        isOpen: false,
        
        currentTicket: null,
        ticketHistory: [],
        attachments: [],
        priorities: [],
        assignableUsers: [],
        
        attachments: [],
        
        async openModal(ticketId) {
            this.isOpen = true;
            await this.loadTicketDetails(ticketId);
            await this.loadTicketHistory(ticketId);
            await this.loadPriorities();
            await this.loadAssignableUsers();
            document.body.classList.add('overflow-hidden');
        },
        
        closeModal() {
            this.isOpen = false;
            this.currentTicket = null;
            this.ticketHistory = [];
            this.attachments = [];
            document.body.classList.remove('overflow-hidden');
        },
        
        async loadTicketDetails(ticketId) {
            try {
                const response = await fetch(`admin/ajax/ajax_handlers.php?action=get_ticket_details&ticket_id=${ticketId}`);
                const data = await response.json();
                
                if (data.success) {
                    this.currentTicket = data.ticket;
                } else {
                    alert('Error loading ticket details: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to load ticket details:', error);
                alert('Failed to load ticket details. Please try again.');
            }
        },
        
        async loadTicketHistory(ticketId) {
            try {
                const response = await fetch(`admin/ajax/ajax_handlers.php?action=get_ticket_history&ticket_id=${ticketId}`);
                const data = await response.json();
                
                if (data.success) {
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
                const response = await fetch('admin/ajax/ajax_handlers.php?action=get_priorities');
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
                const response = await fetch('admin/ajax/ajax_handlers.php?action=get_assignable_users');
                const data = await response.json();
                
                if (data.success) {
                    this.assignableUsers = data.users;
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
                
                const response = await fetch('admin/ajax/ajax_handlers.php?action=update_ticket_status', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.currentTicket.status = newStatus;
                    await this.loadTicketHistory(this.currentTicket.id);
                    alert('Status updated successfully');
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
                
                const response = await fetch('admin/ajax/ajax_handlers.php?action=update_ticket_priority', {
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
                
                const response = await fetch('admin/ajax/ajax_handlers.php?action=assign_ticket', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Update the local assignee name from the assignable users array
                    const assignee = this.assignableUsers.find(u => u.id === assigneeId);
                    this.currentTicket.assigned_to = assigneeId;
                    this.currentTicket.assigned_to_name = assignee ? assignee.name : 'Unassigned';
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
        
        async addComment(isPrivate = false) {
            if (!this.currentTicket) return;
            
            const commentContent = this.$refs.commentEditor.innerHTML;
            
            if (!commentContent.trim()) {
                alert('Please enter a comment');
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ticket_id', this.currentTicket.id);
                formData.append('content', commentContent);
                formData.append('is_private', isPrivate ? 1 : 0);
                
                const response = await fetch('admin/ajax/ajax_handlers.php?action=add_comment', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Clear the editor
                    this.$refs.commentEditor.innerHTML = '';
                    // Clear any attachments
                    this.attachments = [];
                    // Reload ticket history
                    await this.loadTicketHistory(this.currentTicket.id);
                    alert('Comment added successfully');
                } else {
                    alert('Error adding comment: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to add comment:', error);
                alert('Failed to add comment. Please try again.');
            }
        },
        
        async archiveTicket() {
            if (!this.currentTicket) return;
            
            if (!confirm('Are you sure you want to archive this ticket?')) {
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('ticket_id', this.currentTicket.id);
                
                const response = await fetch('admin/ajax/ajax_handlers.php?action=archive_ticket', {
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
        
        // Rich text editor functions
        formatText(command) {
            switch (command) {
                case 'bold':
                    document.execCommand('bold', false, null);
                    break;
                case 'italic':
                    document.execCommand('italic', false, null);
                    break;
                case 'underline':
                    document.execCommand('underline', false, null);
                    break;
                case 'link':
                    const url = prompt('Enter the URL:');
                    if (url) {
                        document.execCommand('createLink', false, url);
                    }
                    break;
                case 'heading':
                    document.execCommand('formatBlock', false, '<h3>');
                    break;
                case 'list-ul':
                    document.execCommand('insertUnorderedList', false, null);
                    break;
                case 'list-ol':
                    document.execCommand('insertOrderedList', false, null);
                    break;
            }
            // Focus back on the editor
            this.$refs.commentEditor.focus();
        },
        
        async addAttachment() {
            // Create a file input element
            const fileInput = document.createElement('input');
            fileInput.type = 'file';
            fileInput.multiple = false;
            
            // Handle file selection
            fileInput.onchange = async (e) => {
                if (e.target.files.length === 0) return;
                
                const file = e.target.files[0];
                
                // Add to local attachments array for preview
                this.attachments.push({
                    name: file.name,
                    size: file.size,
                    file: file // Keep the file object for upload
                });
                
                // If we want to upload immediately:
                if (this.currentTicket) {
                    await this.uploadAttachment(file);
                }
            };
            
            // Trigger the file selection dialog
            fileInput.click();
        },
        
        async uploadAttachment(file) {
            try {
                const formData = new FormData();
                formData.append('attachment', file);
                formData.append('ticket_id', this.currentTicket.id);
                
                const response = await fetch('admin/ajax/ajax_handlers.php', {
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
        
        removeAttachment(index) {
            this.attachments.splice(index, 1);
        }
    };
}