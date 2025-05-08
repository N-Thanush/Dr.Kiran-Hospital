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

// Function to generate CSV file
function generateCSV($data, $filename) {
    // Set headers for file download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fputs($output, "\xEF\xBB\xBF");
    
    // Add CSV headers
    fputcsv($output, array_keys($data[0]));
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    // Close the output stream
    fclose($output);
    exit;
}

// Check if this is an AJAX request or direct download request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Get parameters
$format = isset($_GET['format']) ? $_GET['format'] : 'csv'; // Default to CSV
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    if ($isAjax) {
        jsonResponse(false, 'Invalid date format. Use YYYY-MM-DD format.');
    } else {
        die('Invalid date format. Use YYYY-MM-DD format.');
    }
}

try {
    // Build the SQL query based on filters
    $query = "SELECT a.id, a.appointment_date, a.appointment_time, 
                     a.patient_first_name, a.patient_last_name, a.patient_phone, 
                     a.patient_email, a.status, a.notes, a.created_at
              FROM appointments a
              WHERE a.is_booked = 1 
              AND a.appointment_date BETWEEN ? AND ?";
    
    $params = [$startDate, $endDate];
    $types = 'ss';
    
    // Add status filter if not 'all'
    if ($status !== 'all') {
        $query .= " AND a.status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    // Order by date and time
    $query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
    
    // Prepare and execute statement
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all appointments
    $appointments = [];
    
    while ($row = $result->fetch_assoc()) {
        // Format dates and times for display
        $formattedDate = date('Y-m-d', strtotime($row['appointment_date']));
        $formattedTime = date('h:i A', strtotime($row['appointment_time']));
        
        $appointments[] = [
            'ID' => $row['id'],
            'Date' => $formattedDate,
            'Time' => $formattedTime,
            'Patient Name' => $row['patient_first_name'] . ' ' . $row['patient_last_name'],
            'Phone' => $row['patient_phone'],
            'Email' => $row['patient_email'],
            'Status' => ucfirst($row['status']),
            'Notes' => $row['notes'],
            'Created' => $row['created_at']
        ];
    }
    
    // Check if we have appointments
    if (empty($appointments)) {
        if ($isAjax) {
            jsonResponse(false, 'No appointments found for the selected date range.');
        } else {
            die('No appointments found for the selected date range.');
        }
    }
    
    // Generate filename
    $filename = 'appointments_' . $startDate . '_to_' . $endDate . '.' . $format;
    
    // Export based on format
    if ($format === 'csv') {
        generateCSV($appointments, $filename);
    } else {
        // If unsupported format is requested, default to CSV
        generateCSV($appointments, str_replace('.' . $format, '.csv', $filename));
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Error exporting appointments: " . $e->getMessage());
    
    if ($isAjax) {
        jsonResponse(false, "Error exporting appointments: " . $e->getMessage());
    } else {
        die("Error exporting appointments: " . $e->getMessage());
    }
}
?> 