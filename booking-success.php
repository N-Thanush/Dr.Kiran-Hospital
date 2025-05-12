<?php
session_start();

// Redirect if no success data
if (!isset($_SESSION['booking_success'])) {
    header("Location: slot-booking.php");
    exit;
}

$success = $_SESSION['booking_success'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Successful - Dr. Kiran Hospital</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css">
    <script src="https://unpkg.com/@dotlottie/player-component@2.7.12/dist/dotlottie-player.mjs" type="module"></script>
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .success-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        .success-animation {
            width: 150px;
            height: 150px;
            margin: 0 auto 1rem;
        }
        .appointment-details {
            background-color: #f4e6fa;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
        }
        .detail-row {
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .detail-label {
            font-weight: 600;
            color: #4a5568;
            flex-basis: 35%;
            font-size: 0.9rem;
        }
        .detail-value {
            flex-basis: 65%;
            color: #2d3748;
            font-size: 0.9rem;
        }
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .btn-primary {
            background-color: #7047d1;
            color: white;
        }
        .btn-primary:hover {
            background-color: #5d3cb5;
            transform: translateY(-1px);
        }
        .success-message {
            color: #2f855a;
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }
        .confirmation-number {
            font-family: monospace;
            font-size: 1rem;
            color: #7047d1;
            background: #f0e6ff;
            padding: 0.4rem;
            border-radius: 4px;
            margin: 0.75rem 0;
        }
        .text-gray-600 {
            font-size: 0.9rem;
        }
        .space-x-4 {
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <dotlottie-player
            src="https://lottie.host/66a77625-724a-4d65-ae1e-4011b2c2aa67/MUBSjtzlHu.lottie"
            background="transparent"
            speed="1"
            class="success-animation"
            autoplay
        ></dotlottie-player>
        
        <h1 class="success-message">Booking Successful!</h1>
        <p class="text-gray-600 mb-4">Your appointment has been confirmed.</p>
        
        <div class="confirmation-number">
            Confirmation #: <?php echo strtoupper(substr(md5($success['patient_name'] . $success['appointment_date'] . $success['appointment_time']), 0, 8)); ?>
        </div>
        
        <div class="appointment-details">
            <div class="detail-row">
                <span class="detail-label">Patient Name:</span>
                <span class="detail-value"><?php echo htmlspecialchars($success['patient_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value"><?php echo htmlspecialchars($success['appointment_date']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time:</span>
                <span class="detail-value"><?php echo htmlspecialchars($success['appointment_time']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Phone:</span>
                <span class="detail-value"><?php echo htmlspecialchars($success['phone']); ?></span>
            </div>
            <?php if (!empty($success['reason'])): ?>
            <div class="detail-row">
                <span class="detail-label">Reason:</span>
                <span class="detail-value"><?php echo nl2br(htmlspecialchars($success['reason'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <p class="text-gray-600 mb-6">
            Please arrive 10 minutes before your scheduled appointment time.<br>
            Save your confirmation number for future reference.
        </p>
        
        <div class="space-x-4">
            <a href="index.php" class="btn btn-primary">Return to Home</a>
        </div>
    </div>

    <?php
    // Clear the success session data after displaying
    unset($_SESSION['booking_success']);
    ?>
</body>
</html>