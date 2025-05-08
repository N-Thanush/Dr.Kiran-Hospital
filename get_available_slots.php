<?php
require_once 'connect.php';

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    // Get date from request
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception('Invalid date format. Please use YYYY-MM-DD format.');
    }
    
    // Get all slots for the date
    $query = "SELECT 
                appointment_time,
                DATE_FORMAT(appointment_time, '%l:%i %p') as formatted_time,
                is_booked,
                is_available
             FROM appointments 
             WHERE appointment_date = ? 
             ORDER BY appointment_time";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }
    
    $stmt->bind_param('s', $date);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $morning_slots = [];
    $evening_slots = [];
    
    // Debug logging
    error_log("Fetching slots for date: " . $date);
    
    while ($row = $result->fetch_assoc()) {
        $time = strtotime($row['appointment_time']);
        $slotInfo = [
            'time' => $row['appointment_time'],
            'formatted_time' => $row['formatted_time'],
            'is_booked' => $row['is_booked'],
            'is_available' => true  // All slots are considered available
        ];
        
        // Sort into morning and evening slots
        if ($time >= strtotime('10:00:00') && $time < strtotime('13:00:00')) {
            $morning_slots[] = $slotInfo;
        } else if ($time >= strtotime('18:00:00') && $time < strtotime('20:00:00')) {
            $evening_slots[] = $slotInfo;
        }
        
        // Debug log each slot
        error_log("Processing slot: " . json_encode($slotInfo));
    }
    
    // If no slots exist for this date, create them
    if (empty($morning_slots) && empty($evening_slots)) {
        // Create morning slots
        for ($time = strtotime('10:00:00'); $time < strtotime('13:00:00'); $time += 15 * 60) {
            $slotInfo = [
                'time' => date('H:i:s', $time),
                'formatted_time' => date('g:i A', $time),
                'is_booked' => false,
                'is_available' => true
            ];
            $morning_slots[] = $slotInfo;
        }
        
        // Create evening slots
        for ($time = strtotime('18:00:00'); $time < strtotime('20:00:00'); $time += 15 * 60) {
            $slotInfo = [
                'time' => date('H:i:s', $time),
                'formatted_time' => date('g:i A', $time),
                'is_booked' => false,
                'is_available' => true
            ];
            $evening_slots[] = $slotInfo;
        }
    }
    
    // Debug log counts
    error_log("Found " . count($morning_slots) . " morning slots");
    error_log("Found " . count($evening_slots) . " evening slots");
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'morning_slots' => $morning_slots,
        'evening_slots' => $evening_slots
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_available_slots.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch available slots: ' . $e->getMessage(),
        'morning_slots' => [],
        'evening_slots' => []
    ]);
}
?>