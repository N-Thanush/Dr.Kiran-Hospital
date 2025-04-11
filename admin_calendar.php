<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once 'connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Log the unauthorized access attempt
    error_log("Unauthorized access attempt to admin_calendar.php. Session data: " . print_r($_SESSION, true));
    
    // Redirect to login page
    header('Location: admin_login.php?error=not_logged_in');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set header to return JSON
    header('Content-Type: application/json');
    
    // Initialize response array
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate required fields
        $requiredFields = ['patientFirstName', 'patientLastName', 'dobMonth', 'dobDay', 'dobYear', 'contactNumber', 'email', 'reasonForAppointment'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Sanitize and validate input
        $patientFirstName = filter_var($_POST['patientFirstName'], FILTER_SANITIZE_STRING);
        $patientLastName = filter_var($_POST['patientLastName'], FILTER_SANITIZE_STRING);
        $patientPreferredName = filter_var($_POST['patientPreferredName'] ?? '', FILTER_SANITIZE_STRING);
        $contactNumber = filter_var($_POST['contactNumber'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $preferredSpecialty = filter_var($_POST['preferredSpecialty'] ?? '', FILTER_SANITIZE_STRING);
        $reasonForAppointment = filter_var($_POST['reasonForAppointment'], FILTER_SANITIZE_STRING);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Validate date of birth
        $dobMonth = (int)$_POST['dobMonth'];
        $dobDay = (int)$_POST['dobDay'];
        $dobYear = (int)$_POST['dobYear'];
        
        if (!checkdate($dobMonth, $dobDay, $dobYear)) {
            throw new Exception("Please enter a valid date of birth.");
        }

        // Format date of birth
        $dob = sprintf("%04d-%02d-%02d", $dobYear, $dobMonth, $dobDay);

        // Get current date and time
        $appointmentDate = date('Y-m-d');
        $appointmentTime = date('H:i:s');

        // Insert into database
        $query = "INSERT INTO appointments (
            appointment_date,
            appointment_time,
            patient_first_name,
            patient_last_name,
            patient_preferred_name,
            dob,
            contact_number,
            email,
            preferred_specialty,
            reason_for_appointment,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed')";

        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $stmt->bind_param(
            "ssssssssss",
            $appointmentDate,
            $appointmentTime,
            $patientFirstName,
            $patientLastName,
            $patientPreferredName,
            $dob,
            $contactNumber,
            $email,
            $preferredSpecialty,
            $reasonForAppointment
        );

        if (!$stmt->execute()) {
            throw new Exception("Database execute error: " . $stmt->error);
        }

        // Success response
        $response['success'] = true;
        $response['message'] = 'Registration successful!';

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }

    // Send JSON response and exit
    echo json_encode($response);
    exit();
}

// Log successful access
error_log("Admin calendar accessed by: " . ($_SESSION['admin_username'] ?? 'unknown'));

// Get the current month and year
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get the selected date (default to today if not set)
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

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

try {
    // Improved query with detailed appointment information
    $query = "SELECT * FROM appointments 
              WHERE appointment_date BETWEEN ? AND ? 
              ORDER BY appointment_date, appointment_time";
              
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ss", $startDate, $endDate);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    // Store appointments by date
    while ($row = $result->fetch_assoc()) {
        $date = $row['appointment_date'];
        if (!isset($appointments[$date])) {
            $appointments[$date] = [];
        }
        $appointments[$date][] = $row;
    }
    
    // Debug - Log the number of appointments found
    error_log("Found " . count($appointments) . " days with appointments in " . $monthName . " " . $year);
    
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    // Initialize empty array if query fails
    $appointments = [];
}

// Get doctor list (if needed)
$doctors = [];
try {
    $doctorQuery = "SELECT * FROM doctors WHERE status = 'active' ORDER BY last_name";
    $doctorResult = $conn->query($doctorQuery);
    
    if ($doctorResult) {
        while ($row = $doctorResult->fetch_assoc()) {
            $doctors[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
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
    <title>Admin Calendar - Dr. Kiran Neuro Centre</title>
    <link rel="icon" href="img/klogo-.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <link href="css/admin_calendar.css" rel="stylesheet">
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
  <div class="container">
    <header class="header">
      <h1 class="title">Appointment</h1>
      <!-- <button class="check-request-btn">
        <span>+</span>
        Check request
      </button> -->
    </header>
<!--     
    <div class="toolbar">
      <div class="toolbar-right">
        <button class="continue-patient-btn" onclick="openRegistrationModal()">
          <i class="fas fa-user-plus"></i> Continue Patient
        </button>
      </div>
    </div> -->
    
    <div class="calendar-container">
      <div class="sidebar">
        <div class="sidebar-header">
          <div class="sidebar-title">
            <?php echo $monthName . ' ' . $year; ?>
          </div>
          <div class="nav-arrows">
            <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>&date=<?php echo $selectedDate; ?>" class="nav-btn nav-prev">&lt;</a>
            <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>&date=<?php echo $selectedDate; ?>" class="nav-btn">&gt;</a>
          </div>
        </div>
        
        <div class="calendar-grid">
          <div class="weekdays">
            <?php
            $weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($weekDays as $day) {
              echo '<div class="weekday">' . $day . '</div>';
            }
            ?>
          </div>
          
          <div class="days">
            <?php
            // Get the first day of the month
            $firstDay = mktime(0, 0, 0, $month, 1, $year);
            $daysInMonth = date('t', $firstDay);
            $dayOfWeek = date('w', $firstDay);
            
            // Adjust Sunday from 0 to 7
            if ($dayOfWeek == 0) $dayOfWeek = 7;
            
            // Add days from previous month
            $prevMonth = date('t', strtotime('-1 month', $firstDay));
            for ($i = $dayOfWeek - 1; $i > 0; $i--) {
              $prevMonthDate = date('Y-m-d', strtotime('-' . $i . ' days', $firstDay));
              $isSunday = date('w', strtotime($prevMonthDate)) == 0;
              
              if (!$isSunday) {
                echo '<div class="day other-month" data-date="' . $prevMonthDate . '">' . ($prevMonth - $i + 1) . '</div>';
              }
            }
            
            // Current month days
            for ($day = 1; $day <= $daysInMonth; $day++) {
              $currentDate = date('Y-m-d', mktime(0, 0, 0, $month, $day, $year));
              $isSunday = date('w', strtotime($currentDate)) == 0;
              
              if (!$isSunday) {
                $isToday = $currentDate === date('Y-m-d');
                $isSelected = $currentDate === $selectedDate;
                $hasAppointments = isset($appointments[$currentDate]) && count($appointments[$currentDate]) > 0;
                
                $classes = 'day';
                // Only add active and selected classes if this date is selected
                // OR if it's today and no date has been selected yet
                if ($isSelected || ($isToday && !isset($_GET['date']))) {
                  $classes .= ' active selected';
                }
                if ($hasAppointments) {
                  $classes .= ' has-appointments';
                }
                
                echo '<div class="' . $classes . '" data-date="' . $currentDate . '">' . $day . '</div>';
              }
            }
            
            // Add days from next month
            $lastDay = mktime(0, 0, 0, $month, $daysInMonth, $year);
            $remainingCells = 6 * 6 - ($dayOfWeek - 1 + $daysInMonth); // 6 rows x 6 columns (excluding Sunday)
            
            for ($i = 1; $i <= $remainingCells; $i++) {
              $nextMonthDate = date('Y-m-d', strtotime('+' . $i . ' days', $lastDay));
              $isSunday = date('w', strtotime($nextMonthDate)) == 0;
              
              if (!$isSunday) {
                echo '<div class="day other-month" data-date="' . $nextMonthDate . '">' . $i . '</div>';
              }
            }
            ?>
          </div>
        </div>
      </div>
      
      <div class="main-calendar">
        <!-- <div class="main-header">
          <div class="month-nav">
            <div class="month-title">
              <?php
              // Display selected date or current month
              if (isset($_GET['date'])) {
                echo date('F d, Y', strtotime($selectedDate));
              } else {
                echo $monthName . ' ' . $year;
              }
              ?>
            </div>
            <a href="?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>&date=<?php echo date('Y-m-d'); ?>" class="today-btn">Today</a>
            <div class="month-nav-arrows">
              <a href="?month=<?php echo $month-1; ?>&year=<?php echo $year; ?>&date=<?php echo $selectedDate; ?>" class="month-nav-btn">&lt;</a>
              <a href="?month=<?php echo $month+1; ?>&year=<?php echo $year; ?>&date=<?php echo $selectedDate; ?>" class="month-nav-btn">&gt;</a>
            </div>
          </div>
          
          <div class="view-options">
            <div class="view-option">None</div>
            <div class="view-option active">Priority</div>
            <div class="view-option">Deadline</div>
            <button class="calendar-layout-btn">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
              </svg>
            </button>
          </div>
        </div> -->
        
        <div class="calendar-grid-main">
            <section class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div id="calendar"></div>
        </div>
          </div>
          </div>
            </section>
          </div>
        
      
      </div>
    </div>
  </div>

  <!-- Appointment Details Modal -->
  <div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-calendar-check me-2"></i>Appointment Details</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-calendar me-2"></i>Date:</label>
                <p id="appointmentDate"></p>
              </div>
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-clock me-2"></i>Time:</label>
                <p id="appointmentTime"></p>
              </div>
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-user me-2"></i>Patient Name:</label>
                <p id="patientName"></p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-phone me-2"></i>Contact Number:</label>
                <p id="contactNumber"></p>
              </div>
              <div class="mb-3">
                <label class="fw-bold"><i class="fas fa-stethoscope me-2"></i>Preferred Specialty:</label>
                <p id="preferredSpecialty"></p>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="fw-bold"><i class="fas fa-comment-medical me-2"></i>Reason for Visit:</label>
            <p id="reason"></p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Replace the existing registration modal with this updated version -->
  <div id="registrationModal" class="modal">
    <div class="modal-content registration-modal">
        <div class="modal-header">
            <h2>Patient Registration</h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <div class="appointment-summary">
                <h3 class="text-lg font-bold mb-2">Appointment Details</h3>
                <p>Date: <span class="appointment-date"><?php echo date('l, F j, Y'); ?></span></p>
            </div>

            <h2 class="text-xl font-bold mb-4 text-center">Patient Information</h2>
            <form id="registrationForm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- First Name -->
                    <div>
                        <label for="patientFirstName" class="block mb-1 font-medium text-gray-700">First Name *</label>
                        <input type="text" id="patientFirstName" name="patientFirstName" required
                            class="form-control w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <!-- Last Name -->
                    <div>
                        <label for="patientLastName" class="block mb-1 font-medium text-gray-700">Last Name *</label>
                        <input type="text" id="patientLastName" name="patientLastName" required
                            class="form-control w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <!-- Preferred Name -->
                    <div>
                        <label for="patientPreferredName" class="block mb-1 font-medium text-gray-700">Preferred Name (Optional)</label>
                        <input type="text" id="patientPreferredName" name="patientPreferredName"
                            class="form-control w-full px-4 py-2 border rounded-lg">
                    </div>
                    
                    <!-- Date of Birth -->
                    <div>
                        <label class="block mb-1 font-medium text-gray-700">Date of Birth *</label>
                        <div class="grid grid-cols-3 gap-2">
                            <select name="dobMonth" required class="form-control px-2 py-2 border rounded-lg">
                                <option value="">Month</option>
                                <?php
                                $months = [
                                    1 => "January", 2 => "February", 3 => "March", 4 => "April",
                                    5 => "May", 6 => "June", 7 => "July", 8 => "August",
                                    9 => "September", 10 => "October", 11 => "November", 12 => "December"
                                ];
                                foreach ($months as $num => $name) {
                                    echo "<option value=\"$num\">$name</option>";
                                }
                                ?>
                            </select>
                            
                            <select name="dobDay" required class="form-control px-2 py-2 border rounded-lg">
                                <option value="">Day</option>
                                <?php for ($i = 1; $i <= 31; $i++) {
                                    echo "<option value=\"$i\">$i</option>";
                                } ?>
                            </select>
                            
                            <select name="dobYear" required class="form-control px-2 py-2 border rounded-lg">
                                <option value="">Year</option>
                                <?php 
                                $currentYear = date('Y');
                                for ($i = $currentYear; $i >= $currentYear - 100; $i--) {
                                    echo "<option value=\"$i\">$i</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- Contact Number -->
                    <div>
                        <label for="contactNumber" class="block mb-1 font-medium text-gray-700">Contact Number *</label>
                        <input type="tel" id="contactNumber" name="contactNumber" required
                            class="form-control w-full px-4 py-2 border rounded-lg" 
                            placeholder="Contact number">
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block mb-1 font-medium text-gray-700">Email *</label>
                        <input type="email" id="email" name="email" required
                            class="form-control w-full px-4 py-2 border rounded-lg" 
                            placeholder="your@email.com">
                    </div>
                </div>
                
                <!-- Preferred Specialty -->
                <div class="mb-4">
                    <label for="preferredSpecialty" class="block mb-1 font-medium text-gray-700">Preferred Specialty</label>
                    <select id="preferredSpecialty" name="preferredSpecialty" class="form-control w-full px-4 py-2 border rounded-lg">
                        <option value="">Select a specialty (optional)</option>
                        <?php
                        $specialties = [
                            "Headache", "Fits", "Giddiness", "Paralysis", "Memory loss",
                            "Neck and back pain", "Parkinson's", "Hands and legs tingling"
                        ];
                        foreach ($specialties as $specialty) {
                            echo "<option value=\"" . htmlspecialchars($specialty) . "\">" . htmlspecialchars($specialty) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <!-- Reason for Appointment -->
                <div class="mb-6">
                    <label for="reasonForAppointment" class="block mb-1 font-medium text-gray-700">Reason for Appointment *</label>
                    <textarea id="reasonForAppointment" name="reasonForAppointment" rows="4" required
                        class="form-control w-full px-4 py-2 border rounded-lg" 
                        placeholder="Please briefly describe your symptoms or reason for visit"></textarea>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 px-4 rounded-lg font-medium transition">
                    Submit Appointment Request
                </button>
            </form>
        </div>
    </div>
</div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Prevent caching issues
    if (window.history.replaceState) {
      window.history.replaceState(null, null, window.location.href);
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      console.log("Admin calendar loaded successfully");
      
      // Day selection functionality
      const days = document.querySelectorAll('.day');
      days.forEach(day => {
        day.addEventListener('click', function() {
          // Remove active and selected classes from all days
          days.forEach(d => {
            d.classList.remove('active', 'selected');
          });
          
          // Add active and selected classes only to clicked day
          this.classList.add('active', 'selected');
          
          const date = this.getAttribute('data-date');
          if (date) {
            window.location.href = 'admin_calendar.php?date=' + date + '&month=<?php echo $month; ?>&year=<?php echo $year; ?>';
          }
        });
      });
      
      const modal = document.getElementById('appointmentModal');
      const appointments = document.querySelectorAll('.appointment');
      
      if (appointments.length > 0) {
        console.log(`Found ${appointments.length} appointments to display`);
      } else {
        console.log("No appointments found for this view");
      }
      
      appointments.forEach(appointment => {
        appointment.addEventListener('click', function() {
          // Format date for display
          const appointmentDate = this.dataset.date;
          const dateParts = appointmentDate.split('-');
          const formattedDate = new Date(dateParts[0], dateParts[1]-1, dateParts[2])
            .toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
          
          document.getElementById('appointmentDate').textContent = formattedDate;
          document.getElementById('appointmentTime').textContent = this.dataset.time;
          document.getElementById('patientName').textContent = this.dataset.patient;
          document.getElementById('contactNumber').textContent = this.dataset.contact;
          document.getElementById('preferredSpecialty').textContent = this.dataset.specialty;
          document.getElementById('reason').textContent = this.dataset.reason;
          
          console.log(`Displaying details for appointment on ${formattedDate} at ${this.dataset.time}`);
        });
      });
    });

    function openRegistrationModal() {
      document.getElementById('registrationModal').style.display = 'block';
    }

    // Close modal when clicking the X button
    document.querySelector('.close').addEventListener('click', function() {
      document.getElementById('registrationModal').style.display = 'none';
    });

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
      const modal = document.getElementById('registrationModal');
      if (event.target === modal) {
        modal.style.display = 'none';
      }
    });

    // Handle form submission
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData(this);
        
        // Send form data to server
        fetch('admin_calendar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal
                document.getElementById('registrationModal').style.display = 'none';
                // Show success message
                alert('Registration successful!');
                // Refresh the calendar
                location.reload();
            } else {
                alert(data.message || 'Registration failed. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        // Get appointments data from PHP
        let appointments = <?php echo json_encode($appointments ?? []); ?>;
        
        // Format appointments for FullCalendar
        let events = [];
        if (appointments) {
            for (const date in appointments) {
                appointments[date].forEach(appointment => {
                    events.push({
                        title: `${appointment.patient_first_name} ${appointment.patient_last_name}`,
                        start: `${date}T${appointment.appointment_time}`,
                        extendedProps: {
                            specialty: appointment.preferred_specialty,
                            contact: appointment.contact_number,
                            reason: appointment.reason_for_appointment
                        },
                        className: getAppointmentClass(appointment.preferred_specialty)
                    });
                });
            }
        }

        // Initialize FullCalendar
        const calendarEl = document.getElementById('calendar');
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            initialDate: '<?php echo $selectedDate ?? date('Y-m-d'); ?>',
            selectable: true,
            selectMirror: true,
            dayMaxEvents: true,
            events: events,
            height: 'auto',
            firstDay: 1, // Start week on Monday
            dateClick: function(info) {
                const clickedDate = info.dateStr;
                window.location.href = `admin_calendar.php?date=${clickedDate}&month=<?php echo $month; ?>&year=<?php echo $year; ?>`;
            },
            eventClick: function(info) {
                const event = info.event;
                const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
                
                // Update modal content
                document.getElementById('appointmentDate').textContent = formatDate(event.start);
                document.getElementById('appointmentTime').textContent = formatTime(event.start);
                document.getElementById('patientName').textContent = event.title;
                document.getElementById('contactNumber').textContent = event.extendedProps.contact;
                document.getElementById('preferredSpecialty').textContent = event.extendedProps.specialty;
                document.getElementById('reason').textContent = event.extendedProps.reason;
                
                modal.show();
            },
            datesSet: function(info) {
                // Update the current view's dates
                const currentDate = info.view.currentStart;
                const month = currentDate.getMonth() + 1;
                const year = currentDate.getFullYear();
                
                // Only update URL if month/year changed
                if (month != <?php echo $month; ?> || year != <?php echo $year; ?>) {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('month', month);
                    currentUrl.searchParams.set('year', year);
                    window.history.pushState({}, '', currentUrl);
                }
            }
        });
        
        calendar.render();
        
        // Helper function to determine appointment class based on specialty
        function getAppointmentClass(specialty) {
            if (!specialty) return 'appointment-blue';
            
            specialty = specialty.toLowerCase();
            if (specialty.includes('cardiology')) return 'appointment-red';
            if (specialty.includes('pediatrics')) return 'appointment-green';
            if (specialty.includes('neurology')) return 'appointment-purple';
            if (specialty.includes('dental')) return 'appointment-yellow';
            return 'appointment-blue';
        }
        
        // Helper function to format date
        function formatDate(date) {
            return date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        }
        
        // Helper function to format time
        function formatTime(date) {
            return date.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }
    });
  </script>
</body>
</html>