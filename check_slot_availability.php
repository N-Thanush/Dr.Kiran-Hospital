<?php
// Set secure session settings
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json; charset=utf-8');

// Disable error display in output
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Required database connection
require 'connect.php';

/**
 * Sanitize input data
 */
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

/**
 * Send JSON response
 */
function sendResponse($success, $message = '', $data = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'available' => $success,
        'data' => $data
    ];
    
    echo json_encode($response);
    exit;
}

// Ensure this is an AJAX request
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    sendResponse(false, 'Invalid request method');
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Get and sanitize input
$date = isset($_POST['date']) ? test_input($_POST['date']) : '';
$time = isset($_POST['time']) ? test_input($_POST['time']) : '';

// Validate input
if (empty($date) || empty($time)) {
    sendResponse(false, 'Date and time are required');
}

try {
    // Log request for debugging
    error_log("Checking slot availability via AJAX: Date: {$date}, Time: {$time}");
    
    // Check if the slot is still available with more flexible time matching
    $query = "SELECT * FROM appointments 
              WHERE appointment_date = ? 
              AND (appointment_time = STR_TO_DATE(?, '%h:%i %p') OR TIME_FORMAT(appointment_time, '%h:%i %p') = ?)
              AND is_available = 1 
              AND is_booked = 0";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Database prepare error: " . $conn->error);
        sendResponse(false, 'Database error');
    }
    
    $stmt->bind_param("sss", $date, $time, $time);
    
    if (!$stmt->execute()) {
        error_log("Query execution error: " . $stmt->error);
        sendResponse(false, 'Error checking slot availability');
    }
    
    $result = $stmt->get_result();
    
    // Log result for debugging
    error_log("AJAX slot check result: " . $result->num_rows . " rows found");
    
    // Check if slot exists and is available
    if ($result->num_rows === 0) {
        error_log("Slot not available: Date: {$date}, Time: {$time}");
        sendResponse(false, 'This slot is no longer available');
    }
    
    // Check if slot is in the past
    $slotDateTime = new DateTime($date . ' ' . $time);
    $now = new DateTime();
    
    if ($slotDateTime < $now) {
        sendResponse(false, 'Cannot book appointments in the past');
    }
    
    // Slot is available
    sendResponse(true, 'Slot is available');
    
} catch (Exception $e) {
    error_log("Error in check_slot_availability.php: " . $e->getMessage());
    sendResponse(false, 'An error occurred while checking slot availability');
} 