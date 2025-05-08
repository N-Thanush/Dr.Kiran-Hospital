<?php 
// Start session with secure cookie settings
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

require 'connect.php';
require 'config.php';

// Disable error display in output and enable error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Define reCAPTCHA keys
define('RECAPTCHA_SITE_KEY', '6LfAuCArAAAAAPyg-uKrQDXm4qbJ2sSPtHCk3nz2');
define('RECAPTCHA_SECRET_KEY', '6LfAuCArAAAAAENL0xX-mxTTh8Yp-ueInfe7kXi6');

// Function to handle JSON responses with proper headers
function sendJsonResponse($success, $message = '', $errors = []) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set security and cache headers
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Access-Control-Allow-Origin: same-origin');
    
    // Create response array
    $response = [
        'success' => $success,
        'message' => $message,
        'errors' => $errors,
        'timestamp' => time()
    ];
    
    // Log response for debugging
    error_log("Sending JSON response: " . json_encode($response));
    
    // Send JSON response
    echo json_encode($response);
    exit;
}

// Function to verify reCAPTCHA response
function verifyRecaptcha($recaptchaResponse) {
    try {
        if (empty($recaptchaResponse)) {
            return false;
        }

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $recaptchaResponse,
            'remoteip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            error_log('reCAPTCHA verification failed: Unable to connect to verification service');
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('reCAPTCHA verification failed: Invalid JSON response');
            return false;
        }
        
        error_log('reCAPTCHA verification result: ' . print_r($result, true));
        
        return isset($result['success']) && $result['success'] === true;
    } catch (Exception $e) {
        error_log('reCAPTCHA verification error: ' . $e->getMessage());
        return false;
    }
}

// Initialize variables to retain form values after submission
$patientFirstName = $patientLastName = $patientPreferredName = $contactNumber = '';
$dobMonth = $dobDay = $dobYear = $preferredSpecialty = $reasonForAppointment = '';
$errors = [];
$formSubmitted = false;

// Check if we have appointment data in session
if (!isset($_SESSION['appointment_date']) || !isset($_SESSION['appointment_time'])) {
    $_SESSION['error'] = "Please select an appointment time first.";
    header("Location: slot-booking.php");
    exit;
}

$appointmentDate = $_SESSION['appointment_date'];
$appointmentTime = $_SESSION['appointment_time'];

// Verify the slot is still available
try {
    // Log appointment data for debugging
    error_log("Checking slot availability for: Date: " . $appointmentDate . ", Time: " . $appointmentTime);
    
    $checkQuery = "SELECT * FROM appointments 
                  WHERE appointment_date = ? 
                  AND (appointment_time = STR_TO_DATE(?, '%h:%i %p') OR TIME_FORMAT(appointment_time, '%h:%i %p') = ?)
                  AND is_available = TRUE 
                  AND is_booked = FALSE";
    
    $checkStmt = $conn->prepare($checkQuery);
    if (!$checkStmt) {
        throw new Exception("Database error while checking slot availability: " . $conn->error);
    }
    
    $checkStmt->bind_param("sss", $appointmentDate, $appointmentTime, $appointmentTime);
    if (!$checkStmt->execute()) {
        throw new Exception("Error verifying slot availability: " . $checkStmt->error);
    }
    
    $result = $checkStmt->get_result();
    
    // Log the result for debugging
    error_log("Slot availability check result: " . $result->num_rows . " rows found");
    
    if ($result->num_rows === 0) {
        // Log additional details for better debugging
        error_log("Slot availability check failed:");
        error_log("- Appointment Date: " . $appointmentDate); 
        error_log("- Appointment Time: " . $appointmentTime);
        error_log("- SQL Query: " . $checkQuery);
        error_log("- Prepared Statement Error: " . $checkStmt->error);
        
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            sendJsonResponse(false, "This slot is no longer available. Please select another time slot.");
            exit;
        } else {
            // Store error in session and redirect
            $_SESSION['booking_error'] = "This appointment slot is no longer available. Please select a different date/time.";
    header("Location: slot-booking.php");
    exit;
}
    }
    // Check if slot is in the past
    $slotDateTime = new DateTime($appointmentDate . ' ' . $appointmentTime);
    $now = new DateTime();
    if ($slotDateTime < $now) {
        // Check if it's an AJAX request
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            sendJsonResponse(false, "Cannot book appointments in the past. Please select a future time.");
            exit;
        } else {
            $_SESSION['booking_error'] = "Cannot book appointments in the past. Please select a future time.";
            header("Location: slot-booking.php");
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Error in registration form: " . $e->getMessage());
    
    // Check if it's an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        sendJsonResponse(false, "An error occurred while processing your request. Please try again.");
        exit;
    } else {
        $_SESSION['booking_error'] = "An error occurred. Please try again.";
        header("Location: slot-booking.php");
        exit;
    }
}

// Format the date for display
$formattedDate = date('l, F j, Y', strtotime($appointmentDate));

// Standardize time format for consistency
function standardizeTimeFormat($timeStr) {
    // Remove extra spaces
    $timeStr = trim($timeStr);
    
    // Check if there's a space before AM/PM
    if (preg_match('/(\d+:\d+)\s*(AM|PM)/i', $timeStr, $matches)) {
        return $matches[1] . ' ' . strtoupper($matches[2]);
    }
    
    // If no space before AM/PM, add one
    if (preg_match('/(\d+:\d+)(AM|PM)/i', $timeStr, $matches)) {
        return $matches[1] . ' ' . strtoupper($matches[2]);
    }
    
    // Return original if no pattern matched
    return $timeStr;
}

// Standardize the appointment time format
$appointmentTime = standardizeTimeFormat($appointmentTime);

// Enhanced test_input function
function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check early if it's an AJAX request for better handling
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Set content type for AJAX requests
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    try {
        // Validate inputs with enhanced sanitization
        $firstName = isset($_POST['first_name']) ? test_input($_POST['first_name']) : '';
        $lastName = isset($_POST['last_name']) ? test_input($_POST['last_name']) : '';
        $phone = isset($_POST['phone']) ? test_input($_POST['phone']) : '';
        $reason = isset($_POST['reason']) ? test_input($_POST['reason']) : '';
        $specialty = isset($_POST['specialty']) ? test_input($_POST['specialty']) : '';

        // Initialize errors array
        $errors = [];

        // Validate required fields
        if (empty($firstName)) $errors['first_name'] = "First name is required";
        if (empty($lastName)) $errors['last_name'] = "Last name is required";
        if (empty($phone)) $errors['phone'] = "Phone number is required";
        if (empty($specialty)) $errors['specialty'] = "Preferred specialty is required";
        if (empty($reason)) $errors['reason'] = "Reason for appointment is required";

        // Verify appointment data exists in session
        if (!isset($_SESSION['appointment_date']) || !isset($_SESSION['appointment_time'])) {
            throw new Exception("Appointment date and time are required. Please select a slot first.");
        }

        $appointmentDate = $_SESSION['appointment_date'];
        $appointmentTime = $_SESSION['appointment_time'];

        // Verify reCAPTCHA
        $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
        if (empty($recaptchaResponse)) {
            $errors['recaptcha'] = "Please complete the reCAPTCHA verification";
        } else if (!verifyRecaptcha($recaptchaResponse)) {
            $errors['recaptcha'] = "reCAPTCHA verification failed";
        }

        if (empty($errors)) {
            // Start transaction
            $conn->begin_transaction();

            try {
                // Check if slot is still available with FOR UPDATE lock
                $checkQuery = "SELECT id FROM appointments 
                           WHERE appointment_date = ? 
                           AND (TIME_FORMAT(appointment_time, '%h:%i %p') = ? OR appointment_time = STR_TO_DATE(?, '%h:%i %p'))
                           AND is_available = 1 
                           AND is_booked = 0
                           FOR UPDATE";
            
                $checkStmt = $conn->prepare($checkQuery);
            if (!$checkStmt) {
                    throw new Exception("Database prepare error: " . $conn->error);
            }
            
            $checkStmt->bind_param("sss", $appointmentDate, $appointmentTime, $appointmentTime);
                
                if (!$checkStmt->execute()) {
                    throw new Exception("Error checking slot availability: " . $checkStmt->error);
                }

            $result = $checkStmt->get_result();
            
                if ($result->num_rows === 0) {
                    throw new Exception("This slot is no longer available. Please select another time slot.");
            }

                $slotData = $result->fetch_assoc();

                // Prepare patient data
                $fullName = $firstName . ' ' . $lastName;
                $currentDateTime = date('Y-m-d H:i:s');
            
                // Book the appointment with updated fields
                $bookQuery = "UPDATE appointments 
                            SET patient_name = ?,
                                patient_phone = ?,
                                reason = ?,
                                preferred_specialty = ?,
                                is_booked = 1,
                                status = 'confirmed'
                            WHERE id = ? 
                            AND is_booked = 0 
                            AND is_available = 1";
            
                $bookStmt = $conn->prepare($bookQuery);
                if (!$bookStmt) {
                    throw new Exception("Database prepare error: " . $conn->error);
            }
            
                $bookStmt->bind_param("ssssi", 
                    $fullName,      // patient_name 
                    $phone,         // patient_phone
                    $reason,        // reason
                    $specialty,     // preferred_specialty
                    $slotData['id'] // id
            );
            
                if (!$bookStmt->execute()) {
                    throw new Exception("Error booking appointment: " . $bookStmt->error);
            }
            
                if ($bookStmt->affected_rows === 0) {
                    throw new Exception("Failed to book appointment. The slot may have been taken.");
                }
            
                // Log successful booking
                error_log("Appointment booked successfully:");
                error_log("- Patient: " . $fullName);
                error_log("- Date: " . $appointmentDate);
                error_log("- Time: " . $appointmentTime);
                error_log("- Slot ID: " . $slotData['id']);
            
                // Commit transaction
            $conn->commit();
            
                // Store success data in session
                $_SESSION['booking_success'] = [
                    'patient_name' => $fullName,
                    'appointment_date' => $appointmentDate,
                    'appointment_time' => $appointmentTime,
                    'phone' => $phone,
                    'reason' => $reason,
                    'specialty' => $specialty
                ];
            
            // Clear appointment session data
            unset($_SESSION['appointment_date']);
            unset($_SESSION['appointment_time']);
            
                // Send JSON response for AJAX requests
                if ($isAjax) {
                    sendJsonResponse(true, "Appointment booked successfully!");
                } else {
                    // Redirect to success page for normal form submission
                    header("Location: booking-success.php");
                    exit;
                }
            
        } catch (Exception $e) {
            $conn->rollback();
                error_log("Database error during booking: " . $e->getMessage());
                if ($isAjax) {
                    sendJsonResponse(false, $e->getMessage());
            } else {
                    $errors['database'] = $e->getMessage();
                }
            }
        }

        // If there are errors, handle them appropriately
        if (!empty($errors)) {
            if ($isAjax) {
                sendJsonResponse(false, "Form validation failed", $errors);
            } else {
                $_SESSION['form_errors'] = $errors;
                $_SESSION['form_data'] = $_POST;
                header("Location: registration-form.php");
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Critical error in form processing: " . $e->getMessage());
        if ($isAjax) {
            sendJsonResponse(false, "An unexpected error occurred");
        } else {
            $_SESSION['error'] = "An unexpected error occurred. Please try again.";
            header("Location: registration-form.php");
    exit;
}
    }
}

// Get any stored form data and errors
$formData = $_SESSION['form_data'] ?? [];
$formErrors = $_SESSION['form_errors'] ?? [];

// Clear stored form data and errors
unset($_SESSION['form_data']);
unset($_SESSION['form_errors']);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$months = [
    1 => "January", 2 => "February", 3 => "March", 4 => "April",
    5 => "May", 6 => "June", 7 => "July", 8 => "August",
    9 => "September", 10 => "October", 11 => "November", 12 => "December"
];

$specialties = [
    "Headache", "Fits", "Giddiness", "Paralysis", "Memory loss",
    "Neck and back pain", "Parkinson's", "Hands and legs tingling"
];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Special case for direct reCAPTCHA verification requests
    if (isset($_POST['g-recaptcha-response']) && isset($_POST['verify_only']) && $_POST['verify_only'] === 'true') {
        // Set proper content type header
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $recaptcha_response = $_POST['g-recaptcha-response'];
            $verified = verifyRecaptcha($recaptcha_response);
            
            if ($verified) {
                echo json_encode(['success' => true, 'message' => 'reCAPTCHA verified successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed']);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit; // Ensure we exit here
    }
    
    // All other AJAX requests will be handled by the main form processing code above
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
    <title>Patient Registration - Dr. Kiran Hospitals</title>
    <!-- Update reCAPTCHA script to load properly - use standard API instead of explicit render -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <link rel="stylesheet" href="css/animations.css"><?php // Include our custom animations ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/coreui@4.2.6/dist/css/coreui.min.css">
    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@4.2.6/dist/js/coreui.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@coreui/calendar@4.2.6/dist/calendar.min.js"></script>
    <style>
        /* Close button styles */
        .close-button {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #7047d1;
            color: white;
            border: none;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.2s ease;
            z-index: 1000;
        }
        
        .close-button:hover {
            background-color: #5d3cb5;
        }
        
        /* Position relative for the container to properly position the close button */
        .bg-white {
            position: relative;
        }

        /* Existing styles */
        .error-message {
            color: #dc3545;
            font-size: 0.9375rem;
            margin-top: 0.25rem;
            margin-bottom: 0.25rem;
            display: block;
            clear: both;
        }
        .form-control.is-invalid {
            border-color: #dc3545 !important;
            border-width: 2px !important;
            background-image: none !important;
        }
        .appointment-summary {
            background-color: #f4e6fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
            border-left: 4px solid #7047d1;
            font-size: 1rem;
        }
        .appointment-date {
            font-weight: 700;
            color: #7047d1;
            font-size: 1.05rem;
        }
        .appointment-time {
            font-weight: 700;
            color: #7047d1;
            font-size: 1.05rem;
        }
        body {
            margin: 0;
            padding: 12px;
            background: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 100%;
            padding: 0;
        }
        .card {
            margin: 0;
            box-shadow: none;
            padding: 0.5rem;
        }
        .btn {
            padding: 0.5rem 1rem;
        }
        @media (max-width: 768px) {
            body {
                padding: 6px;
            }
            .card {
                padding: 0.5rem;
            }
            .form-group {
                margin-bottom: 0.75rem;
                position: relative;
            }
        }
        .spinner-border-sm {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 0.2em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border .75s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }
        
        /* Add styles for success animation container */
        .success-animation {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            max-width: 300px;
        }
        
        .hidden {
            display: none;
        }
        
        /* Form field containers - REDUCED MARGINS */
        .form-field-container {
            margin-bottom: 0.5rem;
            position: relative;
        }
        
        /* Validation icon positioning - REMOVED */
        .validation-icon {
            display: none; /* Hide validation icons */
        }
        
        /* Alternative error indication with border */
        .form-control.is-invalid {
            border-color: #dc3545 !important;
            border-width: 2px !important;
            background-image: none !important;
        }
        
        /* Ensure error messages appear below inputs - REDUCED HEIGHT */
        .error-message-container {
            min-height: 16px;
            margin-top: 0.125rem;
        }
        
        /* Form grid spacing */
        .form-grid {
            display: grid;
            grid-gap: 0.5rem;
        }
        
        /* Reduce label spacing */
        label {
            margin-bottom: 0.125rem !important;
            display: block;
            font-size: 0.9375rem !important;
            font-weight: 600 !important;
            color: #4a5568;
        }
        
        /* Form inputs padding */
        .form-control {
            padding: 0.375rem 0.625rem !important;
            height: auto !important;
            border-radius: 6px !important;
            border: 1px solid #e2e8f0 !important;
            transition: border-color 0.15s ease-in-out;
            font-weight: 500;
            font-size: 1rem !important;
        }
        
        .form-control:focus {
            border-color: #7047d1 !important;
            box-shadow: 0 0 0 3px rgba(112, 71, 209, 0.15) !important;
            outline: none;
        }
        
        /* Content spacing */
        .bg-white {
            padding: 1rem !important;
            border-radius: 8px !important;
        }
        
        /* Section headers */
        h2.text-xl {
            margin-bottom: 0.75rem !important;
            color: #4a5568;
            font-size: 1.375rem !important;
            font-weight: 700 !important;
        }
        
        /* Adjust spacing for DOB selects */
        .dob-container select {
            padding: 0.375rem 0.5rem !important;
        }
        
        /* Submit button styling */
        button[type="submit"] {
            background-color: #7047d1 !important;
            transition: all 0.2s ease;
            font-weight: 600 !important;
            letter-spacing: 0.025em;
            font-size: 1.0625rem !important;
        }
        
        button[type="submit"]:hover {
            background-color: #5d3cb5 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(112, 71, 209, 0.1);
        }
        
        /* Form sections */
        .form-section {
            margin-bottom: 0.75rem;
            
        }
        
        .form-section-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 0.5rem;
        }
        
        textarea.form-control {
            min-height: 70px;
        }
        
        /* Show form message */
        #formMessage {
            font-size: 1rem !important;
            padding: 0.5rem 1rem !important;
        }
        
        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        
        .time-slot {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .time-slot:hover {
            background-color: #f0f0f0;
        }
        
        .time-slot.selected {
            background-color: #7047d1;
            color: white;
            border-color: #7047d1;
        }
        
        .time-slot.booked {
            background-color: #e9ecef;
            color: #adb5bd;
            cursor: not-allowed;
            border-color: #dee2e6;
        }

        .captcha-container {
            margin: 20px 0;
            display: flex;
            justify-content: center;
        }

        .g-recaptcha {
            transform-origin: center;
            -webkit-transform-origin: center;
        }

        @media (max-width: 480px) {
            .g-recaptcha {
                transform: scale(0.9);
                -webkit-transform: scale(0.9);
            }
        }

        /* Add specific styles for reCAPTCHA container */
        #recaptcha-container {
            display: flex;
            justify-content: center;
            margin: 20px 0;
            min-height: 78px; /* Height of reCAPTCHA */
        }
    </style>
</head>
<body class="bg-gray-100 flex justify-center items-center min-h-screen py-4">
    <div class="bg-white p-4 rounded-lg shadow-lg w-full max-w-lg mx-4">
        <button class="close-button" onclick="window.location.href='slot-booking.php';">×</button>
        <?php if (isset($_SESSION['success'])): ?>
            <!-- Success message -->
            <div class="text-center py-8">
                <svg class="mx-auto h-16 w-16 text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="text-2xl font-bold mb-2 text-green-600"><?php echo $_SESSION['success']; ?></h2>
                <a href="index.php" class="bg-blue-600 text-white py-2 px-4 rounded hover:bg-blue-700 transition">Return to Home</a>
            </div>
        <?php else: ?>
            <!-- Appointment Summary -->
            <div style="text-align:center; margin-bottom: 18px;">
                <img src="img/klogo-.png" alt="Kiran Hospital Logo" style="max-width: 120px; height: auto; display: inline-block;">
            </div>
            <div class="appointment-summary">
                <h3 class="text-lg font-bold mb-1">Appointment Details</h3>
                <p class="font-medium">Date: <span class="appointment-date"><?php echo htmlspecialchars($formattedDate); ?></span></p>
                <p class="font-medium">Time: <span class="appointment-time"><?php echo htmlspecialchars($appointmentTime); ?></span></p>
            </div>

            <h2 class="text-xl font-bold mb-3 text-center">Patient Information</h2>
            
            <!-- Success animation container (hidden by default) -->
            <div id="successAnimation" class="success-animation hidden fade-in">
                <dotlottie-player src="https://lottie.host/66a77625-724a-4d65-ae1e-4011b2c2aa67/MUBSjtzlHu.lottie" background="transparent" speed="1" style="width: 300px; height: 300px" loop autoplay></dotlottie-player>
                <h3 class="text-xl font-bold text-green-600 mt-4">Appointment Booked Successfully!</h3>
                <p class="text-gray-600 mb-2">Your appointment has been confirmed.</p>
                <p class="text-gray-500 text-sm">You will be redirected to confirmation page...</p>
            </div>
            
            <form id="registrationForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" onsubmit="return validateForm()">
                <!-- Hidden fields to preserve appointment information -->
                <input type="hidden" name="appointmentDate" value="<?php echo htmlspecialchars($appointmentDate); ?>">
                <input type="hidden" name="appointmentTime" value="<?php echo htmlspecialchars($appointmentTime); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <!-- Form message container for showing success/error messages -->
                <div id="formMessage" class="hidden mb-3 p-2 rounded"></div>

                <div class="form-section">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-2 form-grid">
                        <!-- First Name -->
                        <div class="form-field-container">
                            <label for="patientFirstName" class="block mb-1 font-semibold text-gray-700">First Name *</label>
                            <div class="relative">
                                <input type="text" id="patientFirstName" name="first_name" value="<?php echo htmlspecialchars($patientFirstName); ?>" 
                                    class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($formErrors['first_name']) ? 'is-invalid border-red-500' : ''; ?>"
                                    placeholder="Enter first name">
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($formErrors['first_name'])): ?>
                                    <div class="error-message"><?php echo $formErrors['first_name']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Last Name -->
                        <div class="form-field-container">
                            <label for="patientLastName" class="block mb-1 font-semibold text-gray-700">Last Name *</label>
                            <div class="relative">
                                <input type="text" id="patientLastName" name="last_name" value="<?php echo htmlspecialchars($patientLastName); ?>" 
                                    class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($formErrors['last_name']) ? 'is-invalid border-red-500' : ''; ?>"
                                    placeholder="Enter last name">
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($formErrors['last_name'])): ?>
                                    <div class="error-message"><?php echo $formErrors['last_name']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Preferred Name -->
                        <div class="form-field-container">
                            <label for="patientPreferredName" class="block mb-1 font-semibold text-gray-700">Preferred Name (Optional)</label>
                            <input type="text" id="patientPreferredName" name="preferred_name" value="<?php echo htmlspecialchars($patientPreferredName); ?>" 
                                class="form-control w-full px-4 py-2 border rounded-lg"
                                placeholder="Nickname (optional)">
                        </div>
                        
                        <!-- Date of Birth -->
                        <div class="form-field-container">
                            <label class="block mb-1 font-semibold text-gray-700">Date of Birth *</label>
                            <div class="grid grid-cols-3 gap-1 dob-container">
                                <select name="dob_month" class="form-control px-2 py-2 border rounded-lg <?php echo isset($formErrors['dob']) ? 'is-invalid border-red-500' : ''; ?>">
                                    <option value="">Month</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $dobMonth == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <select name="dob_day" class="form-control px-2 py-2 border rounded-lg <?php echo isset($formErrors['dob']) ? 'is-invalid border-red-500' : ''; ?>">
                                    <option value="">Day</option>
                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $dobDay == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                                
                                <select name="dob_year" class="form-control px-2 py-2 border rounded-lg <?php echo isset($formErrors['dob']) ? 'is-invalid border-red-500' : ''; ?>">
                                    <option value="">Year</option>
                                    <?php 
                                    $currentYear = date('Y');
                                    for ($i = $currentYear; $i >= $currentYear - 100; $i--): 
                                    ?>
                                        <option value="<?php echo $i; ?>" <?php echo $dobYear == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($formErrors['dob'])): ?>
                                    <div class="error-message"><?php echo $formErrors['dob']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-2 form-grid">
                        <!-- Contact Number -->
                        <div class="form-field-container">
                            <label for="contactNumber" class="block mb-1 font-semibold text-gray-700">Contact Number *</label>
                            <div class="relative">
                                <input type="tel" id="contactNumber" name="phone" value="<?php echo htmlspecialchars($contactNumber); ?>" 
                                    class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($formErrors['phone']) ? 'is-invalid border-red-500' : ''; ?>" 
                                    placeholder="Phone number">
                            </div>
                            <div class="error-message-container">
                                <?php if (isset($formErrors['phone'])): ?>
                                    <div class="error-message"><?php echo $formErrors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <!-- Preferred Specialty -->
                    <div class="form-field-container mb-2">
                        <label for="preferredSpecialty" class="block mb-1 font-semibold text-gray-700">Preferred Specialty *</label>
                        <select id="preferredSpecialty" name="specialty" class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($formErrors['specialty']) ? 'is-invalid border-red-500' : ''; ?>" required>
                            <option value="">Select a specialty</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo htmlspecialchars($specialty); ?>" <?php echo $preferredSpecialty == $specialty ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($specialty); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Reason for Appointment -->
                    <div class="form-field-container mb-3">
                        <label for="reasonForAppointment" class="block mb-1 font-semibold text-gray-700">Reason for Appointment *</label>
                        <div class="relative">
                            <textarea id="reasonForAppointment" name="reason" rows="3" 
                                class="form-control w-full px-4 py-2 border rounded-lg <?php echo isset($formErrors['reason']) ? 'is-invalid border-red-500' : ''; ?>" 
                                placeholder="Describe your symptoms or reason for visit"><?php echo htmlspecialchars($reasonForAppointment); ?></textarea>
                        </div>
                        <div class="error-message-container">
                            <?php if (isset($formErrors['reason'])): ?>
                                <div class="error-message"><?php echo $formErrors['reason']; ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- General error message for appointment slot -->
                <?php if (isset($formErrors['appointmentSlot'])): ?>
                    <div class="mb-3 p-2 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?php echo $formErrors['appointmentSlot']; ?>
                    </div>
                <?php endif; ?>

                <!-- Add standard reCAPTCHA container -->
                <div class="mb-4 flex justify-center">
                    <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>" data-callback="enableSubmit" data-expired-callback="disableSubmit"></div>
                    </div>
                    
                <!-- reCAPTCHA error message -->
                <div class="error-message-container mt-2">
                    <div class="error-message" id="recaptchaError" style="display: none;"></div>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="submitBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg font-semibold transition" disabled>
                    Submit Appointment Request
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        // Initialize submit button as disabled
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        });
            
        // Handle form submission
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
            // Validate form first
                    if (!validateForm()) {
                        return false;
                    }

            // Check if reCAPTCHA is completed
            const recaptchaResponse = grecaptcha.getResponse();
                    if (!recaptchaResponse) {
                showRecaptchaError('Please complete the reCAPTCHA verification');
                        return false;
                    }
                    
                        // Show loading state
            const submitBtn = document.getElementById('submitBtn');
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border-sm"></span> Processing...';
                        
            // Get hidden appointment data
            const appointmentDate = document.querySelector('input[name="appointmentDate"]').value;
            const appointmentTime = document.querySelector('input[name="appointmentTime"]').value;
            
            try {
                // First check if the slot is still available
                const slotCheck = await fetch('check_slot_availability.php', {
                            method: 'POST',
                            headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                    body: `date=${encodeURIComponent(appointmentDate)}&time=${encodeURIComponent(appointmentTime)}`
                });
                
                const slotData = await slotCheck.json();
                if (!slotData.available) {
                    showFormMessage('error', slotData.message || 'This slot is no longer available. Please select another time slot.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit Appointment Request';
                    // Redirect back to slot booking after a delay
                            setTimeout(() => {
                        window.location.href = 'slot-booking.php';
                            }, 3000);
                    return false;
                        }
            
                // Proceed with form submission
                const formData = new FormData(this);
                formData.append('g-recaptcha-response', recaptchaResponse);
                
                const response = await fetch('registration-form.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                        
                // Log response headers for debugging
                console.log('Response status:', response.status);
                console.log('Content-Type:', response.headers.get('content-type'));
                
                // First check if response is OK
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                    }
                
                // Get the response text first (don't try to directly parse as JSON)
                const responseText = await response.text();
                
                // Try to parse the text as JSON
                let result;
                try {
                    // Skip empty responses
                    if (responseText.trim() === '') {
                        throw new Error('Empty response from server');
                    }
                    
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    console.error('Error parsing JSON response:', jsonError);
                    console.error('Response text:', responseText);
                    throw new Error('Invalid response format from server');
        }
        
                if (result && result.success) {
                    // Show success state
                    submitBtn.innerHTML = '<span class="text-white">✓ Success!</span>';
                    submitBtn.classList.remove('bg-indigo-600');
                    submitBtn.classList.add('bg-green-600');
                        
                    // Show success animation and redirect
                    document.getElementById('registrationForm').classList.add('hidden');
                    document.getElementById('successAnimation').classList.remove('hidden');
                    
                    // Redirect to success page
                        setTimeout(() => {
                        window.location.href = 'booking-success.php';
                        }, 2000);
                } else {
                    // Reset reCAPTCHA
                    grecaptcha.reset();
                    
                    // Show error message
                    const errorMsg = result && result.message ? result.message : 'An error occurred';
                    showFormMessage('error', errorMsg);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Submit Appointment Request';
                    
                    // If the error is about slot availability, redirect back to slot booking
                    if (errorMsg.includes('slot') && errorMsg.includes('available')) {
                        setTimeout(() => {
                            window.location.href = 'slot-booking.php';
                        }, 3000);
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                grecaptcha.reset();
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Submit Appointment Request';
                showFormMessage('error', 'An unexpected error occurred. Please try again.');
        }
        });
        
        // Enhanced validation function
        function validateForm() {
            let isValid = true;
            const requiredFields = ['first_name', 'last_name', 'phone', 'specialty', 'reason'];
            
            // Clear previous errors
            document.querySelectorAll('.error-message').forEach(el => el.remove());
            document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

            requiredFields.forEach(field => {
                const element = document.querySelector(`[name="${field}"]`);
                if (element && !element.value.trim()) {
                    isValid = false;
                    element.classList.add('is-invalid');
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message text-red-500 text-sm mt-1';
                    errorDiv.textContent = `${field.replace('_', ' ').charAt(0).toUpperCase() + field.slice(1)} is required`;
                    element.parentNode.appendChild(errorDiv);
            }
            });
            
            return isValid;
        }
        
        // Enable submit button when reCAPTCHA is completed
        function enableSubmit() {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.add('hover:bg-indigo-700');
            }
            const recaptchaError = document.getElementById('recaptchaError');
            if (recaptchaError) {
                recaptchaError.style.display = 'none';
            }
            }
            
        // Disable submit button when reCAPTCHA expires
        function disableSubmit() {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                submitBtn.classList.remove('hover:bg-indigo-700');
            }
            showRecaptchaError('reCAPTCHA verification expired. Please verify again.');
            }
            
        // Show reCAPTCHA error message
        function showRecaptchaError(message) {
            const errorDiv = document.getElementById('recaptchaError');
            if (errorDiv) {
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                errorDiv.className = 'error-message text-red-500 text-sm mt-2';
            }
            }
            
        // Improved form message display function
        function showFormMessage(type, message) {
            const messageDiv = document.getElementById('formMessage');
            if (messageDiv) {
                messageDiv.className = 'mb-4 p-3 rounded text-sm font-medium ' + 
                    (type === 'error' ? 'bg-red-100 text-red-700 border border-red-400' : 
                     'bg-green-100 text-green-700 border border-green-400');
                messageDiv.textContent = message;
                messageDiv.classList.remove('hidden');
                
                // Scroll to message
                messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
        
        // Add this JavaScript before form submission
        async function checkSlotAvailability(date, time) {
            try {
                const response = await fetch('check_slot_availability.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}`
                });
                    
                // Check if response is OK before parsing JSON
                if (!response.ok) {
                    console.error('Server returned error status:', response.status);
                    return false;
                }
                
                try {
                    const data = await response.json();
                    return data.available === true;
                } catch (jsonError) {
                    console.error('Error parsing JSON response:', jsonError);
                        return false;
                    }
            } catch (error) {
                console.error('Error checking slot availability:', error);
                        return false;
                    }
        }
    </script>
</body>
</html>

<?php
// Simplified sendConfirmationEmail function
function sendConfirmationEmail($to, $data) {
    // Use PHPMailer for more reliable email delivery
    require_once 'php/php-mailer/src/PHPMailer.php';
    require_once 'php/php-mailer/src/SMTP.php';
    require_once 'php/php-mailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Recipients
        $mail->setFrom('appointments@hospital.com', 'Hospital Appointments');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Appointment Confirmation - Ref: " . $data['reference'];
        
        // HTML message with simplified styling
        $htmlMessage = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #7047d1; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; }
                .appointment-details { background-color: #f4e6fa; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .reference { font-weight: bold; color: #7047d1; }
                .footer { font-size: 0.8em; color: #777; margin-top: 30px; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Appointment Confirmation</h2>
                </div>
                <div class='content'>
                    <p>Dear " . htmlspecialchars($data['name']) . ",</p>
                    <p>Your appointment request has been received and confirmed.</p>
                    
                    <div class='appointment-details'>
                        <p><strong>Date:</strong> " . htmlspecialchars($data['date']) . "</p>
                        <p><strong>Time:</strong> " . htmlspecialchars($data['time']) . "</p>
                        <p><strong>Reference:</strong> <span class='reference'>" . htmlspecialchars($data['reference']) . "</span></p>
                    </div>
                    
                    <p>Please keep this reference number for your records.</p>
                    <p>We look forward to seeing you!</p>
                    
                    <div class='footer'>
                        <p>© " . date('Y') . " Hospital Appointment System</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        // Plain text alternative
        $textMessage = "Dear " . $data['name'] . ",\n\n";
        $textMessage .= "Your appointment has been confirmed for:\n";
        $textMessage .= "Date: " . $data['date'] . "\n";
        $textMessage .= "Time: " . $data['time'] . "\n";
        $textMessage .= "Reference: " . $data['reference'] . "\n\n";
        $textMessage .= "We look forward to seeing you!\n\n";
        
        $mail->Body = $htmlMessage;
        $mail->AltBody = $textMessage;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        throw new Exception("Failed to send confirmation email");
    }
}
?>