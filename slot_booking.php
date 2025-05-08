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
             AND is_available = 1
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
        } else {
            // A slot is available if is_available = 1 AND is_booked = 0
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

// Convert PHP arrays to JavaScript with proper JSON encoding
$availableSlotsJSON = json_encode($availableSlots);
$bookedSlotsJSON = json_encode($bookedSlots);

// Add cache-busting timestamp
$cacheBuster = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Dr. Kiran Hospital</title>
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .booking-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-group input[type="date"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        .time-slots-section {
            margin: 30px 0;
        }

        .time-slots-section h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .time-slots-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }

        .time-slot {
            padding: 10px;
            text-align: center;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .time-slot:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }

        .time-slot.selected {
            background: #007bff;
            color: white;
            border-color: #0056b3;
        }

        .submit-button {
            width: 100%;
            padding: 12px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .submit-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .submit-button:hover:not(:disabled) {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="booking-form">
            <h2>Book Your Appointment</h2>
            
            <div class="form-group">
                <label for="selected_date">Select Date:</label>
                <input type="date" id="selected_date" name="selected_date" 
                       min="<?php echo date('Y-m-d'); ?>" 
                       value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="time-slots-section">
                <h3>Available Time Slots</h3>
                <div class="time-slots-container">
                    <!-- Time slots will be dynamically inserted here -->
                </div>
            </div>
            
            <button type="submit" class="submit-button" id="continue-button" disabled>
                Continue to Patient Details
            </button>
        </div>
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
        
        // Function to check if a slot is available
        function isSlotAvailable(date, time) {
            const dateStr = formatDate(date);
            const slotKey = `${dateStr} ${time}`;
            return availableSlots.includes(slotKey);
        }
        
        // Function to check if a slot is booked
        function isSlotBooked(date, time) {
            const dateStr = formatDate(date);
            const slotKey = `${dateStr} ${time}`;
            return bookedSlots.includes(slotKey);
        }
        
        // Function to format date as YYYY-MM-DD
        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }
        
        // Function to generate time slots for a specific date
        function generateTimeSlots(date) {
            const slots = [];
            const dateStr = formatDate(date);
            
            // Morning slots (9:00 AM - 1:00 PM)
            for (let hour = 9; hour < 13; hour++) {
                for (let minute = 0; minute < 60; minute += 15) {
                    const timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')} AM`;
                    if (isSlotAvailable(date, timeStr)) {
                        slots.push(timeStr);
                    }
                }
            }
            
            // Evening slots (6:00 PM - 8:00 PM)
            for (let hour = 18; hour < 20; hour++) {
                for (let minute = 0; minute < 60; minute += 15) {
                    const timeStr = `${(hour - 12).toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')} PM`;
                    if (isSlotAvailable(date, timeStr)) {
                        slots.push(timeStr);
                    }
                }
            }
            
            return slots;
        }
        
        // Function to update the time slots display
        function updateTimeSlots(date) {
            const timeSlotsContainer = document.querySelector('.time-slots-container');
            if (!timeSlotsContainer) return;
            
            // Clear existing slots
            timeSlotsContainer.innerHTML = '';
            
            // Generate and display slots
            const slots = generateTimeSlots(date);
            console.log("Generating slots for date:", formatDate(date));
            console.log("Available slots:", slots);
            
            slots.forEach(timeStr => {
                const slotElement = document.createElement('div');
                slotElement.className = 'time-slot';
                slotElement.textContent = timeStr;
                
                // Add click handler
                slotElement.addEventListener('click', function() {
                    document.querySelectorAll('.time-slot').forEach(s => s.classList.remove('selected'));
                    this.classList.add('selected');
                    selectedTimeSlot = timeStr;
                    document.getElementById('continue-button').disabled = false;
                });
                
                timeSlotsContainer.appendChild(slotElement);
            });
        }
        
        // Initialize the calendar
        function initCalendar() {
            // Set up date selection
            const dateInput = document.getElementById('selected_date');
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    selectedDate = new Date(this.value);
                    updateTimeSlots(selectedDate);
                });
            }
            
            // Initial update
            updateTimeSlots(today);
        }
        
        // Start the timer when the page loads
        window.addEventListener('DOMContentLoaded', function() {
            initCalendar();
        });
    </script>
</body>
</html> 