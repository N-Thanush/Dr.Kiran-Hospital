<?php
session_start();
require_once 'connect.php';

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['date']) || !isset($_POST['time'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$date = $_POST['date'];
$time = $_POST['time'];

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Find the booking
    $query = "SELECT id FROM appointments 
              WHERE appointment_date = ? 
              AND TIME_FORMAT(appointment_time, '%l:%i %p') = ?
              FOR UPDATE";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Booking not found');
    }
    
    $booking = $result->fetch_assoc();
    
    // Reset the booking
    $updateQuery = "UPDATE appointments 
                   SET is_booked = 0,
                       is_available = 1,
                       status = 'confirmed'
                   WHERE id = ?";
                   
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $booking['id']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete booking');
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 