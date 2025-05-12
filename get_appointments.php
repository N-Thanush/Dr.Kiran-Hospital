<?php
session_start();
require_once 'connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Get date range from request with validation
    $start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d');
    $end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d');
    
    // Validate date formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}/', $end)) {
        throw new Exception('Invalid date format. Please use YYYY-MM-DD format.');
    }
    
    // Ensure end date is not before start date
    if (strtotime($end) < strtotime($start)) {
        $end = $start; // Default to single day if range is invalid
    }
    
    // Log the request for debugging
    error_log("Fetching appointments from $start to $end");
    
    // Get the request type
    $requestType = isset($_GET['type']) ? $_GET['type'] : 'all';
    
    // Build the query based on request type
    $query = "SELECT a.id, a.*, 
              COALESCE(p.first_name, a.patient_first_name) as patient_first_name, 
              COALESCE(p.last_name, a.patient_last_name) as patient_last_name, 
              COALESCE(p.contact_number, a.contact_number) as contact_number, 
              COALESCE(p.email, a.email) as email,
              a.reason_for_appointment, 
              a.status, 
              a.is_available, 
              a.is_booked,
              a.notes
              FROM appointments a 
              LEFT JOIN patients p ON a.patient_id = p.id 
              WHERE a.appointment_date BETWEEN ? AND ?";
    
    // Add filters based on request type
    switch ($requestType) {
        case 'available':
            $query .= " AND a.is_available = TRUE AND a.is_booked = FALSE";
            break;
        case 'booked':
            $query .= " AND a.is_booked = TRUE";
            break;
        case 'slots':
            $query .= " AND a.is_available = TRUE";
            break;
        // Default is 'all', no additional filter
    }
    
    $query .= " ORDER BY a.appointment_date, a.appointment_time";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('ss', $start, $end);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    // Log the number of appointments found
    error_log("Found " . count($appointments) . " appointments between $start and $end for type '$requestType'");
    
    echo json_encode([
        'success' => true,
        'appointments' => $appointments,
        'start_date' => $start,
        'end_date' => $end,
        'type' => $requestType,
        'count' => count($appointments)
    ]);
    
} catch (Exception $e) {
    // Log the error and return an error response
    error_log('Error in get_appointments.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'appointments' => []
    ]);
}
?> 