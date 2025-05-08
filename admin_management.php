<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once 'connect.php';

// Function to log errors with timestamp
function logError($message) {
    error_log("[" . date('Y-m-d H:i:s') . "] [manage_slots.php] " . $message);
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Get selected date from query parameter or use today
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch all appointments for the selected date
$query = "SELECT a.*, 
          a.patient_name,
          a.patient_phone,
          a.preferred_specialty,
          a.reason
          FROM appointments a
          WHERE a.appointment_date = ?
          ORDER BY a.appointment_time";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

// Handle AJAX form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'manage_slots':
                $selectedDate = $_POST['selected_date'];
                $selectedSlots = json_decode($_POST['selected_slots'], true);

                try {
                    // Start transaction
                    $conn->begin_transaction();

                    // Delete all unbooked slots for this date
                    $deleteQuery = "DELETE FROM appointments 
                                  WHERE appointment_date = ? AND is_booked = 0";
                    $stmt = $conn->prepare($deleteQuery);
                    $stmt->bind_param("s", $selectedDate);
                    $stmt->execute();

                    // Insert new slots
                    $insertQuery = "INSERT INTO appointments 
                                  (appointment_date, appointment_time, is_available, is_booked) 
                                  VALUES (?, ?, 1, 0)";
                    $stmt = $conn->prepare($insertQuery);

                    foreach ($selectedSlots as $time) {
                        $stmt->bind_param("ss", $selectedDate, $time);
                        $stmt->execute();
                    }

                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Slots updated successfully!']);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Error updating slots: ' . $e->getMessage()]);
                }
                exit;

            case 'delete_slot':
                if ((!isset($_POST['date']) || !isset($_POST['time'])) && !isset($_POST['slot_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
                    exit;
                }

                try {
                    // If we have a slot ID, use that directly (most reliable method)
                    if (isset($_POST['slot_id']) && !empty($_POST['slot_id'])) {
                        $slotId = $_POST['slot_id'];
                        error_log("Deleting slot by ID: $slotId");
                        
                        // First verify it's not booked
                        $checkQuery = "SELECT is_booked FROM appointments WHERE id = ?";
                        $stmt = $conn->prepare($checkQuery);
                        $stmt->bind_param("i", $slotId);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            $slot = $result->fetch_assoc();
                            if ($slot['is_booked'] == 1) {
                                echo json_encode(['success' => false, 'message' => 'Cannot delete booked slot']);
                                exit;
                            }
                            
                            // Delete the slot
                            $deleteQuery = "DELETE FROM appointments WHERE id = ? AND is_booked = 0";
                            $stmt = $conn->prepare($deleteQuery);
                            $stmt->bind_param("i", $slotId);
                            $stmt->execute();
                            
                            if ($stmt->affected_rows > 0) {
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'Slot deleted successfully',
                                    'slotId' => $slotId
                                ]);
                                exit;
                            } else {
                                error_log("Delete failed for slot ID: $slotId");
                                echo json_encode(['success' => false, 'message' => 'Failed to delete slot']);
                                exit;
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Slot not found']);
                            exit;
                        }
                    }
                    
                    // If we don't have an ID, fall back to date/time approach
                    $date = $_POST['date'];
                    $time = $_POST['time'];
                    
                    // For debugging
                    error_log("Delete slot request: Date: $date, Time: $time");

                    // Try with both the original time and format conversions
                    $formattedTime = $time;
                    if (strpos($time, 'AM') !== false || strpos($time, 'PM') !== false) {
                        // This is in 12-hour format, convert it to 24-hour
                        $formattedTime = date('H:i:s', strtotime($time));
                    }
                    
                    error_log("Formatted time for DB query: $formattedTime");
                    
                    // Use a query with better time comparison
                    $checkQuery = "SELECT id, is_booked, appointment_time 
                                 FROM appointments 
                                 WHERE appointment_date = ? 
                                 AND (appointment_time = ? OR TIME_FORMAT(appointment_time, '%l:%i %p') = ?)";
                    $stmt = $conn->prepare($checkQuery);
                    $stmt->bind_param("sss", $date, $formattedTime, $time);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    error_log("Check query found {$result->num_rows} matching slots");

                    if ($result->num_rows > 0) {
                        $slot = $result->fetch_assoc();
                        error_log("Found slot ID: {$slot['id']}, is_booked: {$slot['is_booked']}, time: {$slot['appointment_time']}");
                        
                        if ($slot['is_booked'] == 1) {
                            echo json_encode(['success' => false, 'message' => 'Cannot delete booked slot']);
                            exit;
                        }

                        // Delete the slot
                        $deleteQuery = "DELETE FROM appointments WHERE id = ? AND is_booked = 0";
                        $stmt = $conn->prepare($deleteQuery);
                        $stmt->bind_param("i", $slot['id']);
                        $stmt->execute();

                        if ($stmt->affected_rows > 0) {
                            echo json_encode([
                                'success' => true,
                                'message' => 'Slot deleted successfully',
                                'slotId' => $slot['id']
                            ]);
                        } else {
                            error_log("Delete failed for slot ID: {$slot['id']}");
                            echo json_encode(['success' => false, 'message' => 'Failed to delete slot']);
                        }
                    } else {
                        // If still not found, try to debug by listing all slots for that date
                        $allSlotsQuery = "SELECT id, appointment_time, TIME_FORMAT(appointment_time, '%l:%i %p') as formatted_time 
                                         FROM appointments 
                                         WHERE appointment_date = ?";
                        $stmt = $conn->prepare($allSlotsQuery);
                        $stmt->bind_param("s", $date);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        $availableSlots = [];
                        while ($row = $result->fetch_assoc()) {
                            $availableSlots[] = $row;
                        }
                        
                        error_log("All slots for date $date: " . json_encode($availableSlots));
                        echo json_encode([
                            'success' => false, 
                            'message' => 'Slot not found',
                            'debug' => "Tried to find time: $time / $formattedTime for date: $date"
                        ]);
                    }
                } catch (Exception $e) {
                    error_log("Exception in delete_slot: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                }
                exit;

            case 'check_slot':
                $date = $_POST['date'];
                $time = $_POST['time'];
                
                $available = isSlotAvailable($conn, $date, $time);
                
                echo json_encode([
                    'available' => $available,
                    'message' => $available ? 'Slot is available' : 'This slot is no longer available. Please select another time slot.'
                ]);
                exit;
        }
    }
}

// Get existing slots for the selected date
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$slots = [];

try {
    $query = "SELECT 
                TIME_FORMAT(appointment_time, '%l:%i %p') as formatted_time,
                is_available,
                is_booked,
                patient_name,
                id
              FROM appointments 
              WHERE appointment_date = ?
              ORDER BY appointment_time";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Standardize the time format
        $row['formatted_time'] = date('g:i A', strtotime($row['formatted_time']));
        $slots[] = $row;
    }
    
    // Debug log the fetched slots
    error_log("Fetched slots for date $selectedDate: " . json_encode($slots));
    
} catch (Exception $e) {
    logError("Error fetching slots: " . $e->getMessage());
    $_SESSION['error'] = "Error loading slots. Please try again.";
}

// Get time slots configuration from database
$morning_slots_query = "SELECT start_time, end_time, interval_minutes FROM time_slots_config WHERE slot_type = 'morning'";
$evening_slots_query = "SELECT start_time, end_time, interval_minutes FROM time_slots_config WHERE slot_type = 'evening'";

$morning_config = $conn->query($morning_slots_query)->fetch_assoc();
$evening_config = $conn->query($evening_slots_query)->fetch_assoc();

// Generate morning slots
$morning_start = strtotime($morning_config['start_time']);
$morning_end = strtotime($morning_config['end_time']);
$morning_interval = $morning_config['interval_minutes'] * 60;

$morning_slots = array();
for ($time = $morning_start; $time < $morning_end; $time += $morning_interval) {
    $morning_slots[] = date('H:i:s', $time);  // Store in 24-hour format for database
}

// Generate evening slots
$evening_start = strtotime($evening_config['start_time']);
$evening_end = strtotime($evening_config['end_time']);
$evening_interval = $evening_config['interval_minutes'] * 60;

$evening_slots = array();
for ($time = $evening_start; $time < $evening_end; $time += $evening_interval) {
    $evening_slots[] = date('H:i:s', $time);  // Store in 24-hour format for database
}

// Debug log the generated slots
error_log("Generated morning slots: " . implode(", ", $morning_slots));
error_log("Generated evening slots: " . implode(", ", $evening_slots));

// Add this function to check slot availability
function isSlotAvailable($conn, $date, $time) {
    $query = "SELECT COUNT(*) as count 
              FROM appointments 
              WHERE appointment_date = ? 
              AND appointment_time = ? 
              AND is_booked = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $date, $time);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] == 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - Dr. Kiran Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="icon" type="image/png" href="img/klogo-.png">
    <style>

body{

    font-family: var(--bs-body-font-family);
}
.content {
            display: none;
        }
        .active {
            display: block;
        }
        .navbar {
            background: white !important;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            color: #1D00DB !important;
            font-size: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-brand img {
            height: 40px;
            width: auto;
        }
        .nav-link {
            color: #4834d4 !important;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        .nav-link:hover {
            color: #3a2db0 !important;
            background: rgba(72, 52, 212, 0.1);
        }
        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #4834d4 !important;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }

        .card-body {
            border-radius: 0 0 20px 20px !important;
            padding: 2rem 2rem 1rem 2rem !important;
        }
        .slot {
            border: 1px solid rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .bg-warning-subtle {
            background-color: #fff3cd !important;
        }
        .bg-success-subtle {
            background-color: #d1e7dd !important;
        }
        .time-display {
            font-size: 1.1rem;
        }
        .badge {
            padding: 0.5rem 1rem;
            font-weight: 500;
        }
        .h5 {
            color: #4834d4;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #4834d4;
            box-shadow: 0 0 0 0.2rem rgba(72, 52, 212, 0.25);
        }
        #dateSelector {
            border-radius: 6px;
            border: 1px solid #ced4da;
            padding: 0.5rem;
        }
        .booking-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }
        .booking-details div {
            margin-bottom: 8px;
            font-size: 15px;
            color: #333;
        }
        .booking-details .label {
            font-weight: 600;
            color: #333;
            display: inline-block;
            min-width: 100px;
        }
        .toggle-container {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
        }
        .toggle-btn {
            background: #4834d4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .toggle-btn:hover {
            background: #3a2db0;
            transform: translateY(-2px);
        }
        .time-slot {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease-in-out;
            margin: 5px;
            overflow: hidden;
        }
        .time-slot input[type="checkbox"] {
            display: none;
        }
        .time-slot label {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background-color: #fff;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            margin: 0;
            min-width: 100px;
            justify-content: center;
        }
        .time-slot input[type="checkbox"]:checked + label {
            background-color: #4834d4;
            color: white;
            border-color: #4834d4;
        }
        .time-slot.booked label {
            background-color: #f8f9fa;
            color: #6c757d;
            border-color: #dee2e6;
            cursor: not-allowed;
        }
        .time-slot:not(.booked) label:hover {
            background-color: #4834d4;
            color: white;
            border-color: #4834d4;
            opacity: 0.8;
        }
        .select-buttons {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
        }
        .btn-outline-primary {
            color: #4834d4;
            border-color: #4834d4;
        }
        .btn-outline-primary:hover {
            background-color: #4834d4;
            border-color: #4834d4;
        }
        .guide-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .guide-title {
            color: #4834d4;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .guide-title i {
            color: #ff9800;
        }
        
        .guide-steps {
            padding-left: 20px;
            margin: 0;
        }
        
        .guide-steps li {
            margin-bottom: 10px;
            color: #495057;
        }
        
        .delete-confirm-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            transition: opacity 0.3s ease;
        }
        
        .delete-confirm-overlay.active {
            display: block;
            animation: fadeIn 0.2s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .time-slot.deleting {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .time-slot.delete-error {
            animation: shake 0.5s;
            border-left: 4px solid #f44336;
        }
        
        .delete-success {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(76, 175, 80, 0.1);
            border: 2px solid #4caf50;
            border-radius: 6px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 2;
        }
        
        .delete-success.show {
            opacity: 1;
            animation: successPulse 1s ease-out;
        }
        
        @keyframes successPulse {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Add these new styles for better UI */
        .content {
            background-color: #f8f9fa;
            min-height: calc(100vh - 80px);
            padding: 30px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="img/klogo-.png" alt="Dr. Kiran Hospital Logo">
                Dr. Kiran Hospital
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Toggle Button -->
    <div class="toggle-container">
        <button class="toggle-btn" onclick="toggleViews()">
            <i class="fas fa-exchange-alt"></i>
            <span id="toggleText">Switch to Calendar</span>
        </button>
    </div>

    <!-- Calendar View -->
    <div id="calendarView" class="content active">
        <div class="container">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Appointment Calendar
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label">Select Date:</label>
                        <input type="date" id="dateSelector" class="form-control" style="max-width: 200px;"
                            value="<?php echo $selected_date; ?>">
                    </div>

                    <div class="row">
                        <!-- Morning Slots -->
                        <div class="col-md-6 mb-4">
                            <h5 class="mb-3">
                                <i class="fas fa-sun me-2"></i>Morning Slots (10:00 AM - 1:00 PM)
                            </h5>
                            <?php
                            $morning_slots_found = false;
                            foreach ($appointments as $appointment) {
                                $time = strtotime($appointment['appointment_time']);
                                if ($time >= strtotime('10:00:00') && $time < strtotime('13:00:00')) {
                                    $morning_slots_found = true;
                                    ?>
                                    <div class="slot p-3 mb-3 rounded <?php echo $appointment['is_booked'] ? 'bg-warning-subtle' : 'bg-success-subtle'; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="time-display">
                                                <i class="fas fa-clock me-2"></i>
                                                <strong><?php echo date('h:i A', $time); ?></strong>
                                            </div>
                                            <span class="badge <?php echo $appointment['is_booked'] ? 'bg-warning' : 'bg-success'; ?>">
                                                <?php echo $appointment['is_booked'] ? 'Booked' : 'Available'; ?>
                                            </span>
                                        </div>
                                        <?php if ($appointment['is_booked']): ?>
                                            <div class="mt-3">
                                                <div><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                                <div><strong>Phone:</strong> <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                                                <?php if (!empty($appointment['preferred_specialty'])): ?>
                                                    <div><strong>Specialty:</strong> <?php echo htmlspecialchars($appointment['preferred_specialty']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($appointment['reason'])): ?>
                                                    <div><strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                }
                            }
                            if (!$morning_slots_found) {
                                echo '<div class="alert alert-info">No morning slots available for this date.</div>';
                            }
                            ?>
                        </div>

                        <!-- Evening Slots -->
                        <div class="col-md-6 mb-4">
                            <h5 class="mb-3">
                                <i class="fas fa-moon me-2"></i>Evening Slots (6:00 PM - 8:00 PM)
                            </h5>
                            <?php
                            $evening_slots_found = false;
                            foreach ($appointments as $appointment) {
                                $time = strtotime($appointment['appointment_time']);
                                if ($time >= strtotime('18:00:00') && $time < strtotime('20:00:00')) {
                                    $evening_slots_found = true;
                                    ?>
                                    <div class="slot p-3 mb-3 rounded <?php echo $appointment['is_booked'] ? 'bg-warning-subtle' : 'bg-success-subtle'; ?>">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="time-display">
                                                <i class="fas fa-clock me-2"></i>
                                                <strong><?php echo date('h:i A', $time); ?></strong>
                                            </div>
                                            <span class="badge <?php echo $appointment['is_booked'] ? 'bg-warning' : 'bg-success'; ?>">
                                                <?php echo $appointment['is_booked'] ? 'Booked' : 'Available'; ?>
                                            </span>
                                        </div>
                                        <?php if ($appointment['is_booked']): ?>
                                            <div class="mt-3">
                                                <div><strong>Patient:</strong> <?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                                <div><strong>Phone:</strong> <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                                                <?php if (!empty($appointment['preferred_specialty'])): ?>
                                                    <div><strong>Specialty:</strong> <?php echo htmlspecialchars($appointment['preferred_specialty']); ?></div>
                                                <?php endif; ?>
                                                <?php if (!empty($appointment['reason'])): ?>
                                                    <div><strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php
                                }
                            }
                            if (!$evening_slots_found) {
                                echo '<div class="alert alert-info">No evening slots available for this date.</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Slot Management View -->
    <div id="slotManagementView" class="content">
        <div class="container">
            <!-- Quick Guide Box -->
            <div class="guide-box">
                <div class="guide-title">
                    <i class="fas fa-lightbulb"></i>
                    How to Enable Appointment Slots
                </div>
                <ol class="guide-steps">
                    <li>Select a date from the calendar</li>
                    <li>Choose the time slots you want to make available</li>
                    <li>Use the "Select All" buttons to quickly select morning or evening slots</li>
                    <li>Click "Save Available Slots" to update</li>
                </ol>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0" style="color:rgb(255, 255, 255);">
                        <i class="fas fa-clock me-2"></i>Manage Slots
                    </h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="slotManagementForm">
                        <input type="hidden" name="action" value="manage_slots">
                        
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Select Date</h5>
                                <input type="date" class="form-control" name="selected_date" id="selected_date"
                                       value="<?php echo htmlspecialchars($selected_date); ?>"
                                       min="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Morning Slots (10:00 AM - 1:00 PM)</h5>
                                <div class="select-buttons">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll('morning')">
                                        Select All Morning
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="unselectAll('morning')">
                                        Clear Morning
                                    </button>
                                </div>
                                <div class="time-slots morning-slots">
                                    <?php
                                    foreach ($morning_slots as $timeStr) {
                                        $displayTime = date('g:i A', strtotime($timeStr));  // Convert to 12-hour format for display
                                        $isBooked = false;
                                        $isAvailable = false;
                                        $slotId = null;
                                        
                                        foreach ($slots as $slot) {
                                            if (date('g:i A', strtotime($slot['formatted_time'])) === $displayTime) {
                                                $isBooked = $slot['is_booked'] == 1;
                                                $isAvailable = $slot['is_available'] == 1;
                                                $slotId = $slot['id'];
                                                break;
                                            }
                                        }
                                        
                                        $slotClass = $isBooked ? 'booked' : '';
                                        ?>
                                        <div class="time-slot <?php echo $slotClass; ?>" data-slot-id="<?php echo $slotId; ?>">
                                            <input type="checkbox" 
                                                   id="morning_<?php echo $displayTime; ?>" 
                                                   name="morning_slots[]" 
                                                   value="<?php echo $timeStr; ?>"
                                                   <?php echo $isBooked ? 'disabled' : ''; ?>
                                                   <?php echo (!$isBooked && $isAvailable) ? 'checked' : ''; ?>>
                                            <label for="morning_<?php echo $displayTime; ?>">
                                                <?php echo $displayTime; ?>
                                                <?php if ($isBooked): ?>
                                                    <span class="badge bg-danger">Booked</span>
                                                <?php endif; ?>
                                            </label>
                                            <?php if (!$isBooked && $isAvailable && $slotId): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-slot" 
                                                        data-date="<?php echo $selectedDate; ?>" 
                                                        data-time="<?php echo $timeStr; ?>"
                                                        data-display-time="<?php echo $displayTime; ?>"
                                                        data-slot-id="<?php echo $slotId; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Evening Slots (6:00 PM - 8:00 PM)</h5>
                                <div class="select-buttons">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll('evening')">
                                        Select All Evening
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="unselectAll('evening')">
                                        Clear Evening
                                    </button>
                                </div>
                                <div class="time-slots evening-slots">
                                    <?php
                                    foreach ($evening_slots as $timeStr) {
                                        $displayTime = date('g:i A', strtotime($timeStr));  // Convert to 12-hour format for display
                                        $isBooked = false;
                                        $isAvailable = false;
                                        $slotId = null;
                                        
                                        foreach ($slots as $slot) {
                                            if (date('g:i A', strtotime($slot['formatted_time'])) === $displayTime) {
                                                $isBooked = $slot['is_booked'] == 1;
                                                $isAvailable = $slot['is_available'] == 1;
                                                $slotId = $slot['id'];
                                                break;
                                            }
                                        }
                                        
                                        $slotClass = $isBooked ? 'booked' : '';
                                        ?>
                                        <div class="time-slot <?php echo $slotClass; ?>" data-slot-id="<?php echo $slotId; ?>">
                                            <input type="checkbox" 
                                                   id="evening_<?php echo $displayTime; ?>" 
                                                   name="evening_slots[]" 
                                                   value="<?php echo $timeStr; ?>"
                                                   <?php echo $isBooked ? 'disabled' : ''; ?>
                                                   <?php echo (!$isBooked && $isAvailable) ? 'checked' : ''; ?>>
                                            <label for="evening_<?php echo $displayTime; ?>">
                                                <?php echo $displayTime; ?>
                                                <?php if ($isBooked): ?>
                                                    <span class="badge bg-danger">Booked</span>
                                                <?php endif; ?>
                                            </label>
                                            <?php if (!$isBooked && $isAvailable && $slotId): ?>
                                                <button type="button" class="btn btn-sm btn-danger delete-slot" 
                                                        data-date="<?php echo $selectedDate; ?>" 
                                                        data-time="<?php echo $timeStr; ?>"
                                                        data-display-time="<?php echo $displayTime; ?>"
                                                        data-slot-id="<?php echo $slotId; ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Save Available Slots
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        function toggleViews() {
            const calendarView = document.getElementById('calendarView');
            const slotManagementView = document.getElementById('slotManagementView');
            const toggleText = document.getElementById('toggleText');
            if (calendarView.classList.contains('active')) {
                calendarView.classList.remove('active');
                slotManagementView.classList.add('active');
                toggleText.textContent = 'Switch to Calendar';
                window.location.hash = '#slot-management';
            } else {
                calendarView.classList.add('active');
                slotManagementView.classList.remove('active');
                toggleText.textContent = 'Switch to Slot Management';
                window.location.hash = '';
            }
        }

        function selectAll(period) {
            const container = document.querySelector(`.${period}-slots`);
            container.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach(checkbox => {
                checkbox.checked = true;
            });
        }

        function unselectAll(period) {
            const container = document.querySelector(`.${period}-slots`);
            container.querySelectorAll('input[type="checkbox"]:not(:disabled)').forEach(checkbox => {
                checkbox.checked = false;
            });
        }

        // Add date validation to disable Sundays
        document.getElementById('dateSelector').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            if (selectedDate.getDay() === 0) { // 0 is Sunday
                alert('Sundays are not available for appointments');
                this.value = ''; // Clear the selection
                return;
            }
            window.location.href = 'admin_management.php?date=' + this.value;
        });

        // Add date validation to the slot management form
        document.getElementById('selected_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            if (selectedDate.getDay() === 0) { // 0 is Sunday
                alert('Sundays are not available for appointments');
                this.value = ''; // Clear the selection
                return;
            }
            
            // Add the hash to maintain slot management view when changing dates
            const currentHash = window.location.hash;
            window.location.href = 'admin_management.php?date=' + this.value + (currentHash || '#slot-management');
        });

        // Function to show Toastify notification
        function showNotification(message, type = 'success') {
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "right",
                backgroundColor: type === 'success' ? "#4CAF50" : "#f44336",
                stopOnFocus: true
            }).showToast();
        }

        // Show notification for logout
        if (new URLSearchParams(window.location.search).has('logged_out')) {
            showNotification('You have been successfully logged out', 'success');
        }

        // Update form submission to show notifications
        document.getElementById('slotManagementForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const selectedDate = document.getElementById('selected_date').value;
            if (!selectedDate) {
                showNotification('Please select a date first', 'error');
                return;
            }

            const morningSlots = document.querySelectorAll('.morning-slots input[type="checkbox"]:checked').length;
            const eveningSlots = document.querySelectorAll('.evening-slots input[type="checkbox"]:checked').length;

            if (morningSlots === 0 && eveningSlots === 0) {
                showNotification('Please select at least one time slot', 'error');
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            // Get all selected slots
            const selectedSlots = [];
            document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                selectedSlots.push(checkbox.value);
            });

            // Submit form via AJAX
            const formData = new FormData(this);
            formData.append('selected_slots', JSON.stringify(selectedSlots));

            fetch('admin_management.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    // Store current view state in sessionStorage
                    sessionStorage.setItem('currentView', 'slot-management');
                    // Set hash before reload
                    window.location.hash = '#slot-management';
                    setTimeout(() => {
                        // Reload the page with the hash intact
                        window.location.reload();
                    }, 1000);
                } else {
                    showNotification(data.message || 'Error updating slots', 'error');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating slots', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        class DeleteButtonHandler {
            constructor() {
                this.setupEventListeners();
                this.overlay = this.createOverlay();
                document.body.appendChild(this.overlay);
            }

            createOverlay() {
                const overlay = document.createElement('div');
                overlay.className = 'delete-confirm-overlay';
                return overlay;
            }

            setupEventListeners() {
                document.querySelectorAll('.delete-slot').forEach(button => {
                    button.addEventListener('click', (e) => {
                        e.preventDefault(); // Prevent any default behavior
                        e.stopPropagation(); // Stop event from bubbling up
                        this.handleDelete(e);
                    });
                });
            }

            async handleDelete(event) {
                const button = event.currentTarget;
                const timeSlot = button.closest('.time-slot');
                
                if (timeSlot.classList.contains('deleting')) return;
                
                // Prevent any form submission
                event.preventDefault();
                event.stopPropagation();
                
                if (!await this.showConfirmation()) return;
                
                // Log all available data for debugging
                console.log('Delete button data attributes:', {
                    date: button.dataset.date,
                    time: button.dataset.time,
                    displayTime: button.dataset.displayTime,
                    slotId: button.dataset.slotId
                });
                
                this.startDeleteAnimation(button, timeSlot);
                
                try {
                    // Try with the 24-hour format time first
                    const response = await this.deleteSlot(button.dataset.date, button.dataset.time, button.dataset.slotId);
                    if (response.success) {
                        await this.showSuccessAnimation(timeSlot);
                        this.resetTimeSlot(timeSlot);
                        // Show feedback
                        showNotification('Slot deleted successfully', 'success');
                    } else {
                        this.showErrorAnimation(timeSlot);
                        let errorMsg = response.message || 'Failed to delete slot';
                        if (response.debug) {
                            console.error('Delete error debug info:', response.debug);
                        }
                        showNotification(errorMsg, 'error');
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    this.showErrorAnimation(timeSlot);
                    showNotification('Error processing request', 'error');
                }
            }

            showConfirmation() {
                return new Promise(resolve => {
                    this.overlay.classList.add('active');
                    
                    // Use setTimeout to ensure the overlay is rendered before the confirm dialog
                    setTimeout(() => {
                        const result = window.confirm('Are you sure you want to reset this slot?');
                        this.overlay.classList.remove('active');
                        resolve(result);
                    }, 50);
                });
            }

            startDeleteAnimation(button, timeSlot) {
                button.disabled = true;
                timeSlot.classList.add('deleting');
                
                // Show loading spinner
                const originalContent = button.innerHTML;
                button.innerHTML = '<span class="loading-spinner"></span>';
                button.dataset.originalContent = originalContent;
            }

            async deleteSlot(date, time, slotId) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'delete_slot');
                    
                    // If we have a slot ID, use that (most reliable)
                    if (slotId) {
                        formData.append('slot_id', slotId);
                        console.log(`Deleting slot by ID: ${slotId}`);
                    } else {
                        // Otherwise use date and time
                        formData.append('date', date);
                        formData.append('time', time);
                        console.log(`Deleting slot: date=${date}, time=${time}`);
                    }
                    
                    const response = await fetch('admin_management.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    console.log('Delete response:', result);
                    return result;
                } catch (error) {
                    console.error('Delete request failed:', error);
                    return { success: false, message: 'Network error occurred' };
                }
            }

            async showSuccessAnimation(timeSlot) {
                const successIndicator = document.createElement('div');
                successIndicator.className = 'delete-success';
                timeSlot.appendChild(successIndicator);
                
                // Use setTimeout to ensure proper animation rendering
                await new Promise(resolve => setTimeout(resolve, 50));
                successIndicator.classList.add('show');
                
                await new Promise(resolve => setTimeout(resolve, 800));
            }

            showErrorAnimation(timeSlot) {
                timeSlot.classList.remove('deleting');
                timeSlot.classList.add('delete-error');
                
                const button = timeSlot.querySelector('.delete-slot');
                if (button) {
                    button.disabled = false;
                    button.innerHTML = button.dataset.originalContent || '<i class="fas fa-trash"></i>';
                }
                
                setTimeout(() => {
                    timeSlot.classList.remove('delete-error');
                }, 500);
            }

            resetTimeSlot(timeSlot) {
                // Remove deleting class
                timeSlot.classList.remove('deleting');
                
                // Uncheck the checkbox
                const checkbox = timeSlot.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = false;
                }

                // Remove the delete button
                const deleteButton = timeSlot.querySelector('.delete-slot');
                if (deleteButton) {
                    deleteButton.remove();
                }

                // Remove success indicator if exists
                const successIndicator = timeSlot.querySelector('.delete-success');
                if (successIndicator) {
                    successIndicator.remove();
                }
            }
        }

        // Initialize delete button handler
        function initDeleteButtonHandler() {
            if (window.deleteButtonHandlerInstance) {
                // Remove old overlay if exists
                if (window.deleteButtonHandlerInstance.overlay && window.deleteButtonHandlerInstance.overlay.parentNode) {
                    window.deleteButtonHandlerInstance.overlay.parentNode.removeChild(window.deleteButtonHandlerInstance.overlay);
                }
            }
            window.deleteButtonHandlerInstance = new DeleteButtonHandler();
        }
        document.addEventListener('DOMContentLoaded', () => {
            // First check hash
            const useSlotManagementView = window.location.hash === '#slot-management' || 
                                         new URLSearchParams(window.location.search).has('view') ||
                                         sessionStorage.getItem('currentView') === 'slot-management';
            
            if (useSlotManagementView) {
                document.getElementById('calendarView').classList.remove('active');
                document.getElementById('slotManagementView').classList.add('active');
                document.getElementById('toggleText').textContent = 'Switch to Calendar';
                // Set hash if it's not already set
                if (window.location.hash !== '#slot-management') {
                    window.location.hash = '#slot-management';
                }
            } else {
                document.getElementById('calendarView').classList.add('active');
                document.getElementById('slotManagementView').classList.remove('active');
                document.getElementById('toggleText').textContent = 'Switch to Slot Management';
            }
            
            // Initialize the delete button handler
            setTimeout(() => {
                initDeleteButtonHandler();
            }, 100); // Small delay to ensure DOM is fully ready

            // Prevent form submissions from causing page redirects
            document.getElementById('slotManagementForm').addEventListener('submit', function(e) {
                // We're handling this with our custom AJAX, so prevent default
                e.preventDefault();
            });
        });
    </script>
</body>

</html>