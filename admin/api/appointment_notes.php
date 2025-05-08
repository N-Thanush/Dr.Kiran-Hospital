<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Include database connection
require_once '../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Log error function
function logError($message) {
    $logFile = __DIR__ . '/../../logs/api_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    
    error_log($logMessage, 3, $logFile);
}

// Response function
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => $success, 'message' => $message];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit;
}

// Validate appointment ID
if (!isset($_REQUEST['appointment_id']) || !is_numeric($_REQUEST['appointment_id'])) {
    jsonResponse(false, 'Invalid appointment ID');
}

$appointmentId = intval($_REQUEST['appointment_id']);

// Check if appointment exists
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    jsonResponse(false, 'Appointment not found');
}
$stmt->close();

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Get notes for an appointment
        $stmt = $conn->prepare("
            SELECT n.id, n.note_text, n.created_at, n.updated_at, a.name AS admin_name
            FROM appointment_notes n
            LEFT JOIN admins a ON n.admin_id = a.id
            WHERE n.appointment_id = ?
            ORDER BY n.created_at DESC
        ");
        $stmt->bind_param("i", $appointmentId);
        
        if (!$stmt->execute()) {
            logError("Error fetching notes: " . $stmt->error);
            jsonResponse(false, 'Error fetching notes');
        }
        
        $result = $stmt->get_result();
        $notes = [];
        
        while ($row = $result->fetch_assoc()) {
            $notes[] = [
                'id' => $row['id'],
                'noteText' => $row['note_text'],
                'createdAt' => $row['created_at'],
                'updatedAt' => $row['updated_at'],
                'adminName' => $row['admin_name']
            ];
        }
        
        $stmt->close();
        jsonResponse(true, 'Notes retrieved successfully', $notes);
        break;
        
    case 'POST':
        // Add a new note
        $noteText = $_POST['note_text'] ?? '';
        
        if (empty($noteText)) {
            jsonResponse(false, 'Note text cannot be empty');
        }
        
        $adminId = $_SESSION['admin_id'];
        $currentTime = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("
            INSERT INTO appointment_notes (appointment_id, admin_id, note_text, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $appointmentId, $adminId, $noteText, $currentTime, $currentTime);
        
        if (!$stmt->execute()) {
            logError("Error adding note: " . $stmt->error);
            jsonResponse(false, 'Error adding note');
        }
        
        $noteId = $stmt->insert_id;
        $stmt->close();
        
        // Get admin name for the response
        $stmt = $conn->prepare("SELECT name FROM admins WHERE id = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        $adminName = $result->fetch_assoc()['name'];
        $stmt->close();
        
        $newNote = [
            'id' => $noteId,
            'noteText' => $noteText,
            'createdAt' => $currentTime,
            'updatedAt' => $currentTime,
            'adminName' => $adminName
        ];
        
        jsonResponse(true, 'Note added successfully', $newNote);
        break;
        
    case 'PUT':
        // Update an existing note
        parse_str(file_get_contents("php://input"), $putData);
        
        $noteId = $putData['note_id'] ?? 0;
        $noteText = $putData['note_text'] ?? '';
        
        if (empty($noteId) || !is_numeric($noteId)) {
            jsonResponse(false, 'Invalid note ID');
        }
        
        if (empty($noteText)) {
            jsonResponse(false, 'Note text cannot be empty');
        }
        
        $adminId = $_SESSION['admin_id'];
        $currentTime = date('Y-m-d H:i:s');
        
        // Check if note exists and admin has permission
        $stmt = $conn->prepare("SELECT admin_id FROM appointment_notes WHERE id = ? AND appointment_id = ?");
        $stmt->bind_param("ii", $noteId, $appointmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            jsonResponse(false, 'Note not found');
        }
        
        $noteData = $result->fetch_assoc();
        
        // Only allow the note creator or super admin to update the note
        if ($noteData['admin_id'] != $adminId && $_SESSION['admin_role'] !== 'super_admin') {
            jsonResponse(false, 'You do not have permission to update this note');
        }
        
        $stmt->close();
        
        // Update the note
        $stmt = $conn->prepare("UPDATE appointment_notes SET note_text = ?, updated_at = ? WHERE id = ?");
        $stmt->bind_param("ssi", $noteText, $currentTime, $noteId);
        
        if (!$stmt->execute()) {
            logError("Error updating note: " . $stmt->error);
            jsonResponse(false, 'Error updating note');
        }
        
        $stmt->close();
        
        jsonResponse(true, 'Note updated successfully', [
            'id' => $noteId,
            'noteText' => $noteText,
            'updatedAt' => $currentTime
        ]);
        break;
        
    case 'DELETE':
        // Delete a note
        parse_str(file_get_contents("php://input"), $deleteData);
        
        $noteId = $deleteData['note_id'] ?? 0;
        
        if (empty($noteId) || !is_numeric($noteId)) {
            jsonResponse(false, 'Invalid note ID');
        }
        
        $adminId = $_SESSION['admin_id'];
        
        // Check if note exists and admin has permission
        $stmt = $conn->prepare("SELECT admin_id FROM appointment_notes WHERE id = ? AND appointment_id = ?");
        $stmt->bind_param("ii", $noteId, $appointmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            jsonResponse(false, 'Note not found');
        }
        
        $noteData = $result->fetch_assoc();
        
        // Only allow the note creator or super admin to delete the note
        if ($noteData['admin_id'] != $adminId && $_SESSION['admin_role'] !== 'super_admin') {
            jsonResponse(false, 'You do not have permission to delete this note');
        }
        
        $stmt->close();
        
        // Delete the note
        $stmt = $conn->prepare("DELETE FROM appointment_notes WHERE id = ?");
        $stmt->bind_param("i", $noteId);
        
        if (!$stmt->execute()) {
            logError("Error deleting note: " . $stmt->error);
            jsonResponse(false, 'Error deleting note');
        }
        
        $stmt->close();
        
        jsonResponse(true, 'Note deleted successfully');
        break;
        
    default:
        jsonResponse(false, 'Method not allowed');
}
?> 