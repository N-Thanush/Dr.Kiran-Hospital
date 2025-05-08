<?php
session_start();
require_once 'connect.php'; // Changed from config/database.php to connect.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get selected date from query parameter or use today
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Fetch all appointments for the selected date with proper time formatting
$query = "SELECT 
            appointment_time,
            TIME_FORMAT(STR_TO_DATE(appointment_time, '%H:%i:%s'), '%h:%i %p') as formatted_time,
            is_available,
            is_booked,
            patient_name,
            patient_phone,
            status,
            preferred_specialty,
            reason
          FROM appointments 
          WHERE appointment_date = ?
          ORDER BY STR_TO_DATE(appointment_time, '%H:%i:%s')";

        $stmt = $conn->prepare($query);
$stmt->bind_param("s", $selected_date);
$stmt->execute();
    $result = $stmt->get_result();
    
$appointments = [];
    while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

// Debug logging
error_log("Selected date: " . $selected_date);
error_log("Number of appointments: " . count($appointments));
foreach ($appointments as $appointment) {
    error_log("Slot: " . $appointment['formatted_time'] .
        " - Available: " . ($appointment['is_available'] ? 'Yes' : 'No') .
        " - Booked: " . ($appointment['is_booked'] ? 'Yes' : 'No'));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Calendar - Dr. Kiran Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
      body {
            background-color: #f8f9fa;
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
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .nav-link:hover {
            color: white !important;
        }

        .card {
            border: none;
          border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
      }

        .card-header {
            background: #4834d4;
          color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }

        .slot {
            padding: 15px;
            margin: 10px 0;
          border-radius: 10px;
            border: 1px solid #dee2e6;
        }

        .slot-available {
            background-color: #e8f5e9;
            border-color: #c8e6c9;
        }

        .slot-booked {
            background-color: #fff3e0;
            border-color: #ffe0b2;
        }

        .slot-unavailable {
            background-color: #ffebee;
            border-color: #ffcdd2;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
          border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-available {
            background-color: #4caf50;
        }

        .status-booked {
            background-color: #ff9800;
        }

        .status-unavailable {
            background-color: #f44336;
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
                        <a class="nav-link" href="manage_slots.php">
                            <i class="fas fa-clock me-1"></i>Manage Slots
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
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Appointment Calendar
                    </h3>
                    <a href="manage_slots.php" class="btn btn-light">
                        <i class="fas fa-clock me-1"></i>Manage Slots
                    </a>
        </div>
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
                        <h5><i class="fas fa-sun me-2"></i>Morning Slots (10:00 AM - 1:00 PM)</h5>
                <?php
                        $morning_slots_found = false;
                        foreach ($appointments as $appointment) {
                            $time = strtotime($appointment['appointment_time']);
                            if ($time >= strtotime('10:00:00') && $time < strtotime('13:00:00')) {
                                $morning_slots_found = true;

                                // Determine slot status
                                if ($appointment['is_booked']) {
                                    $status_class = 'slot-booked';
                                    $status_indicator = 'status-booked';
                                    $status_text = 'Booked';
                                    $status_badge = 'bg-warning';
                                } else if ($appointment['is_available']) {
                                    $status_class = 'slot-available';
                                    $status_indicator = 'status-available';
                                    $status_text = 'Available';
                                    $status_badge = 'bg-success';
                                } else {
                                    $status_class = 'slot-unavailable';
                                    $status_indicator = 'status-unavailable';
                                    $status_text = 'Not Available';
                                    $status_badge = 'bg-danger';
                                }
                                ?>
                                <div class="slot <?php echo $status_class; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="status-indicator <?php echo $status_indicator; ?>"></span>
                                            <strong><?php echo $appointment['formatted_time']; ?></strong>
              </div>
                                        <div>
                                            <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
            </div>
        </div>
                                    <?php if ($appointment['is_booked'] && $appointment['patient_name']): ?>
                                        <div class="mt-2">
                                            <div class="appointment-details">
                                                <div><strong>Patient:</strong>
                                                    <?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                                <div><strong>Phone:</strong>
                                                    <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                                                <div><strong>Specialty:</strong>
                                                    <?php echo htmlspecialchars($appointment['preferred_specialty']); ?></div>
                                                <div><strong>Reason:</strong>
                                                    <?php echo htmlspecialchars($appointment['reason']); ?></div>
      </div>
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
                        <h5><i class="fas fa-moon me-2"></i>Evening Slots (6:00 PM - 8:00 PM)</h5>
                                <?php 
                        $evening_slots_found = false;
                        foreach ($appointments as $appointment) {
                            $time = strtotime($appointment['appointment_time']);
                            if ($time >= strtotime('18:00:00') && $time < strtotime('20:00:00')) {
                                $evening_slots_found = true;

                                // Determine slot status
                                if ($appointment['is_booked']) {
                                    $status_class = 'slot-booked';
                                    $status_indicator = 'status-booked';
                                    $status_text = 'Booked';
                                    $status_badge = 'bg-warning';
                                } else if ($appointment['is_available']) {
                                    $status_class = 'slot-available';
                                    $status_indicator = 'status-available';
                                    $status_text = 'Available';
                                    $status_badge = 'bg-success';
                                } else {
                                    $status_class = 'slot-unavailable';
                                    $status_indicator = 'status-unavailable';
                                    $status_text = 'Not Available';
                                    $status_badge = 'bg-danger';
                                }
                                ?>
                                <div class="slot <?php echo $status_class; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="status-indicator <?php echo $status_indicator; ?>"></span>
                                            <strong><?php echo $appointment['formatted_time']; ?></strong>
                        </div>
                                        <div>
                                            <span class="badge <?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                    </div>
                </div>
                                    <?php if ($appointment['is_booked'] && $appointment['patient_name']): ?>
                                        <div class="mt-2">
                                            <div class="appointment-details">
                                                <div><strong>Patient:</strong>
                                                    <?php echo htmlspecialchars($appointment['patient_name']); ?></div>
                                                <div><strong>Phone:</strong>
                                                    <?php echo htmlspecialchars($appointment['patient_phone']); ?></div>
                                                <div><strong>Specialty:</strong>
                                                    <?php echo htmlspecialchars($appointment['preferred_specialty']); ?></div>
                                                <div><strong>Reason:</strong>
                                                    <?php echo htmlspecialchars($appointment['reason']); ?></div>
                    </div>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
        document.getElementById('dateSelector').addEventListener('change', function () {
            window.location.href = 'admin_calendar.php?date=' + this.value;
    });
  </script>
</body>

</html>