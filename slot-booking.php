<?php
session_start();
require 'connect.php';
// Initialize variables
$today = new DateTime('now');
$todayFormatted = $today->format('Y-m-d');

// Clear any old appointment data when loading the booking page
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['appointment_date']);
    unset($_SESSION['appointment_time']);
}

// Fetch booked and available slots from database with error handling
$bookedSlots = [];
$availableSlots = [];
try {
    // Modified query to fetch all slots with proper time formatting
    $query = "SELECT 
                DATE_FORMAT(appointment_date, '%Y-%m-%d') as date,
                TIME_FORMAT(appointment_time, '%l:%i %p') as formatted_time,
                is_booked,
                is_available
             FROM appointments 
             WHERE appointment_date >= CURDATE()
             ORDER BY appointment_date, appointment_time";
             
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query error: " . $conn->error);
    }
    
    while ($row = $result->fetch_assoc()) {
        // Ensure time format is consistent by standardizing
        $formattedTime = standardizeTimeFormat($row['formatted_time']);
        $dateTimeKey = $row['date'] . ' ' . $formattedTime;
        
        // Debug log each slot
        error_log(sprintf(
            "Processing slot: %s (Booked: %d, Available: %d)",
            $dateTimeKey,
            $row['is_booked'],
            $row['is_available']
        ));
        
        // A slot is booked if is_booked = 1
        if ($row['is_booked'] == 1) {
            $bookedSlots[] = $dateTimeKey;
        }
        
        // A slot is available if is_available = 1 AND is_booked = 0
        if ($row['is_available'] == 1 && $row['is_booked'] == 0) {
            $availableSlots[] = $dateTimeKey;
        }
    }
    
    // Debug log the final arrays
    error_log("Total booked slots: " . count($bookedSlots));
    error_log("Total available slots: " . count($availableSlots));
    
} catch (Exception $e) {
    error_log("Error fetching slots: " . $e->getMessage());
    $bookedSlots = [];
    $availableSlots = [];
}

// Function to log slot status
function logSlotStatus($conn, $date, $time) {
    $query = "SELECT id, appointment_date, appointment_time, 
              TIME_FORMAT(appointment_time, '%l:%i %p') as formatted_time_12,
              TIME_FORMAT(appointment_time, '%H:%i:%s') as formatted_time_24,
              is_available, is_booked
              FROM appointments 
              WHERE appointment_date = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    error_log("=== Slot Status Check ===");
    error_log("Checking for date: $date, time: $time");
    
    while ($row = $result->fetch_assoc()) {
        error_log(sprintf(
            "ID: %d, Date: %s, Raw Time: %s, 12h: %s, 24h: %s, Available: %d, Booked: %d",
            $row['id'],
            $row['appointment_date'],
            $row['appointment_time'],
            $row['formatted_time_12'],
            $row['formatted_time_24'],
            $row['is_available'],
            $row['is_booked']
        ));
    }
    error_log("=== End Slot Status ===");
}

// Process form submission if applicable
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['selected_date']) && isset($_POST['selected_time'])) {
        $selectedDate = trim($_POST['selected_date']);
        $selectedTime = standardizeTimeFormat(trim($_POST['selected_time']));
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Log the exact input values
            error_log("=== Booking Attempt ===");
            error_log("Raw POST date: " . $_POST['selected_date']);
            error_log("Raw POST time: " . $_POST['selected_time']);
            error_log("Standardized date: " . $selectedDate);
            error_log("Standardized time: " . $selectedTime);
            
            // Log current slot status
            logSlotStatus($conn, $selectedDate, $selectedTime);
            
            // Check if the slot exists and is available with FOR UPDATE lock
            $checkQuery = "SELECT id, is_available, is_booked, 
                          TIME_FORMAT(appointment_time, '%l:%i %p') as formatted_time,
                          appointment_time
                          FROM appointments 
                          WHERE appointment_date = ? 
                          AND (
                              TIME_FORMAT(appointment_time, '%l:%i %p') = ? 
                              OR TIME_FORMAT(appointment_time, '%h:%i %p') = ?
                              OR TIME_FORMAT(appointment_time, '%g:%i %p') = ?
                          )
                          FOR UPDATE";
            
            $checkStmt = $conn->prepare($checkQuery);
            if (!$checkStmt) {
                throw new Exception("Database prepare error: " . $conn->error);
            }
            
            // Standardize the time format for comparison
            $formattedTime = date('g:i A', strtotime($selectedTime));
            error_log("Checking slot with formatted time: " . $formattedTime);
            
            $checkStmt->bind_param("ssss", $selectedDate, $formattedTime, $formattedTime, $formattedTime);
            
            if (!$checkStmt->execute()) {
                throw new Exception("Database execute error: " . $checkStmt->error);
            }
            
            $checkResult = $checkStmt->get_result();
            
            // Log the query results
            error_log("Check query results - Number of rows: " . $checkResult->num_rows);
            
            if ($checkResult->num_rows === 0) {
                throw new Exception("This slot is not available in our schedule.");
            }
            
            $slotData = $checkResult->fetch_assoc();
            error_log("Slot found - Details: " . print_r($slotData, true));
            
            // Verify slot is available and not booked
            if ($slotData['is_booked'] == 1) {
                throw new Exception("This slot has already been booked.");
            }
            
            if ($slotData['is_available'] != 1) {
                throw new Exception("This slot is not available for booking.");
            }
            
            // Store in session for the registration form
            $_SESSION['appointment_date'] = $selectedDate;
            $_SESSION['appointment_time'] = $selectedTime;
            $_SESSION['appointment_id'] = $slotData['id'];
            
            // Commit transaction
            $conn->commit();
            error_log("Successfully reserved slot - ID: " . $slotData['id']);
            
            header("Location: registration-form.php");
            exit;
            
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            error_log("Booking error: " . $e->getMessage());
            $_SESSION['booking_error'] = $e->getMessage();
            header("Location: slot-booking.php");
            exit;
        }
    } else {
        $_SESSION['booking_error'] = "Please select both date and time.";
        header("Location: slot-booking.php");
        exit;
    }
}

// Function to standardize time format
function standardizeTimeFormat($timeStr) {
    // Remove extra spaces and convert to uppercase
    $timeStr = trim(strtoupper($timeStr));
    
    // Ensure there's a space before AM/PM
    $timeStr = preg_replace('/([AP])M/', ' $1M', $timeStr);
    
    // Remove double spaces
    $timeStr = preg_replace('/\s+/', ' ', $timeStr);
    
    // Parse the time string to ensure consistent format
    $timestamp = strtotime($timeStr);
    if ($timestamp === false) {
        error_log("Failed to parse time: $timeStr");
        return $timeStr;
    }
    
    // Format as h:i A (e.g., 9:00 AM)
    $formatted = date('g:i A', $timestamp);
    error_log("Standardized time format: $timeStr -> $formatted");
    
    return $formatted;
}

// Function to check if a time slot is in the past
function isTimeSlotPast($date, $time) {
    $now = new DateTime();
    $slotDateTime = DateTime::createFromFormat('Y-m-d h:i A', $date . ' ' . $time);
    return $slotDateTime < $now;
}

// Function to generate time slots
function generateTimeSlots($date) {
    global $conn;
    $slots = [];
    
    try {
        // Get time slots configuration from database
        $configQuery = "SELECT slot_type, start_time, end_time, interval_minutes 
                       FROM time_slots_config";
        $configResult = $conn->query($configQuery);
        
        if (!$configResult) {
            throw new Exception("Error fetching time slots configuration");
        }
        
        while ($config = $configResult->fetch_assoc()) {
            $startTime = strtotime($config['start_time']);
            $endTime = strtotime($config['end_time']);
            $interval = $config['interval_minutes'] * 60; // Convert to seconds
            
            for ($time = $startTime; $time < $endTime; $time += $interval) {
                $timeStr = date('g:i A', $time);
                
                // Check if this slot exists and its status
                $query = "SELECT is_available, is_booked 
                         FROM appointments 
                         WHERE appointment_date = ? 
                         AND (
                             TIME_FORMAT(appointment_time, '%l:%i %p') = ? 
                             OR TIME_FORMAT(appointment_time, '%h:%i %p') = ?
                             OR TIME_FORMAT(appointment_time, '%g:%i %p') = ?
                         )";
                         
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssss", $date, $timeStr, $timeStr, $timeStr);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if (!$row['is_booked']) {
                        if (!isTimeSlotPast($date, $timeStr)) {
                            $slots[] = $timeStr;
                        }
                    }
                }
            }
        }
        
        error_log("Generated slots for date $date: " . implode(", ", $slots));
        
    } catch (Exception $e) {
        error_log("Error generating time slots: " . $e->getMessage());
    }
    
    return $slots;
}

// Get available slots for a specific date
function getAvailableSlots($date) {
    global $conn;
    $slots = [];
    
    try {
        $query = "SELECT 
                    TIME_FORMAT(appointment_time, '%h:%i %p') as formatted_time
                  FROM appointments 
                  WHERE appointment_date = ?
                  AND is_booked = 0
                  ORDER BY appointment_time";
                  
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $date);
    $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $timeStr = standardizeTimeFormat($row['formatted_time']);
            if (!isTimeSlotPast($date, $timeStr)) {
                $slots[] = $timeStr;
            }
        }
        
        error_log("Available slots for $date: " . implode(", ", $slots));
        
} catch (Exception $e) {
        error_log("Error getting available slots: " . $e->getMessage());
    }
    
    return $slots;
}

// Handle AJAX request for slots
if (isset($_GET['action']) && $_GET['action'] === 'get_slots') {
    header('Content-Type: application/json');
    
    $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $slots = getAvailableSlots($date);
    
    echo json_encode([
        'success' => true,
        'slots' => $slots
    ]);
    exit;
}

// Handle slot selection
if (isset($_GET['date']) && isset($_GET['time'])) {
    $selectedDate = $_GET['date'];
    $selectedTime = standardizeTimeFormat($_GET['time']);
    
    error_log("Booking attempt - Date: $selectedDate, Time: $selectedTime");
    
    if (!isTimeSlotPast($selectedDate, $selectedTime)) {
        try {
            $conn->begin_transaction();
            
            // First check if the slot exists and is available
            $checkQuery = "SELECT id, is_booked 
                          FROM appointments 
                          WHERE appointment_date = ? 
                          AND (
                              TIME_FORMAT(appointment_time, '%h:%i %p') = ? 
                              OR TIME_FORMAT(appointment_time, '%l:%i %p') = ? 
                              OR TIME_FORMAT(appointment_time, '%g:%i %p') = ?
                          )";
            
            $stmt = $conn->prepare($checkQuery);
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $conn->error);
            }
            
            $stmt->bind_param("ssss", $selectedDate, $selectedTime, $selectedTime, $selectedTime);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception("This slot is not available in our schedule.");
            }
            
            $slot = $result->fetch_assoc();
            
            if ($slot['is_booked'] == 1) {
                throw new Exception("This slot has already been booked.");
            }
            
            // Try to book the slot
            $bookQuery = "UPDATE appointments 
                         SET is_booked = 1 
                         WHERE id = ? 
                         AND is_booked = 0";
            
            $bookStmt = $conn->prepare($bookQuery);
            if (!$bookStmt) {
                throw new Exception("Failed to prepare booking query: " . $conn->error);
            }
            
            $bookStmt->bind_param("i", $slot['id']);
            
            if (!$bookStmt->execute()) {
                throw new Exception("Failed to book slot: " . $bookStmt->error);
            }
            
            if ($bookStmt->affected_rows > 0) {
                $_SESSION['appointment_date'] = $selectedDate;
                $_SESSION['appointment_time'] = $selectedTime;
                $_SESSION['appointment_id'] = $slot['id'];
                
                $conn->commit();
                error_log("Successfully booked slot ID: " . $slot['id']);
                
                header("Location: registration-form.php");
                exit;
            } else {
                throw new Exception("This slot was just booked by someone else.");
            }
            
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            error_log("Booking error: " . $e->getMessage());
            $_SESSION['booking_error'] = $e->getMessage();
            header("Location: slot-booking.php");
            exit;
        }
    } else {
        $_SESSION['booking_error'] = "This time slot has already passed.";
        header("Location: slot-booking.php");
        exit;
    }
}

// Get today's date
$today = date('Y-m-d');
$slots = getAvailableSlots($today);

// Convert PHP arrays to JavaScript with proper JSON encoding
$availableSlotsJSON = json_encode($availableSlots);
$bookedSlotsJSON = json_encode($bookedSlots);

// Add debug output to verify data
error_log("JSON encoded available slots: " . $availableSlotsJSON);
error_log("JSON encoded booked slots: " . $bookedSlotsJSON);

// Add cache-busting timestamp
// Add a cache-busting timestamp to prevent browser caching of slots
$cacheBuster = time();

// Debug log the slots arrays
error_log("Booked slots: " . json_encode($bookedSlots));
error_log("Available slots: " . json_encode($availableSlots));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>Dr. Kiran Hospitals - Schedule Appointment</title>
 <style>
  :root {
  /* Primary palette - Medical theme with soothing blues and teals */
  --primary-color: #2b86c5;
  --primary-dark: #1a5f8d;
  --primary-light: #e1f2fd;
  --primary-gradient: linear-gradient(135deg, #2b86c5, #36a3dc);
  
  /* Secondary palette */
  --secondary-color: #7d68de;
  --secondary-dark: #5a48c2;
  --secondary-light: #f0edff;
  --secondary-gradient: linear-gradient(135deg, #7d68de, #9a8aec);
  
  /* Accent colors */
  --accent-color: #1db895;
  --accent-dark: #0a9b7c;
  --accent-light: #e6f9f5;
  --accent-gradient: linear-gradient(135deg, #1db895, #28d9b1);
  
  /* Status colors */
  --warning-color: #f7b055;
  --error-color: #f25757;
  
  /* Neutrals */
  --text-dark: #2d3748;
  --text-medium: #596577;
  --text-light: #8896ab;
  --border-color: #e4eaf2;
  --background-light: #f5faff;
  --white: #ffffff;
  
  /* Shadows */
  --shadow-sm: 0 2px 4px rgba(30, 80, 150, 0.08);
  --shadow-md: 0 4px 12px rgba(30, 80, 150, 0.12);
  --shadow-lg: 0 8px 24px rgba(30, 80, 150, 0.15);
  
  /* Card effects */
  --card-border-radius: 16px;
  --button-border-radius: 12px;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  background: linear-gradient(135deg, #e6f3fa, #f0f8ff);
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  margin: 0;
  color: var(--text-dark);
}

.modal {
  background-color: var(--white);
  border-radius: var(--card-border-radius);
  width: 90%;
  max-width: 600px;
  box-shadow: var(--shadow-lg);
  position: relative;
  padding: 32px;
  border: 1px solid rgba(230, 240, 255, 0.5);
}

.close-button {
  position: absolute;
  top: 18px;
  right: 18px;
  font-size: 24px;
  cursor: pointer;
  background: #f5f7fa;
  border: none;
  color: var(--text-medium);
  text-decoration: none;
  transition: all 0.2s;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.close-button:hover {
  background-color: #f0f3f9;
  color: var(--error-color);
  box-shadow: var(--shadow-sm);
}

h1 {
  text-align: center;
  font-size: 28px;
  margin-top: 10px;
  margin-bottom: 32px;
  color: var(--primary-dark);
  font-weight: 600;
  position: relative;
}

h1:after {
  content: "";
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 80px;
  height: 3px;
  background: var(--primary-gradient);
  border-radius: 3px;
}

.calendar-container {
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 40px;
  position: relative;
}

.nav-button {
  width: 40px;
  height: 40px;
  border: none;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--white);
  color: var(--primary-color);
  font-size: 22px;
  cursor: pointer;
  margin: 0 10px;
  position: absolute;
  z-index: 10;
  transition: all 0.3s;
  box-shadow: var(--shadow-sm);
}

.nav-button:hover {
  background-color: var(--primary-light);
  color: var(--primary-dark);
  box-shadow: var(--shadow-md);
  transform: scale(1.05);
}

.nav-button.prev {
  left: -8px;
}

.nav-button.next {
  right: -8px;
}

.calendar {
  border: none;
  border-radius: var(--card-border-radius);
  overflow: hidden;
  width: 100%;
  max-width: 460px;
  background-color: var(--background-light);
  padding: 15px 12px;
  box-shadow: var(--shadow-md);
}

.month-year {
  text-align: center;
  font-size: 18px;
  font-weight: 600;
  margin-bottom: 12px;
  color: var(--primary-dark);
  padding: 5px 0;
}

.weekdays {
  display: flex;
  border-bottom: 1px solid var(--border-color);
  background-color: rgba(230, 240, 255, 0.4);
  border-radius: 8px 8px 0 0;
}

.weekday {
  flex: 1;
  text-align: center;
  padding: 12px 0;
  font-weight: 600;
  color: var(--text-medium);
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.dates {
  display: flex;
  background-color: var(--white);
  height: 95px;
  border-radius: 0 0 8px 8px;
}

.date {
  flex: 1;
  text-align: center;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100%;
  position: relative;
  transition: all 0.3s;
  border-radius: 12px;
  margin: 2px;
}

.date:not(.disabled):hover {
  background-color: var(--primary-light);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.date.selected {
  background: var(--primary-gradient);
  box-shadow: var(--shadow-md);
  transform: translateY(-3px);
}

.date.disabled {
  color: var(--text-light);
  cursor: not-allowed;
  opacity: 0.6;
}

.date-number {
  font-size: 30px;
  font-weight: 700;
  line-height: 1.2;
  position: relative;
  z-index: 1;
}

.date.selected .date-number,
.date.selected .date-month {
  color: var(--white);
}

.date-month {
  font-size: 14px;
  margin-top: 4px;
  font-weight: 500;
}

.date:not(.disabled):not(.selected) .date-number {
  color: var(--primary-color);
}

.date:not(.disabled):not(.selected) .date-month {
  color: var(--primary-color);
}

.date.disabled .date-number,
.date.disabled .date-month {
  color: var(--text-light);
}

.today-indicator {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--error-color);
  box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.6);
}

h2 {
  text-align: center;
  font-size: 22px;
  margin-top: 5px;
  margin-bottom: 22px;
  color: var(--secondary-dark);
  font-weight: 600;
}

.time-period-selection {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin-bottom: 26px;
}

.time-period-button {
  padding: 12px 28px;
  border: none;
  border-radius: var(--button-border-radius);
  background-color: var(--secondary-light);
  color: var(--secondary-dark);
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  min-width: 140px;
  box-shadow: var(--shadow-sm);
}

.time-period-button:hover {
  background-color: #e7e2ff;
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.time-period-button.selected {
  background: var(--secondary-gradient);
  color: var(--white);
  box-shadow: var(--shadow-md);
}

.time-slots-container {
  margin-bottom: 30px;
}

.time-slots {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin-bottom: 30px;
  max-height: 300px;
  overflow-y: auto;
  padding-right: 8px;
  padding-bottom: 4px;
}

.time-slot {
  padding: 12px 8px;
  text-align: center;
  border: none;
  border-radius: var(--button-border-radius);
  cursor: pointer;
  color: var(--primary-dark);
  font-size: 15px;
  font-weight: 600;
  transition: all 0.3s;
  box-shadow: var(--shadow-sm);
  background-color: var(--primary-light);
}

.time-slot:hover {
  background-color: #d0eafc;
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.time-slot.selected {
  background: var(--primary-gradient);
  color: var(--white);
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.time-slot.disabled {
  background-color: #f3f5f9;
  color: var(--text-light);
  cursor: not-allowed;
  pointer-events: none;
  box-shadow: none;
  opacity: 0.7;
}

.no-slots-message {
  text-align: center;
  color: var(--text-medium);
  font-style: italic;
  padding: 20px;
  background-color: #f0f5fa;
  border-radius: var(--button-border-radius);
  box-shadow: var(--shadow-sm);
  border-left: 4px solid var(--primary-color);
}

.submit-button {
  display: block;
  width: 100%;
  background: var(--accent-gradient);
  color: var(--white);
  padding: 16px;
  border: none;
  border-radius: var(--button-border-radius);
  font-size: 17px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: var(--shadow-md);
  position: relative;
  overflow: hidden;
}

.submit-button:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-3px);
}

.submit-button:before {
  content: "";
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent,
    rgba(255, 255, 255, 0.2),
    transparent
  );
  transition: 0.5s;
}

.submit-button:hover:before {
  left: 100%;
}

.submit-button:disabled {
  background: #c6d2e0;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.footer {
  text-align: center;
  padding-top: 25px;
  margin-top: 25px;
  border-top: 1px solid var(--border-color);
  color: var(--text-medium);
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo {
  margin-left: 10px;
  font-weight: 600;
  color: var(--primary-dark);
  position: relative;
}

.logo:before {
  content: "•";
  position: absolute;
  left: -12px;
  color: var(--accent-color);
  font-size: 20px;
}

/* Custom scrollbar for time slots */
.time-slots::-webkit-scrollbar {
  width: 8px;
}

.time-slots::-webkit-scrollbar-track {
  background-color: #edf2f7;
  border-radius: 8px;
}

.time-slots::-webkit-scrollbar-thumb {
  background-color: var(--secondary-light);
  border-radius: 8px;
  border: 2px solid #edf2f7;
}

.time-slots::-webkit-scrollbar-thumb:hover {
  background-color: var(--secondary-color);
}

/* Add subtle animation effects */
@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(43, 134, 197, 0.4); }
  70% { box-shadow: 0 0 0 10px rgba(43, 134, 197, 0); }
  100% { box-shadow: 0 0 0 0 rgba(43, 134, 197, 0); }
}

.date.selected {
  animation: pulse 2s infinite;
}

/* Additional responsive touches */
@media (max-width: 480px) {
  .time-slots {
    grid-template-columns: repeat(3, 1fr);
  }
  
  .modal {
    padding: 24px 18px;
  }
  
  h1 {
    font-size: 24px;
  }
  
  h2 {
    font-size: 20px;
  }
}
  </style>
</head>
<body>
  <div class="modal">
    <button class="close-button" onclick="window.location.href='index.php';">×</button>
    <div style="text-align:center; margin-bottom: 18px;">
      <img src="img/klogo-.png" alt="Kiran Hospital Logo" style="max-width: 120px; height: auto; display: inline-block;">
    </div>
    <h1>Schedule Your Appointment</h1>
    
    <?php if (isset($_SESSION['booking_error'])): ?>
      <div style="background-color: #ffebee; color: #c62828; padding: 10px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #c62828;">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['booking_error']; ?>
      </div>
      <?php unset($_SESSION['booking_error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($_SESSION['error']); ?>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <form id="scheduleForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return validateForm()">
      <input type="hidden" name="selected_date" id="selected_date_input">
      <input type="hidden" name="selected_time" id="selected_time_input">
      <input type="hidden" name="cache_buster" value="<?php echo $cacheBuster; ?>">
      
      <div class="calendar-container">
        <button type="button" class="nav-button prev" onclick="navigateWeek(-1)">‹</button>
        
        <div class="calendar">
          <div class="month-year" id="month-year-display">March 2025</div>
          <div class="weekdays">
            <div class="weekday">Sun</div>
            <div class="weekday">Mon</div>
            <div class="weekday">Tue</div>
            <div class="weekday">Wed</div>
            <div class="weekday">Thu</div>
            <div class="weekday">Fri</div>
            <div class="weekday">Sat</div>
          </div>
          
          <div class="dates" id="dates-container">
            <!-- Calendar dates will be generated by JavaScript -->
          </div>
        </div>
        
        <button type="button" class="nav-button next" onclick="navigateWeek(1)">›</button>
      </div>
      
      <h2>Select appointment time</h2>
      
      <div class="time-period-selection">
        <button type="button" id="morning-button" class="time-period-button">Morning</button>
        <button type="button" id="evening-button" class="time-period-button">Evening</button>
      </div>
      
      <div class="time-slots-container">
        <div class="time-slots" id="time-slots-container">
          <!-- Time slots will be generated by JavaScript -->
        </div>
        <div class="no-slots-message" id="no-slots-message">
          No available time slots for this date and time period.
        </div>
      </div>
      
      <button type="submit" class="submit-button" id="continue-button" disabled>Continue to Patient Details</button>
      
      <div class="footer">
        <span class="logo">Dr. Kiran Hospitals</span>
      </div>
    </form>
  </div>

  <script>
    // Cache buster to prevent stale data
    const cacheBuster = '<?php echo $cacheBuster; ?>';
    
    const today = new Date(<?php echo date('Y'); ?>, <?php echo date('n')-1; ?>, <?php echo date('j'); ?>);
    let currentWeekStart = new Date(today);
    let selectedDate = new Date(today);
    let selectedTimeSlot = null;
    let selectedTimePeriod = null; // 'morning' or 'evening'
    
    // Get booked and available slots from PHP
    const bookedSlots = <?php echo $bookedSlotsJSON; ?>;
    const availableSlots = <?php echo $availableSlotsJSON; ?>;
    
    // Debug log the arrays
    console.log("Initial booked slots:", bookedSlots);
    console.log("Initial available slots:", availableSlots);
    
    // Adjust to the start of the week (Sunday)
    const dayOfWeek = today.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    currentWeekStart.setDate(today.getDate() - dayOfWeek); // Start from Sunday
    
    // Format options for displaying dates
    const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    const fullMonthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    
    // Time slot generation parameters
    const morningStartTime = 9 * 60; // 9:00 AM in minutes
    const morningEndTime = 13 * 60; // 1:00 PM in minutes
    const eveningStartTime = 18 * 60; // 6:00 PM in minutes
    const eveningEndTime = 20 * 60; // 8:00 PM in minutes
    const interval = 15; // 15 minutes
    
    // Initialize the calendar
    function initCalendar() {
      renderWeek();
      
      // Set up time period button handlers
      document.getElementById('morning-button').addEventListener('click', function() {
        selectTimePeriod('morning');
      });
      
      document.getElementById('evening-button').addEventListener('click', function() {
        selectTimePeriod('evening');
      });
      
      // Set up form submission handler
      document.getElementById('scheduleForm').addEventListener('submit', function(e) {
          e.preventDefault();
        
        if (!selectedTimeSlot) {
            showError('Please select a time slot before continuing.');
            return false;
        }
        
        if (!selectedDate) {
            showError('Please select a date before continuing.');
          return false;
        }
        
        // Format selected date for submission
        const formattedDate = `${selectedDate.getFullYear()}-${(selectedDate.getMonth() + 1).toString().padStart(2, '0')}-${selectedDate.getDate().toString().padStart(2, '0')}`;
        document.getElementById('selected_date_input').value = formattedDate;
        document.getElementById('selected_time_input').value = selectedTimeSlot;
        
        // Check if the slot is available
        const slotKey = `${formattedDate} ${selectedTimeSlot}`;
        console.log('Checking slot availability:', slotKey);
        console.log('Available slots:', availableSlots);
        
        // Check if the slot exists in availableSlots
        const isAvailable = availableSlots.some(slot => {
            const [slotDate, slotTime, slotPeriod] = slot.split(' ');
            const slotTimeStr = `${slotTime} ${slotPeriod}`;
            return slotDate === formattedDate && slotTimeStr === selectedTimeSlot;
        });
        
        if (!isAvailable) {
            showError('This slot is not available. Please select another time.');
          return false;
        }
        
        // Check if the slot is booked
        const isBooked = bookedSlots.some(slot => {
            const [slotDate, slotTime, slotPeriod] = slot.split(' ');
            const slotTimeStr = `${slotTime} ${slotPeriod}`;
            return slotDate === formattedDate && slotTimeStr === selectedTimeSlot;
        });
        
        if (isBooked) {
            showError('This slot has already been booked. Please select another time.');
            return false;
        }
        
        // Show loading state
        const submitButton = document.getElementById('continue-button');
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        
        // Submit the form
        try {
            this.submit();
        } catch (error) {
            console.error('Form submission error:', error);
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-arrow-right"></i> Continue to Patient Details';
            showError('An error occurred. Please try again.');
        }
      });
      
      // Initially hide time slots until a time period is selected
      document.getElementById('time-slots-container').style.display = 'none';
      document.getElementById('no-slots-message').style.display = 'none';
    }
    
    // Select time period (morning or evening)
    function selectTimePeriod(period) {
      selectedTimePeriod = period;
      
      // Update UI for selected time period button
      const morningButton = document.getElementById('morning-button');
      const eveningButton = document.getElementById('evening-button');
      
      morningButton.classList.remove('selected');
      eveningButton.classList.remove('selected');
      
      if (period === 'morning') {
        morningButton.classList.add('selected');
      } else {
        eveningButton.classList.add('selected');
      }
      
      // Reset selected time slot
      selectedTimeSlot = null;
      document.getElementById('continue-button').disabled = true;
      
      // Generate time slots for the selected period
      generateTimeSlots();
    }
    
    // Render the current week
    function renderWeek() {
      const datesContainer = document.getElementById('dates-container');
      datesContainer.innerHTML = '';
      
      // Update month-year display based on current week
      updateMonthYearDisplay();
      
      // Track if we've found a selectable date yet
      let foundSelectableDate = false;
      
      // Generate dates starting from Sunday
      for (let i = 0; i < 7; i++) {
        const date = new Date(currentWeekStart);
        date.setDate(currentWeekStart.getDate() + i);
        
        const dateNumber = date.getDate();
        const monthShort = monthNames[date.getMonth()];
        const isToday = isSameDate(date, today);
        const isPast = date < today && !isToday;
        const isSelectedDate = isSameDate(date, selectedDate);
        const isSunday = date.getDay() === 0;
        
        // Create date element
        const dateElement = document.createElement('div');
        dateElement.className = `date${isSelectedDate ? ' selected' : ''}${(isPast || isSunday) ? ' disabled' : ''}`;
        
        // Store date information as data attributes
        dateElement.dataset.date = date.getDate();
        dateElement.dataset.month = date.getMonth();
        dateElement.dataset.year = date.getFullYear();
        dateElement.dataset.fulldate = `${date.getFullYear()}-${(date.getMonth() + 1).toString().padStart(2, '0')}-${date.getDate().toString().padStart(2, '0')}`;
        
        // Add title for Sundays
        if (isSunday) {
            dateElement.title = "Closed on Sundays";
        }
        
        if (!isPast && !isSunday) {
            dateElement.onclick = function() { 
                selectDate(this); 
                const selectedDateObj = new Date(
                    parseInt(this.dataset.year),
                    parseInt(this.dataset.month),
                    parseInt(this.dataset.date)
                );
                selectedDate = selectedDateObj;
                
                // If a time period is already selected, regenerate time slots
                if (selectedTimePeriod) {
                    generateTimeSlots();
                }
            };
            
            // If this is the first selectable date and no date is currently selected
            if (!foundSelectableDate && (!isSelectedDate || isPast)) {
                foundSelectableDate = true;
                if (!isToday) { // Only auto-select if it's not already today
                    selectedDate = new Date(date);
                }
            }
        }
        
        // Date number
        const dateNumberElement = document.createElement('div');
        dateNumberElement.className = 'date-number';
        dateNumberElement.textContent = dateNumber;
        
        // Date month
        const dateMonthElement = document.createElement('div');
        dateMonthElement.className = 'date-month';
        dateMonthElement.textContent = monthShort;
        
        // Today indicator
        if (isToday) {
            const todayIndicator = document.createElement('div');
            todayIndicator.className = 'today-indicator';
            dateElement.appendChild(todayIndicator);
        }
        
        // Add holiday indicator for Sundays
        if (isSunday) {
            const holidayIndicator = document.createElement('div');
            holidayIndicator.className = 'holiday-indicator';
            holidayIndicator.textContent = 'CLOSED';
            holidayIndicator.style.fontSize = '10px';
            holidayIndicator.style.color = '#f25757';
            holidayIndicator.style.fontWeight = 'bold';
            holidayIndicator.style.marginTop = '2px';
            dateElement.appendChild(holidayIndicator);
        }
        
        dateElement.appendChild(dateNumberElement);
        dateElement.appendChild(dateMonthElement);
        datesContainer.appendChild(dateElement);
      }
    }
    
    // Update the month and year display
    function updateMonthYearDisplay() {
      const firstDate = new Date(currentWeekStart);
      const lastDate = new Date(currentWeekStart);
      lastDate.setDate(currentWeekStart.getDate() + 6); // Show full week Sunday-Saturday
      
      let displayText = '';
      
      if (firstDate.getMonth() === lastDate.getMonth()) {
        // Same month
        displayText = `${fullMonthNames[firstDate.getMonth()]} ${firstDate.getFullYear()}`;
      } else {
        // Different months
        displayText = `${monthNames[firstDate.getMonth()]} - ${monthNames[lastDate.getMonth()]} ${lastDate.getFullYear()}`;
      }
      
      document.getElementById('month-year-display').textContent = displayText;
    }
    
    // Navigate weeks
    function navigateWeek(direction) {
      const newWeekStart = new Date(currentWeekStart);
      newWeekStart.setDate(currentWeekStart.getDate() + (direction * 7));
      
      // Only allow navigating to future weeks, not past weeks
      if (direction < 0) {
        // For backward navigation, ensure we don't go before the current week
        const todaySunday = new Date(today);
        todaySunday.setDate(today.getDate() - today.getDay()); // Go to current week's Sunday
        
        if (newWeekStart < todaySunday) {
          return; // Don't navigate to past weeks
        }
      }
      
      currentWeekStart = newWeekStart;
      renderWeek();
      
      // If a time period is selected, regenerate the time slots
      if (selectedTimePeriod) {
        generateTimeSlots();
      }
    }
    
    // Select a date
    function selectDate(element) {
      // Skip if clicking on disabled date
      if (element.classList.contains('disabled')) {
        return;
      }
      
      // Remove selected class from all date elements
      document.querySelectorAll('.date').forEach(date => {
        date.classList.remove('selected');
      });
      
      // Add selected class to clicked element
      element.classList.add('selected');
      
      // Reset selected time slot
      selectedTimeSlot = null;
      document.getElementById('continue-button').disabled = true;
    }
    
    // Check if a time slot is booked or unavailable - IMPROVED function
    function isTimeSlotBooked(dateStr, timeStr) {
        // Make sure time format is consistent
        const formattedTime = standardizeTimeFormat(timeStr);
        const slotDateTime = `${dateStr} ${formattedTime}`;
        
        // Debug logging
        console.log(`Checking slot ${slotDateTime}`);
        
        // Check if slot is booked
        const isBooked = bookedSlots.includes(slotDateTime);
        
        // Check if slot is available (enabled by admin)
        const isAvailable = availableSlots.includes(slotDateTime);
        
        // Slot is considered unavailable if it's either booked or not enabled by admin
        return isBooked || !isAvailable;
    }
    
    // Generate time slots - MODIFIED to handle slots from database
    function generateTimeSlots() {
        const timeSlotsContainer = document.getElementById('time-slots-container');
        const noSlotsMessage = document.getElementById('no-slots-message');
        const continueButton = document.getElementById('continue-button');
        
        // Clear previous slots and reset state
        timeSlotsContainer.innerHTML = '';
        continueButton.disabled = true;
        selectedTimeSlot = null;
        
        if (!selectedTimePeriod || !selectedDate) {
            timeSlotsContainer.style.display = 'none';
            noSlotsMessage.style.display = 'none';
            return;
        }
        
        // Format the selected date
        const formattedDate = `${selectedDate.getFullYear()}-${(selectedDate.getMonth() + 1).toString().padStart(2, '0')}-${selectedDate.getDate().toString().padStart(2, '0')}`;
        
        console.log('Generating slots for date:', formattedDate);
        console.log('Time period:', selectedTimePeriod);
        console.log('Available slots:', availableSlots);
        
        // Get current time
        const now = new Date();
        const isToday = selectedDate.toDateString() === now.toDateString();
        
        // Generate all possible time slots for the selected period
        let timeSlots = [];
        if (selectedTimePeriod === 'morning') {
            for (let time = morningStartTime; time <= morningEndTime; time += interval) {
                const hour = Math.floor(time / 60);
                const minute = time % 60;
                const period = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                const timeStr = `${hour12}:${minute.toString().padStart(2, '0')} ${period}`;
                
                // Only add slots that exist in availableSlots
                const slotKey = `${formattedDate} ${timeStr}`;
                if (availableSlots.includes(slotKey)) {
                    timeSlots.push(timeStr);
                }
            }
        } else {
            for (let time = eveningStartTime; time <= eveningEndTime; time += interval) {
                const hour = Math.floor(time / 60);
                const minute = time % 60;
                const period = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
                const timeStr = `${hour12}:${minute.toString().padStart(2, '0')} ${period}`;
                
                // Only add slots that exist in availableSlots
                const slotKey = `${formattedDate} ${timeStr}`;
                if (availableSlots.includes(slotKey)) {
                    timeSlots.push(timeStr);
                }
            }
        }
        
        // Filter out past time slots if it's today
        if (isToday) {
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();
            const currentTime = currentHour * 60 + currentMinute;
            
            timeSlots = timeSlots.filter(timeStr => {
                const [time, period] = timeStr.split(' ');
                const [hours, minutes] = time.split(':');
                let slotHour = parseInt(hours);
                const slotMinute = parseInt(minutes);
                
                // Convert to 24-hour format
                if (period === 'PM' && slotHour !== 12) slotHour += 12;
                if (period === 'AM' && slotHour === 12) slotHour = 0;
                
                const slotTime = slotHour * 60 + slotMinute;
                return slotTime > currentTime + 30; // Add 30-minute buffer
            });
        }
        
        // Create time slot elements
        if (timeSlots.length === 0) {
            noSlotsMessage.textContent = "No available time slots for this time period. Please try another time period or date.";
            noSlotsMessage.style.display = 'block';
            timeSlotsContainer.style.display = 'none';
            return;
        }
        
        timeSlotsContainer.style.display = 'grid';
        noSlotsMessage.style.display = 'none';
        
        timeSlots.forEach(timeStr => {
            const slotKey = `${formattedDate} ${timeStr}`;
            const isBooked = bookedSlots.includes(slotKey);
            
            const slotContainer = document.createElement('div');
            slotContainer.className = 'time-slot-container';
            slotContainer.style.position = 'relative';
            
                    const timeSlot = document.createElement('div');
                    timeSlot.className = 'time-slot';
            timeSlot.textContent = timeStr;
            
            if (isBooked) {
                timeSlot.classList.add('disabled');
                timeSlot.title = 'This slot is already booked';
                
                // Add delete button for booked slots
                if (isAdmin) { // You'll need to set this variable based on user role
                    const deleteBtn = document.createElement('button');
                    deleteBtn.className = 'delete-slot-btn';
                    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
                    deleteBtn.style.position = 'absolute';
                    deleteBtn.style.right = '5px';
                    deleteBtn.style.top = '50%';
                    deleteBtn.style.transform = 'translateY(-50%)';
                    deleteBtn.style.background = '#ff4444';
                    deleteBtn.style.color = 'white';
                    deleteBtn.style.border = 'none';
                    deleteBtn.style.borderRadius = '50%';
                    deleteBtn.style.width = '24px';
                    deleteBtn.style.height = '24px';
                    deleteBtn.style.cursor = 'pointer';
                    deleteBtn.title = 'Delete this booking';
                    
                    deleteBtn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        if (confirm('Are you sure you want to delete this booking?')) {
                            try {
                                const response = await fetch('delete_booking.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: `date=${formattedDate}&time=${timeStr}`
                                });
                                
                                const data = await response.json();
                                if (data.success) {
                                    // Remove from booked slots
                                    const index = bookedSlots.indexOf(slotKey);
                                    if (index > -1) {
                                        bookedSlots.splice(index, 1);
                                    }
                                    // Add to available slots
                                    if (!availableSlots.includes(slotKey)) {
                                        availableSlots.push(slotKey);
                                    }
                                    // Regenerate time slots
                                    generateTimeSlots();
                                } else {
                                    alert(data.message || 'Error deleting booking');
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                alert('Error deleting booking');
                }
            }
        });
        
                    slotContainer.appendChild(deleteBtn);
                }
        } else {
                timeSlot.addEventListener('click', function() {
                    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedTimeSlot = timeStr;
                    continueButton.disabled = false;
                });
            }
            
            slotContainer.appendChild(timeSlot);
            timeSlotsContainer.appendChild(slotContainer);
        });
    }
    
    // Check if two dates are the same (ignoring time)
    function isSameDate(date1, date2) {
        return date1.getDate() === date2.getDate() &&
                date1.getMonth() === date2.getMonth() &&
                date1.getFullYear() === date2.getFullYear();
    }
    
    // Initialize calendar when the page loads
    window.addEventListener('DOMContentLoaded', initCalendar);
    
    // Add a refresh mechanism to prevent stale data
    window.addEventListener('focus', function() {
        // Reload the page when it regains focus to get fresh booking data
        window.location.href = 'slot-booking.php?refresh=' + new Date().getTime();
    });
    
    // Add periodic refresh to prevent stale data
    let refreshTimer;
    
    function startRefreshTimer() {
        // Refresh every 30 seconds to get updated booking data
        refreshTimer = setTimeout(function() {
            // Only refresh if the user hasn't selected a time slot yet
            if (!selectedTimeSlot) {
                window.location.href = 'slot-booking.php?refresh=' + new Date().getTime();
            }
        }, 30000); // 30 seconds
    }
    
    // Start the timer when the page loads
    window.addEventListener('DOMContentLoaded', function() {
        initCalendar();
        startRefreshTimer();
    });
    
    // Clear the timer when the user is about to leave the page
    window.addEventListener('beforeunload', function() {
        clearTimeout(refreshTimer);
    });

    // Helper function to show error messages
    function showError(message) {
        const errorDiv = document.createElement('div');
        errorDiv.style.backgroundColor = '#ffebee';
        errorDiv.style.color = '#c62828';
        errorDiv.style.padding = '10px';
        errorDiv.style.borderRadius = '5px';
        errorDiv.style.marginBottom = '20px';
        errorDiv.style.borderLeft = '4px solid #c62828';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
        
        // Remove any existing error messages
        const existingErrors = document.querySelectorAll('[data-error-message]');
        existingErrors.forEach(error => error.remove());
        
        // Add the new error message
        errorDiv.setAttribute('data-error-message', '');
        document.querySelector('form').insertBefore(errorDiv, document.querySelector('form').firstChild);
        
        // Scroll to error message
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Update form validation to remove captcha check
    function validateForm() {
        if (!selectedDate) {
            showError('Please select a date.');
            return false;
        }

        if (!selectedTimeSlot) {
            showError('Please select a time slot.');
            return false;
        }

        return true;
    }
  </script>
</body>
</html>