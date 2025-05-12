<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once 'connect.php';

// Function to send JSON response
function sendJsonResponse($success, $message = '', $errors = []) {
header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'errors' => $errors
    ]);
    exit;
}

// Function to validate input
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Invalid request method');
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Validate and sanitize inputs
    $firstName = validateInput($_POST['first_name']);
    $lastName = validateInput($_POST['last_name']);
    $phone = validateInput($_POST['phone']);
    $reason = validateInput($_POST['reason']);
    $appointmentDate = validateInput($_POST['appointmentDate']);
    $appointmentTime = validateInput($_POST['appointmentTime']);

    // Validate required fields
    $errors = [];
    if (empty($firstName)) $errors['first_name'] = 'First name is required';
    if (empty($lastName)) $errors['last_name'] = 'Last name is required';
    if (empty($phone)) $errors['phone'] = 'Phone number is required';
    if (empty($reason)) $errors['reason'] = 'Reason for appointment is required';
    if (empty($appointmentDate)) $errors['appointmentDate'] = 'Appointment date is required';
    if (empty($appointmentTime)) $errors['appointmentTime'] = 'Appointment time is required';

    if (!empty($errors)) {
        sendJsonResponse(false, 'Please correct the errors', $errors);
    }

    // Verify the slot is still available
    $checkQuery = "SELECT * FROM appointments 
                   WHERE appointment_date = ? 
                   AND appointment_time = ?
                  AND is_available = TRUE 
                  AND is_booked = FALSE
                   FOR UPDATE";
    
    $checkStmt = $conn->prepare($checkQuery);
    if (!$checkStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $checkStmt->bind_param("ss", $appointmentDate, $appointmentTime);
    if (!$checkStmt->execute()) {
        throw new Exception("Database execute error: " . $checkStmt->error);
    }

    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows === 0) {
        $conn->rollback();
        sendJsonResponse(false, "This slot is no longer available. Please select another time.");
    }

    // Update appointment
    $updateQuery = "UPDATE appointments 
                   SET is_booked = TRUE,
                       is_available = FALSE,
                       patient_name = ?,
                       patient_phone = ?,
                       reason_for_visit = ?,
                       status = 'confirmed',
                       booked_at = NOW()
                   WHERE appointment_date = ? 
                   AND appointment_time = ?
                   AND is_available = TRUE 
                   AND is_booked = FALSE";
    
    $updateStmt = $conn->prepare($updateQuery);
    if (!$updateStmt) {
        throw new Exception("Database prepare error: " . $conn->error);
    }
    
    $fullName = $firstName . ' ' . $lastName;
    $updateStmt->bind_param("sssss", 
        $fullName,
        $phone,
        $reason,
        $appointmentDate,
        $appointmentTime
    );

    if (!$updateStmt->execute()) {
        throw new Exception("Database execute error: " . $updateStmt->error);
    }

    if ($updateStmt->affected_rows === 0) {
        throw new Exception("Failed to update appointment. Please try again.");
    }

    // Commit transaction
    $conn->commit();
    
    // Store appointment details in session for success page
    $_SESSION['appointment_success'] = [
        'patient_name' => $fullName,
        'appointment_date' => $appointmentDate,
        'appointment_time' => $appointmentTime,
        'phone' => $phone
    ];
        
    // Send success response
    sendJsonResponse(true, "Appointment booked successfully!");
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn) {
    $conn->rollback();
    }
    
    error_log("Error in process_appointment.php: " . $e->getMessage());
    sendJsonResponse(false, "An error occurred while booking your appointment: " . $e->getMessage());
}
?> 