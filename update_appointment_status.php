<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once 'connect.php';

// Check if receptionist is logged in
if (!isset($_SESSION['receptionist_logged_in']) || $_SESSION['receptionist_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

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
    $logFile = 'logs/appointment_updates.log';
    $logDir = dirname($logFile);
    
    // Create log directory if it doesn't exist
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Function to log errors
function logError($message) {
    error_log("[" . date('Y-m-d H:i:s') . "] [update_appointment_status.php] " . $message);
}

// Validate input parameters
if (!isset($_POST['appointment_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$appointmentId = intval($_POST['appointment_id']);
$status = $_POST['status'];

// Validate status value
$validStatuses = ['pending', 'completed', 'cancelled'];
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Check if appointment exists and is not already in the requested status
    $query = "SELECT status FROM appointments WHERE id = ? FOR UPDATE";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Appointment not found");
    }
    
    $currentStatus = $result->fetch_assoc()['status'];
    if ($currentStatus === $status) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Appointment is already in the requested status']);
        exit;
    }
    
    // Update appointment status
    $updateQuery = "UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("si", $status, $appointmentId);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating appointment status: " . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Appointment status updated successfully',
        'appointment_id' => $appointmentId,
        'new_status' => $status
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    logError($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating appointment status']);
    
} finally {
    $stmt->close();
    $conn->close();
}
?> 