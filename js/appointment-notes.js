/**
 * Appointment Notes JavaScript
 * Handles the CRUD operations for appointment notes in the admin interface
 */

// Function to load notes for a specific appointment
function loadAppointmentNotes(appointmentId) {
    const notesContainer = document.getElementById('appointment-notes-container');
    
    if (!notesContainer) {
        console.error('Notes container not found');
        return;
    }
    
    notesContainer.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    fetch(`api/appointment_notes.php?appointment_id=${appointmentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.data.length > 0) {
                    let notesHtml = '';
                    data.data.forEach(note => {
                        const formattedDate = new Date(note.created_at).toLocaleString();
                        notesHtml += `
                            <div class="note-item mb-3 p-3 border rounded" data-note-id="${note.id}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="note-text">${note.note_text}</div>
                                    <div class="note-actions">
                                        <button class="btn btn-sm btn-outline-primary edit-note-btn" data-note-id="${note.id}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-note-btn" data-note-id="${note.id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="text-muted small mt-2">
                                    <span>${note.admin_name || 'Admin'}</span> • <span>${formattedDate}</span>
                                </div>
                            </div>
                        `;
                    });
                    notesContainer.innerHTML = notesHtml;
                    
                    // Add event listeners for edit and delete buttons
                    document.querySelectorAll('.edit-note-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            const noteId = this.getAttribute('data-note-id');
                            const noteItem = document.querySelector(`.note-item[data-note-id="${noteId}"]`);
                            const noteText = noteItem.querySelector('.note-text').textContent;
                            
                            document.getElementById('edit-note-id').value = noteId;
                            document.getElementById('edit-note-text').value = noteText;
                            document.getElementById('edit-appointment-id').value = appointmentId;
                            
                            // Show edit modal
                            const editNoteModal = new bootstrap.Modal(document.getElementById('editNoteModal'));
                            editNoteModal.show();
                        });
                    });
                    
                    document.querySelectorAll('.delete-note-btn').forEach(btn => {
                        btn.addEventListener('click', function() {
                            if (confirm('Are you sure you want to delete this note?')) {
                                const noteId = this.getAttribute('data-note-id');
                                deleteNote(noteId, appointmentId);
                            }
                        });
                    });
                } else {
                    notesContainer.innerHTML = '<div class="alert alert-info">No notes found for this appointment.</div>';
                }
            } else {
                notesContainer.innerHTML = `<div class="alert alert-danger">${data.message || 'Error loading notes'}</div>`;
            }
        })
        .catch(error => {
            console.error('Error fetching notes:', error);
            notesContainer.innerHTML = '<div class="alert alert-danger">Error loading notes. Please try again later.</div>';
        });
}

// Function to add a new note
function addNote(appointmentId, noteText) {
    const formData = new FormData();
    formData.append('appointment_id', appointmentId);
    formData.append('note_text', noteText);
    
    fetch('api/appointment_notes.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload notes after adding
            loadAppointmentNotes(appointmentId);
            
            // Clear the form
            document.getElementById('new-note-text').value = '';
            
            // Show success message
            showToast('Note added successfully!', 'success');
        } else {
            showToast(data.message || 'Error adding note', 'error');
        }
    })
    .catch(error => {
        console.error('Error adding note:', error);
        showToast('Error adding note. Please try again.', 'error');
    });
}

// Function to update a note
function updateNote(noteId, appointmentId, noteText) {
    const formData = new FormData();
    formData.append('note_id', noteId);
    formData.append('note_text', noteText);
    
    fetch('api/appointment_notes.php', {
        method: 'PUT',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload notes after updating
            loadAppointmentNotes(appointmentId);
            
            // Hide the modal
            const editModal = bootstrap.Modal.getInstance(document.getElementById('editNoteModal'));
            if (editModal) {
                editModal.hide();
            }
            
            // Show success message
            showToast('Note updated successfully!', 'success');
        } else {
            showToast(data.message || 'Error updating note', 'error');
        }
    })
    .catch(error => {
        console.error('Error updating note:', error);
        showToast('Error updating note. Please try again.', 'error');
    });
}

// Function to delete a note
function deleteNote(noteId, appointmentId) {
    const formData = new FormData();
    formData.append('note_id', noteId);
    
    fetch('api/appointment_notes.php', {
        method: 'DELETE',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload notes after deleting
            loadAppointmentNotes(appointmentId);
            
            // Show success message
            showToast('Note deleted successfully!', 'success');
        } else {
            showToast(data.message || 'Error deleting note', 'error');
        }
    })
    .catch(error => {
        console.error('Error deleting note:', error);
        showToast('Error deleting note. Please try again.', 'error');
    });
}

// Helper function to show toast notifications
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        // Create toast container if it doesn't exist
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : 
                   type === 'error' ? 'bg-danger' : 
                   type === 'warning' ? 'bg-warning' : 'bg-info';
    
    const toastHtml = `
        <div id="${toastId}" class="toast ${bgClass} text-white" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">Notification</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    document.getElementById('toast-container').insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();
    
    // Remove toast from DOM after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        toastElement.remove();
    });
}

// Initialize event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add note form submission
    const addNoteForm = document.getElementById('add-note-form');
    if (addNoteForm) {
        addNoteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const appointmentId = document.getElementById('appointment-id').value;
            const noteText = document.getElementById('new-note-text').value.trim();
            
            if (!noteText) {
                showToast('Please enter a note', 'warning');
                return;
            }
            
            addNote(appointmentId, noteText);
        });
    }
    
    // Edit note form submission
    const editNoteForm = document.getElementById('edit-note-form');
    if (editNoteForm) {
        editNoteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const noteId = document.getElementById('edit-note-id').value;
            const appointmentId = document.getElementById('edit-appointment-id').value;
            const noteText = document.getElementById('edit-note-text').value.trim();
            
            if (!noteText) {
                showToast('Please enter a note', 'warning');
                return;
            }
            
            updateNote(noteId, appointmentId, noteText);
        });
    }
}); 