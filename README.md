# Dr. Kiran Hospital - Appointment Management System

## Appointment Notes Feature

The Appointment Notes feature allows administrators to add, edit, and delete notes for patient appointments. This feature is useful for keeping track of patient interactions, special requests, and other important information related to appointments.

### Features

1. **View Notes**: See all notes associated with an appointment in chronological order.
2. **Add Notes**: Add new notes to an appointment with a simple form.
3. **Edit Notes**: Modify existing notes to update information.
4. **Delete Notes**: Remove notes that are no longer relevant.
5. **Admin Attribution**: Each note shows which admin created it and when.

### Setup Instructions

1. **Database Setup**:
   - Run the SQL script in `sql/appointment_notes.sql` to create the necessary database table.
   - This script will create the `appointment_notes` table with appropriate indexes and foreign key constraints.

2. **API Endpoint**:
   - The API endpoint for appointment notes is located at `api/appointment_notes.php`.
   - This API supports GET, POST, PUT, and DELETE methods for full CRUD operations.

3. **Admin Interface**:
   - Notes functionality is integrated in the appointment modal in the admin calendar.
   - CSS and JS files for appointment notes are located in `admin/css/appointment-notes.css` and `admin/js/appointment-notes.js`.

### Usage

1. **Viewing Notes**:
   - Open an appointment in the admin calendar to see associated notes.
   - Notes are displayed in reverse chronological order (newest first).

2. **Adding Notes**:
   - While viewing an appointment, use the "Add New Note" form at the bottom of the notes section.
   - Enter your note text and click the "Add Note" button.

3. **Editing Notes**:
   - Click the edit button (pencil icon) next to any note.
   - Modify the text in the textarea that appears and click "Save".

4. **Deleting Notes**:
   - Click the delete button (trash icon) next to any note.
   - Confirm the deletion when prompted.

### Security

- Only authenticated administrators can access and modify appointment notes.
- All API endpoints check for valid admin authentication.
- All database interactions use prepared statements to prevent SQL injection.

### Troubleshooting

If you encounter issues with the appointment notes feature:

1. Check that the `appointment_notes` table exists in your database.
2. Verify that the admin has proper session permissions.
3. Check the PHP error logs for any API errors.
4. Ensure JavaScript is enabled in your browser.

### File Structure

```
/api
  /appointment_notes.php  - API endpoint for notes CRUD operations
/admin
  /css
    /appointment-notes.css - Styling for appointment notes
  /js
    /appointment-notes.js  - JavaScript for appointment notes functionality
/sql
  /appointment_notes.sql   - SQL script to create database table
```

## Database Optimization

The database structure has been optimized and consolidated for better performance and management. All necessary tables are now defined in a single SQL file for easy setup and maintenance.

### Important Files

- `dr_kiran_hospital_db.sql` - Consolidated database setup file with all tables and columns
- `fix_database.php` - Tool to check and fix database structure
- `cleanup.php` - Utility to remove redundant files after consolidation

### Setup Instructions

1. Make sure your database server (MySQL/MariaDB) is running
2. Navigate to `fix_database.php` in your browser
3. Follow the instructions to check and fix your database
4. Once the database is set up correctly, you can safely run `cleanup.php` to remove redundant files

## Features

- Calendar-based appointment booking with time slots
- Admin panel for managing appointments and slots
- Appointment notes for tracking patient interactions
- Responsive design for mobile and desktop
- Email notifications for appointment confirmations

## Tables

The system uses the following main tables:

1. `appointments` - Stores all appointment information and available slots
   - Contains columns for patient details, date/time, and booking status
   - Uses `is_booked` and `is_available` flags for slot management

2. `admin_users` - Stores admin authentication details
   - Secure password storage with hashing

3. `appointment_notes` - Stores notes related to appointments
   - Linked to appointments via foreign keys
   - Tracks creation and update times

4. `time_slots_config` - Stores configuration for time slots
   - Defines morning and evening time ranges
   - Configures slot intervals

5. `settings` - Stores application configuration
   - General hospital information
   - Booking rules and restrictions

## Optimizations

The following optimizations have been made:

1. Consolidated multiple SQL files into a single setup file
2. Added proper indexes to improve query performance
3. Standardized column naming conventions
4. Added foreign key constraints for data integrity
5. Removed redundant files and duplicate code

## Troubleshooting

If you encounter database issues:

1. Run `fix_database.php` to diagnose and fix common issues
2. Check that all required columns exist in the tables
3. Ensure the database user has proper permissions
4. Review error logs for specific issues