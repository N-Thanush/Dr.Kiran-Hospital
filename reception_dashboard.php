<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once 'connect.php';

// Check if reception staff is logged in
if (!isset($_SESSION['reception_logged_in']) || $_SESSION['reception_logged_in'] !== true) {
    // For testing purposes, we'll allow admin access as well
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Redirect to login page
        header('Location: reception_login.php');
        exit();
    }
}

// Handle slot management POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'manage_slots') {
        $selected_date = $_POST['selected_date'];
        $morning_slots = isset($_POST['morning_slots']) ? $_POST['morning_slots'] : [];
        $evening_slots = isset($_POST['evening_slots']) ? $_POST['evening_slots'] : [];
        
        try {
            $conn->begin_transaction();
            
            // First, disable all non-booked slots for this date
            $stmt = $conn->prepare("UPDATE appointments SET is_available = FALSE 
                                  WHERE appointment_date = ? AND is_booked = FALSE");
            $stmt->bind_param("s", $selected_date);
            $stmt->execute();
            
            // Process all slots
            $all_slots = array_merge($morning_slots, $evening_slots);
            foreach ($all_slots as $slot) {
                // Check if slot exists
                $check = $conn->prepare("SELECT id, is_booked FROM appointments 
                                       WHERE appointment_date = ? AND appointment_time = ?");
                $check->bind_param("ss", $selected_date, $slot);
                $check->execute();
                $result = $check->get_result();
                
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    if (!$row['is_booked']) {
                        // Update existing slot if not booked
                        $update = $conn->prepare("UPDATE appointments SET is_available = TRUE 
                                               WHERE appointment_date = ? AND appointment_time = ?");
                        $update->bind_param("ss", $selected_date, $slot);
                        $update->execute();
                    }
                } else {
                    // Insert new slot
                    $insert = $conn->prepare("INSERT INTO appointments 
                                            (appointment_date, appointment_time, is_available, is_booked) 
                                            VALUES (?, ?, TRUE, FALSE)");
                    $insert->bind_param("ss", $selected_date, $slot);
                    $insert->execute();
                }
            }
            
            $conn->commit();
            $success_message = "Slots updated successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Error updating slots: " . $e->getMessage();
        }
    }
}

// Get the current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get the selected date (default to today if not set)
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

// Handle month overflow/underflow
if ($month > 12) {
    $month = 1;
    $year++;
} elseif ($month < 1) {
    $month = 12;
    $year--;
}

// Get month name
$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

// Get the first day of the month
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);

// Get appointments for the current month
$startDate = date('Y-m-01', $firstDay);
$endDate = date('Y-m-t', $firstDay);

// Initialize appointments array
$appointments = [];
$availableSlots = [];

try {
    // Get appointments for the selected date
    $query = "SELECT 
                a.id,
                a.appointment_date,
                TIME_FORMAT(a.appointment_time, '%l:%i %p') as formatted_time,
                a.patient_name,
                a.patient_email,
                a.patient_phone,
                a.patient_age,
                a.patient_gender,
                a.reason_for_visit,
                a.additional_notes,
                a.status
              FROM appointments a
              WHERE a.appointment_date = ? 
              AND a.is_booked = 1
              ORDER BY a.appointment_time";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }

    // Get available slots
    $query = "SELECT appointment_time FROM appointments 
              WHERE appointment_date = ? AND is_available = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $selectedDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $availableSlots = [];
    while ($row = $result->fetch_assoc()) {
        $availableSlots[] = $row['appointment_time'];
    }
} catch (Exception $e) {
    error_log("Error fetching data: " . $e->getMessage());
    $appointments = [];
    $availableSlots = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Reception Dashboard - Dr. Kiran Neuro Centre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    
    <!-- Load jQuery first -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Load scripts in correct order -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        
        body {
            background-color: #f5f7f9;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .title {
            font-size: 32px;
            color: #4169e1;
            font-weight: 500;
        }
        
        .calendar-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin: 0 auto;
            max-width: 1200px;
        }
        
        .main-calendar {
            padding: 25px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .month-nav {
            font-size: 24px;
            font-weight: 500;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }
        
        .calendar-weekday {
            text-align: center;
            font-weight: 600;
            color: #4169e1;
            padding: 10px;
            border-bottom: 2px solid #f0f3f9;
        }
        
        .calendar-day {
            position: relative;
            min-height: 120px;
            border-radius: 10px;
            background-color: #f8fafc;
            padding: 10px;
            transition: all 0.3s ease;
        }
        
        .calendar-day:hover {
            background-color: #f0f3f9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }
        
        .day-number {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .today {
            background-color: #e1f2fd;
            border: 2px solid #4169e1;
        }
        
        .selected {
            background-color: #f0edff;
            border: 2px solid #7d68de;
        }
        
        .appointment-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #4169e1;
        }
        
        .appointments-list {
            margin-top: 20px;
        }
        
        .appointment-card {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .appointment-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .appointment-time {
            font-weight: 600;
            color: #4169e1;
            margin-bottom: 5px;
        }
        
        .appointment-patient {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .appointment-details {
            color: #596577;
            font-size: 14px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status-confirmed {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .status-cancelled {
            background-color: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }
        
        .status-completed {
            background-color: rgba(3, 169, 244, 0.1);
            color: #03A9F4;
        }
        
        .status-rescheduled {
            background-color: rgba(255, 152, 0, 0.1);
            color: #FF9800;
        }
        
        .status-noshow {
            background-color: rgba(158, 158, 158, 0.1);
            color: #9E9E9E;
        }
        
        .slot-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: white;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .slot-time {
            font-weight: 600;
            font-size: 16px;
        }
        
        .slot-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .slot-available {
            background-color: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }
        
        .slot-booked {
            background-color: rgba(244, 67, 54, 0.1);
            color: #F44336;
        }
        
        .date-heading {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 2px solid #4169e1;
            padding-bottom: 10px;
        }
        
        .section-heading {
            font-size: 18px;
            font-weight: 600;
            margin: 30px 0 15px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-icon {
            color: #4169e1;
            font-size: 20px;
        }
        
        .reception-notice {
            background-color: #fff3e0;
            border: 1px solid #ffe0b2;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .reception-notice-title {
            font-size: 18px;
            font-weight: 600;
            color: #e65100;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .reception-notice-title i {
            color: #f57c00;
        }
        
        .reception-notice p {
            color: #424242;
            line-height: 1.5;
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .calendar-grid {
                gap: 5px;
            }
            
            .calendar-day {
                min-height: 100px;
                padding: 8px;
            }
            
            .day-number {
                font-size: 14px;
            }
            
            .appointment-card {
                padding: 12px;
            }
        }
        
        /* Print styles */
        @media print {
            body {
                background-color: white;
            }
            
            .no-print {
                display: none !important;
            }
            
            .container {
                width: 100%;
                max-width: none;
                padding: 0;
            }
            
            .calendar-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1 class="title">Reception Dashboard</h1>
            <div class="d-flex gap-3">
                <a href="slot-booking.php" class="btn btn-outline-primary">
                    <i class="fas fa-calendar-plus me-2"></i>New Booking
                </a>
                <button class="btn btn-outline-secondary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i>Print Schedule
                </button>
            </div>
        </header>
        
        <!-- Reception Notice Box -->
        <div class="reception-notice mb-4">
            <div class="reception-notice-title">
                <i class="fas fa-exclamation-circle"></i>
                Reception Staff Notice
            </div>
            <p class="mb-0">You can view all booked appointments and available slots on this dashboard. Only administrators can release new slots for booking. Each slot has a 15-minute duration.</p>
        </div>
        
        <div class="calendar-container">
            <div class="main-calendar">
                <div class="calendar-header">
                    <div class="month-nav">
                        <a href="?month=<?= $month-1 ?>&year=<?= $year ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <span class="mx-3"><?= $monthName ?> <?= $year ?></span>
                        <a href="?month=<?= $month+1 ?>&year=<?= $year ?>" class="btn btn-outline-primary">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                    <div>
                        <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-calendar-day me-2"></i>Today
                        </a>
                    </div>
                </div>
                
                <div class="calendar-grid">
                    <!-- Weekday headers -->
                    <div class="calendar-weekday">Sun</div>
                    <div class="calendar-weekday">Mon</div>
                    <div class="calendar-weekday">Tue</div>
                    <div class="calendar-weekday">Wed</div>
                    <div class="calendar-weekday">Thu</div>
                    <div class="calendar-weekday">Fri</div>
                    <div class="calendar-weekday">Sat</div>
                    
                    <!-- Empty cells for days before the first day of the month -->
                    <?php for ($i = 0; $i < $dayOfWeek; $i++): ?>
                        <div class="calendar-day" style="background-color: #f0f3f9; opacity: 0.5;"></div>
                    <?php endfor; ?>
                    
                    <!-- Calendar days -->
                    <?php 
                    for ($day = 1; $day <= $daysInMonth; $day++): 
                        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $isToday = ($date === date('Y-m-d'));
                        $isSelected = ($date === $selectedDate);
                        $hasAppointments = isset($appointments[$date]) && count($appointments[$date]) > 0;
                    ?>
                        <div class="calendar-day <?= $isToday ? 'today' : '' ?> <?= $isSelected ? 'selected' : '' ?>">
                            <div class="day-number"><?= $day ?></div>
                            <?php if ($hasAppointments): ?>
                                <div class="appointment-indicator"></div>
                                <div class="small text-primary">
                                    <?= count($appointments[$date]) ?> appointments
                                </div>
                            <?php endif; ?>
                            <a href="?date=<?= $date ?>&month=<?= $month ?>&year=<?= $year ?>" class="stretched-link"></a>
                        </div>
                    <?php endfor; ?>
                    
                    <!-- Empty cells for days after the last day of the month -->
                    <?php 
                    $totalCells = $dayOfWeek + $daysInMonth;
                    $remainingCells = 7 - ($totalCells % 7);
                    if ($remainingCells < 7):
                        for ($i = 0; $i < $remainingCells; $i++): 
                    ?>
                        <div class="calendar-day" style="background-color: #f0f3f9; opacity: 0.5;"></div>
                    <?php 
                        endfor;
                    endif;
                    ?>
                </div>
            </div>
        </div>
        
        <?php if ($selectedDate): ?>
            <div class="date-heading mt-4">
                <i class="fas fa-calendar-day me-2"></i>
                Schedule for <?= date('l, F j, Y', strtotime($selectedDate)) ?>
            </div>
            
            <div class="row">
                <div class="col-md-7">
                    <h3 class="section-heading">
                        <i class="fas fa-calendar-check section-icon"></i>
                        Booked Appointments
                    </h3>
                    
                    <div class="appointments-list">
                        <?php if (isset($appointments[$selectedDate]) && count($appointments[$selectedDate]) > 0): ?>
                            <?php foreach($appointments[$selectedDate] as $appointment): ?>
                                <div class="appointment-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="appointment-time">
                                            <?= date('h:i A', strtotime($appointment['formatted_time'])) ?>
                                        </div>
                                        <div>
                                            <span class="status-badge status-<?= $appointment['status'] ?>">
                                                <?= ucfirst($appointment['status']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="appointment-patient">
                                        <?= htmlspecialchars($appointment['patient_name']) ?>
                                    </div>
                                    <div class="appointment-details">
                                        <div><strong>DOB:</strong> <?= date('M j, Y', strtotime($appointment['dob'])) ?></div>
                                        <div><strong>Contact:</strong> <?= htmlspecialchars($appointment['patient_phone']) ?></div>
                                        <div><strong>Email:</strong> <?= htmlspecialchars($appointment['patient_email']) ?></div>
                                        <div><strong>Reason:</strong> <?= htmlspecialchars($appointment['reason_for_visit']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No appointments booked for this date.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <h3 class="section-heading">
                        <i class="fas fa-clock section-icon"></i>
                        Slot Availability
                    </h3>
                    
                    <div class="mb-4">
                        <h4 class="h6 text-primary mb-3">
                            <i class="fas fa-sun me-2"></i>Morning Slots (9:00 AM - 1:00 PM)
                        </h4>
                        
                        <?php
                        $morningSlots = array_filter($availableSlots, function($slot) {
                            $hour = (int)substr($slot, 0, 2);
                            return $hour >= 9 && $hour < 13;
                        });
                        
                        if (count($morningSlots) > 0):
                        ?>
                            <?php foreach($morningSlots as $slot): 
                                $time = date('h:i A', strtotime($slot));
                                
                                // Check if this slot is booked
                                $isBooked = false;
                                if (isset($appointments[$selectedDate])) {
                                    foreach($appointments[$selectedDate] as $appointment) {
                                        if ($appointment['formatted_time'] === $slot) {
                                            $isBooked = true;
                                            break;
                                        }
                                    }
                                }
                            ?>
                                <div class="slot-card">
                                    <div class="slot-time"><?= $time ?></div>
                                    <div class="slot-status <?= $isBooked ? 'slot-booked' : 'slot-available' ?>">
                                        <?= $isBooked ? 'Booked' : 'Available' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                No morning slots have been released for this date.
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="h6 text-primary mb-3">
                            <i class="fas fa-moon me-2"></i>Evening Slots (6:00 PM - 8:00 PM)
                        </h4>
                        
                        <?php
                        $eveningSlots = array_filter($availableSlots, function($slot) {
                            $hour = (int)substr($slot, 0, 2);
                            return $hour >= 18 && $hour <= 20;
                        });
                        
                        if (count($eveningSlots) > 0):
                        ?>
                            <?php foreach($eveningSlots as $slot): 
                                $time = date('h:i A', strtotime($slot));
                                
                                // Check if this slot is booked
                                $isBooked = false;
                                if (isset($appointments[$selectedDate])) {
                                    foreach($appointments[$selectedDate] as $appointment) {
                                        if ($appointment['formatted_time'] === $slot) {
                                            $isBooked = true;
                                            break;
                                        }
                                    }
                                }
                            ?>
                                <div class="slot-card">
                                    <div class="slot-time"><?= $time ?></div>
                                    <div class="slot-status <?= $isBooked ? 'slot-booked' : 'slot-available' ?>">
                                        <?= $isBooked ? 'Booked' : 'Available' ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                No evening slots have been released for this date.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Reception dashboard loaded');
            
            // Add refresh mechanism
            setInterval(function() {
                // Refresh the page every 5 minutes to update appointment data
                location.reload();
            }, 5 * 60 * 1000);
        });
    </script>
</body>
</html>
