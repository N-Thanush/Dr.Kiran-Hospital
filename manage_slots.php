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
    header("Location: login.php");
    exit;
}

// Get the valid status values from the database
try {
    $statusQuery = "SHOW COLUMNS FROM appointments WHERE Field = 'status'";
    $result = $conn->query($statusQuery);
    $column = $result->fetch_assoc();
    
    // Log the column type and existing values
    logError("Status column type: " . $column['Type']);
    
    // Get existing status values
    $existingStatusQuery = "SELECT DISTINCT status FROM appointments";
    $statusResult = $conn->query($existingStatusQuery);
    $existingStatuses = [];
    while ($row = $statusResult->fetch_assoc()) {
        $existingStatuses[] = $row['status'];
    }
    logError("Existing status values: " . implode(", ", $existingStatuses));
    
    // Default to a known working status value
    $defaultStatus = !empty($existingStatuses) ? $existingStatuses[0] : 'confirmed';
    
} catch (Exception $e) {
    logError("Error checking status column: " . $e->getMessage());
    $defaultStatus = 'confirmed'; // Fallback status
}

$success_message = '';
$error_message = '';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (isset($_POST['action']) && $_POST['action'] === 'delete_slot') {
        header('Content-Type: application/json');
        
        try {
            if (!isset($_POST['date']) || !isset($_POST['time'])) {
                throw new Exception('Missing date or time parameter');
            }
            
            $date = $_POST['date'];
            $time = $_POST['time'];
    
    // Validate date format
            if (!DateTime::createFromFormat('Y-m-d', $date)) {
                throw new Exception('Invalid date format');
            }
            
            // Start transaction
            $conn->begin_transaction();
            
            // Check if slot exists and is not booked
            $checkQuery = "SELECT id, is_booked FROM appointments 
                         WHERE appointment_date = ? 
                         AND TIME_FORMAT(appointment_time, '%l:%i %p') = ?";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("ss", $date, $time);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $slot = $result->fetch_assoc();
                if ($slot['is_booked'] == 1) {
                    throw new Exception('Cannot delete booked slot');
                }
                
                // Delete the slot instead of updating its state
                $deleteQuery = "DELETE FROM appointments 
                              WHERE id = ? AND is_booked = 0";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("i", $slot['id']);
                $stmt->execute();
                
                if ($stmt->affected_rows > 0) {
                    $conn->commit();
            echo json_encode([
                        'success' => true, 
                        'message' => 'Slot deleted successfully',
                        'slotId' => $slot['id']
                    ]);
                } else {
                    throw new Exception('Failed to delete slot');
                }
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Slot not found']);
            }
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            logError($e->getMessage());
        }
        exit;
    } else {
        // For non-AJAX form submissions
        try {
            if ($_POST['action'] === 'manage_slots') {
                $selectedDate = $_POST['selected_date'] ?? null;
                $morningSlots = isset($_POST['morning_slots']) ? $_POST['morning_slots'] : [];
                $eveningSlots = isset($_POST['evening_slots']) ? $_POST['evening_slots'] : [];
                
                if (!$selectedDate) {
                    $_SESSION['error'] = "Please select a date";
                    header("Location: manage_slots.php");
                    exit;
                }
                
                // Date validation
                if (!DateTime::createFromFormat('Y-m-d', $selectedDate)) {
                    $_SESSION['error'] = "Invalid date format";
                    header("Location: manage_slots.php");
                    exit;
                }
                
                // Slot selection validation
                $morningSlots = isset($_POST['morning_slots']) ? $_POST['morning_slots'] : [];
                $eveningSlots = isset($_POST['evening_slots']) ? $_POST['evening_slots'] : [];
                
                if (empty($morningSlots) && empty($eveningSlots)) {
                    $_SESSION['error'] = "Please select at least one time slot";
                    header("Location: manage_slots.php");
                    exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
                    // Get existing booked slots
                    $bookedQuery = "SELECT TIME_FORMAT(appointment_time, '%l:%i %p') as time 
                                   FROM appointments 
                                   WHERE appointment_date = ? AND is_booked = 1";
                    $stmt = $conn->prepare($bookedQuery);
                    $stmt->bind_param("s", $selectedDate);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $bookedSlots = [];
                    while ($row = $result->fetch_assoc()) {
                        $bookedSlots[] = $row['time'];
                    }

                    // Delete all unbooked slots for this date
                    $deleteQuery = "DELETE FROM appointments 
                                  WHERE appointment_date = ?
                                  AND is_booked = 0";
                    $stmt = $conn->prepare($deleteQuery);
                    $stmt->bind_param("s", $selectedDate);
                    $stmt->execute();

                    // Prepare insert statement for new slots
                    $insertQuery = "INSERT INTO appointments 
                                  (appointment_date, appointment_time, is_available, is_booked, status) 
                                  VALUES (?, 
                                         STR_TO_DATE(?, '%l:%i %p'), 
                                         1, 0, ?)";
                    $stmt = $conn->prepare($insertQuery);

                    // Process morning slots
                    foreach ($morningSlots as $timeStr) {
                        if (!in_array($timeStr, $bookedSlots)) {
                            // Ensure consistent 12-hour format
                            $formattedTime = date('g:i A', strtotime($timeStr));
                            error_log("Processing morning slot: " . $formattedTime);
                            $stmt->bind_param("sss", $selectedDate, $formattedTime, $defaultStatus);
                            $stmt->execute();
                            error_log("Inserted morning slot: " . $formattedTime . ", Status: " . $defaultStatus);
                        }
                    }

                    // Process evening slots
                    foreach ($eveningSlots as $timeStr) {
                        if (!in_array($timeStr, $bookedSlots)) {
                            // Ensure consistent 12-hour format
                            $formattedTime = date('g:i A', strtotime($timeStr));
                            error_log("Processing evening slot: " . $formattedTime);
                            $stmt->bind_param("sss", $selectedDate, $formattedTime, $defaultStatus);
                            $stmt->execute();
                            error_log("Inserted evening slot: " . $formattedTime . ", Status: " . $defaultStatus);
                        }
                    }

                    $conn->commit();
                    $_SESSION['success'] = "Slots updated successfully! All previous unbooked slots have been disabled and new slots are now available.";
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $_SESSION['error'] = "Error updating slots: " . $e->getMessage();
                    logError($e->getMessage());
                }
                
                // Redirect back to the page with the selected date
                header("Location: manage_slots.php?date=" . urlencode($selectedDate));
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "An error occurred: " . $e->getMessage();
            logError($e->getMessage());
            header("Location: manage_slots.php");
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
    $morning_slots[] = date('g:i A', $time);  // Using consistent 12-hour format
}

// Generate evening slots
$evening_start = strtotime($evening_config['start_time']);
$evening_end = strtotime($evening_config['end_time']);
$evening_interval = $evening_config['interval_minutes'] * 60;

$evening_slots = array();
for ($time = $evening_start; $time < $evening_end; $time += $evening_interval) {
    $evening_slots[] = date('g:i A', $time);  // Using consistent 12-hour format
}

// Debug log the generated slots
error_log("Generated morning slots: " . implode(", ", $morning_slots));
error_log("Generated evening slots: " . implode(", ", $evening_slots));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Slots - Dr. Kiran Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/delete-animations.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .navbar {
            background: #4834d4;
            margin-bottom: 30px;
        }
        .navbar-brand {
            color: white !important;
            font-size: 24px;
            font-weight: 500;
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
        }
        .nav-link:hover {
            color: white !important;
        }
        .guide-box {
            background-color: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .guide-title {
            color: #2e7d32;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .guide-steps {
            color: #1b5e20;
            margin: 0;
            padding-left: 20px;
        }
        .guide-steps li {
            margin-bottom: 8px;
        }
        .time-slots {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            padding: 15px 0;
        }
        .time-slot {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease-in-out;
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
        .badge {
            margin-left: 5px;
            font-size: 11px;
            padding: 4px 6px;
        }
        .delete-slot {
            padding: 4px 8px;
            font-size: 12px;
            line-height: 1;
            border-radius: 4px;
        }
        .delete-slot i {
            font-size: 11px;
        }
        .card-title {
            color: #4834d4;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .time-slot.deleting {
            opacity: 0.7;
            pointer-events: none;
        }
        .time-slot.delete-error {
            animation: shake 0.5s ease-in-out;
        }
        .delete-success {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0);
            width: 30px;
            height: 30px;
            background-color: #4CAF50;
            border-radius: 50%;
            opacity: 0;
            transition: all 0.3s ease-in-out;
        }
        .delete-success.show {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }
        .delete-success::before,
        .delete-success::after {
            content: '';
            position: absolute;
            background-color: white;
        }
        .delete-success::before {
            width: 3px;
            height: 15px;
            transform: rotate(45deg);
            left: 14px;
            top: 8px;
        }
        .delete-success::after {
            width: 3px;
            height: 8px;
            transform: rotate(-45deg);
            left: 8px;
            top: 12px;
        }
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-hospital me-2"></i>Dr. Kiran Hospital
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_calendar.php">
                            <i class="fas fa-calendar-alt me-1"></i>Calendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

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
                <i class="far fa-clock me-2"></i>Manage Available Slots
            </div>
            <div class="card-body">
                <form method="POST" action="manage_slots.php" id="slotManagementForm">
                    <input type="hidden" name="action" value="manage_slots">
                    
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Select Date</h5>
                            <input type="date" class="form-control" name="selected_date" id="selected_date"
                                   value="<?php echo htmlspecialchars($selectedDate); ?>"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Morning Slots (9:00 AM - 1:00 PM)</h5>
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
                                    $isBooked = false;
                                    $isAvailable = false;
                                    $slotId = null;
                                    
                                    foreach ($slots as $slot) {
                                        if ($slot['formatted_time'] === $timeStr) {
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
                                               id="morning_<?php echo $timeStr; ?>" 
                                               name="morning_slots[]" 
                                               value="<?php echo $timeStr; ?>"
                                               <?php echo $isBooked ? 'disabled' : ''; ?>
                                               <?php echo (!$isBooked && $isAvailable) ? 'checked' : ''; ?>>
                                        <label for="morning_<?php echo $timeStr; ?>">
                                            <?php echo $timeStr; ?>
                                            <?php if ($isBooked): ?>
                                                <span class="badge bg-danger">Booked</span>
                                            <?php endif; ?>
                                        </label>
                                        <?php if (!$isBooked && $isAvailable): ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-slot" 
                                                    data-date="<?php echo $selectedDate; ?>" 
                                                    data-time="<?php echo $timeStr; ?>">
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
                                    $isBooked = false;
                                    $isAvailable = false;
                                    $slotId = null;
                                    
                                    foreach ($slots as $slot) {
                                        if ($slot['formatted_time'] === $timeStr) {
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
                                               id="evening_<?php echo $timeStr; ?>" 
                                               name="evening_slots[]" 
                                               value="<?php echo $timeStr; ?>"
                                               <?php echo $isBooked ? 'disabled' : ''; ?>
                                               <?php echo (!$isBooked && $isAvailable) ? 'checked' : ''; ?>>
                                        <label for="evening_<?php echo $timeStr; ?>">
                                            <?php echo $timeStr; ?>
                                            <?php if ($isBooked): ?>
                                                <span class="badge bg-danger">Booked</span>
                                            <?php endif; ?>
                                        </label>
                                        <?php if (!$isBooked && $isAvailable): ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-slot" 
                                                    data-date="<?php echo $selectedDate; ?>" 
                                                    data-time="<?php echo $timeStr; ?>">
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

    <script>
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

        // Handle date selection
        document.getElementById('selected_date').addEventListener('change', function() {
            window.location.href = 'manage_slots.php?date=' + this.value;
        });

        // Handle form submission
        document.getElementById('slotManagementForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const selectedDate = document.getElementById('selected_date').value;
            if (!selectedDate) {
                alert('Please select a date first');
                return;
            }

            const morningSlots = document.querySelectorAll('.morning-slots input[type="checkbox"]:checked').length;
            const eveningSlots = document.querySelectorAll('.evening-slots input[type="checkbox"]:checked').length;

            if (morningSlots === 0 && eveningSlots === 0) {
                alert('Please select at least one time slot');
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            // Submit the form
            this.submit();
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
                    button.addEventListener('click', (e) => this.handleDelete(e));
                });
            }

            async handleDelete(event) {
                const button = event.currentTarget;
                const timeSlot = button.closest('.time-slot');
                
                if (timeSlot.classList.contains('deleting')) return;
                
                event.stopPropagation();
                
                if (!await this.showConfirmation()) return;
                
                this.startDeleteAnimation(button, timeSlot);
                
                try {
                    const response = await this.deleteSlot(button.dataset.date, button.dataset.time);
                    if (response.success) {
                        await this.showSuccessAnimation(timeSlot);
                        this.resetTimeSlot(timeSlot);
                    } else {
                        this.showErrorAnimation(timeSlot);
                    }
                } catch (error) {
                    console.error('Delete error:', error);
                    this.showErrorAnimation(timeSlot);
                }
            }

            showConfirmation() {
                return new Promise(resolve => {
                    this.overlay.classList.add('active');
                    
                    const result = window.confirm('Are you sure you want to reset this slot?');
                    this.overlay.classList.remove('active');
                    
                    resolve(result);
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

            async deleteSlot(date, time) {
                try {
                    const response = await fetch('manage_slots.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_slot&date=${date}&time=${time}`
                    });
                    
                    return await response.json();
                } catch (error) {
                    console.error('Delete request failed:', error);
                    return { success: false };
                }
            }

            async showSuccessAnimation(timeSlot) {
                const successIndicator = document.createElement('div');
                successIndicator.className = 'delete-success';
                timeSlot.appendChild(successIndicator);
                
                await new Promise(resolve => setTimeout(resolve, 50));
                successIndicator.classList.add('show');
                
                await new Promise(resolve => setTimeout(resolve, 1000));
            }

            showErrorAnimation(timeSlot) {
                timeSlot.classList.remove('deleting');
                timeSlot.classList.add('delete-error');
                
                const button = timeSlot.querySelector('.delete-slot');
                button.disabled = false;
                button.innerHTML = button.dataset.originalContent;
                
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
        document.addEventListener('DOMContentLoaded', () => {
            new DeleteButtonHandler();
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>