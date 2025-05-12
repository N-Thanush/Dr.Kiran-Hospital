<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once 'connect.php';

// Helper function for API responses
function jsonResponse($success, $message, $data = []) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Log function for recording operations
function logAction($message) {
    $logFile = 'logs/appointment_notes.log';
    $logDir = dirname($logFile);
    
    // Create log directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Verify if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method. Only POST requests are accepted.');
}

// Check if required parameters are present
if (!isset($_POST['appointment_id']) || !isset($_POST['notes'])) {
    jsonResponse(false, 'Missing required parameters: appointment_id and notes.');
}

// Get parameters
$appointmentId = $_POST['appointment_id'];
$notes = $_POST['notes'];

// Validate appointment ID
if (!is_numeric($appointmentId) || $appointmentId <= 0) {
    jsonResponse(false, 'Invalid appointment ID.');
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // First, check if the appointment exists
    $checkQuery = "SELECT id FROM appointments WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    
    if (!$checkStmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $checkStmt->bind_param('i', $appointmentId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Appointment not found with ID: $appointmentId");
    }
    
    // Check if notes already exist for this appointment
    $checkNotesQuery = "SELECT id FROM appointment_notes WHERE appointment_id = ?";
    $checkNotesStmt = $conn->prepare($checkNotesQuery);
    
    if (!$checkNotesStmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $checkNotesStmt->bind_param('i', $appointmentId);
    $checkNotesStmt->execute();
    $notesResult = $checkNotesStmt->get_result();
    
    if ($notesResult->num_rows > 0) {
        // Update existing notes
        $noteRow = $notesResult->fetch_assoc();
        $updateQuery = "UPDATE appointment_notes SET notes = ?, updated_at = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        
        if (!$updateStmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $updateStmt->bind_param('si', $notes, $noteRow['id']);
        $updateStmt->execute();
        
        if ($updateStmt->affected_rows === 0) {
            throw new Exception("Failed to update notes. No changes were made.");
        }
        
        $action = "updated";
    } else {
        // Insert new notes
        $insertQuery = "INSERT INTO appointment_notes (appointment_id, notes, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
        $insertStmt = $conn->prepare($insertQuery);
        
        if (!$insertStmt) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }
        
        $insertStmt->bind_param('is', $appointmentId, $notes);
        $insertStmt->execute();
        
        if ($insertStmt->affected_rows === 0) {
            throw new Exception("Failed to insert notes. No changes were made.");
        }
        
        $action = "added";
    }
    
    // Commit the transaction
    $conn->commit();
    
    // Log the action
    logAction("Notes $action for appointment #$appointmentId");
    
    // Return success response
    jsonResponse(true, "Notes successfully $action for appointment #$appointmentId", [
        'appointment_id' => $appointmentId
    ]);
    
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    
    // Log the error
    error_log("Error saving appointment notes: " . $e->getMessage());
    logAction("ERROR: Failed to save notes for appointment #$appointmentId: " . $e->getMessage());
    
    // Return error response
    jsonResponse(false, "Error saving appointment notes: " . $e->getMessage());
}
?> 