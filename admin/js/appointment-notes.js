/**
 * Appointment Notes Management
 * Handles CRUD operations for appointment notes
 */

// Global variables
let currentAppointmentId = null;

/**
 * Initialize the notes panel for a specific appointment
 * @param {number} appointmentId - The ID of the appointment
 */
function initAppointmentNotes(appointmentId) {
    currentAppointmentId = appointmentId;
    fetchAppointmentNotes(appointmentId);
    
    // Clear the new note textarea
    document.getElementById('newNoteText').value = '';
    
    // Set up the add note button
    document.getElementById('addNoteBtn').onclick = () => {
        const noteText = document.getElementById('newNoteText').value.trim();
        if (noteText) {
            addAppointmentNote(appointmentId, noteText);
        } else {
            showAlert('Please enter a note before submitting', 'warning');
        }
    };
}

/**
 * Fetch notes for a specific appointment
 * @param {number} appointmentId - The ID of the appointment
 */
function fetchAppointmentNotes(appointmentId) {
    fetch(`../api/appointment_notes.php?appointment_id=${appointmentId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            displayAppointmentNotes(data.notes);
        } else {
            console.error('Error fetching notes:', data.message);
            showAlert('Error loading notes: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to load notes. Please try again.', 'danger');
    });
}

/**
 * Display the notes in the notes container
 * @param {Array} notes - Array of note objects
 */
function displayAppointmentNotes(notes) {
    const notesContainer = document.getElementById('appointmentNotesContainer');
    notesContainer.innerHTML = '';
    
    if (notes.length === 0) {
        notesContainer.innerHTML = '<div class="no-notes">No notes for this appointment yet.</div>';
        return;
    }
    
    notes.forEach(note => {
        const noteElement = document.createElement('div');
        noteElement.className = 'note-item';
        noteElement.innerHTML = `
            <div class="note-header">
                <span class="note-date">${formatDateTime(note.created_at)}</span>
                <div class="note-actions">
                    <button class="btn btn-sm btn-outline-primary edit-note-btn" data-note-id="${note.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger delete-note-btn" data-note-id="${note.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="note-content" id="note-content-${note.id}">${note.note_text}</div>
            <div class="note-edit-form d-none" id="note-edit-form-${note.id}">
                <textarea class="form-control note-edit-textarea" id="note-edit-textarea-${note.id}">${note.note_text}</textarea>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary save-edit-btn" data-note-id="${note.id}">Save</button>
                    <button class="btn btn-sm btn-secondary cancel-edit-btn" data-note-id="${note.id}">Cancel</button>
                </div>
            </div>
            <div class="note-footer">
                <small>By: ${note.admin_name || 'Admin'}</small>
                ${note.updated_at !== note.created_at ? `<small class="text-muted ml-2">(Edited: ${formatDateTime(note.updated_at)})</small>` : ''}
            </div>
        `;
        notesContainer.appendChild(noteElement);
        
        // Add event listeners for edit and delete buttons
        noteElement.querySelector(`.edit-note-btn[data-note-id="${note.id}"]`).addEventListener('click', () => {
            toggleEditMode(note.id, true);
        });
        
        noteElement.querySelector(`.delete-note-btn[data-note-id="${note.id}"]`).addEventListener('click', () => {
            confirmDeleteNote(note.id);
        });
        
        noteElement.querySelector(`.save-edit-btn[data-note-id="${note.id}"]`).addEventListener('click', () => {
            const updatedText = document.getElementById(`note-edit-textarea-${note.id}`).value.trim();
            if (updatedText) {
                updateAppointmentNote(note.id, updatedText);
            } else {
                showAlert('Note cannot be empty', 'warning');
            }
        });
        
        noteElement.querySelector(`.cancel-edit-btn[data-note-id="${note.id}"]`).addEventListener('click', () => {
            toggleEditMode(note.id, false);
        });
    });
}

/**
 * Toggle edit mode for a note
 * @param {number} noteId - The ID of the note
 * @param {boolean} isEdit - Whether to enter edit mode
 */
function toggleEditMode(noteId, isEdit) {
    const contentElement = document.getElementById(`note-content-${noteId}`);
    const formElement = document.getElementById(`note-edit-form-${noteId}`);
    
    if (isEdit) {
        contentElement.classList.add('d-none');
        formElement.classList.remove('d-none');
    } else {
        contentElement.classList.remove('d-none');
        formElement.classList.add('d-none');
    }
}

/**
 * Add a new note to an appointment
 * @param {number} appointmentId - The ID of the appointment
 * @param {string} noteText - The text of the note
 */
function addAppointmentNote(appointmentId, noteText) {
    fetch('../api/appointment_notes.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            appointment_id: appointmentId,
            note_text: noteText
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            document.getElementById('newNoteText').value = '';
            showAlert('Note added successfully', 'success');
            fetchAppointmentNotes(appointmentId);
        } else {
            console.error('Error adding note:', data.message);
            showAlert('Error adding note: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to add note. Please try again.', 'danger');
    });
}

/**
 * Update an existing note
 * @param {number} noteId - The ID of the note
 * @param {string} noteText - The updated text
 */
function updateAppointmentNote(noteId, noteText) {
    fetch('../api/appointment_notes.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            note_id: noteId,
            note_text: noteText
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            toggleEditMode(noteId, false);
            showAlert('Note updated successfully', 'success');
            fetchAppointmentNotes(currentAppointmentId);
        } else {
            console.error('Error updating note:', data.message);
            showAlert('Error updating note: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to update note. Please try again.', 'danger');
    });
}

/**
 * Show confirmation dialog for deleting a note
 * @param {number} noteId - The ID of the note to delete
 */
function confirmDeleteNote(noteId) {
    if (confirm('Are you sure you want to delete this note? This action cannot be undone.')) {
        deleteAppointmentNote(noteId);
    }
}

/**
 * Delete a note
 * @param {number} noteId - The ID of the note
 */
function deleteAppointmentNote(noteId) {
    fetch(`../api/appointment_notes.php?note_id=${noteId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showAlert('Note deleted successfully', 'success');
            fetchAppointmentNotes(currentAppointmentId);
        } else {
            console.error('Error deleting note:', data.message);
            showAlert('Error deleting note: ' + data.message, 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('Failed to delete note. Please try again.', 'danger');
    });
}

/**
 * Format date and time for display
 * @param {string} dateTimeStr - The date/time string from the server
 * @return {string} Formatted date/time string
 */
function formatDateTime(dateTimeStr) {
    const date = new Date(dateTimeStr);
    return date.toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Show an alert message
 * @param {string} message - The message to display
 * @param {string} type - The type of alert (success, danger, warning, info)
 */
function showAlert(message, type = 'info') {
    const alertElement = document.createElement('div');
    alertElement.className = `alert alert-${type} alert-dismissible fade show`;
    alertElement.role = 'alert';
    alertElement.innerHTML = `
        ${message}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    `;
    
    const alertContainer = document.getElementById('alertContainer');
    alertContainer.appendChild(alertElement);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertElement.classList.remove('show');
        setTimeout(() => alertElement.remove(), 150);
    }, 5000);
} 