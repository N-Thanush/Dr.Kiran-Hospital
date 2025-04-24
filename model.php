<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Navigation - Dr. Kiran Neuro Centre</title>
    <link rel="icon" href="img/klogo-.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <link href="css/theme.css" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #1e3c72;
            --bs-secondary: #88bbcc;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
            min-height: 100vh;
            padding: 20px;
        }

        .page-container {
            max-width: 800px;
            margin: 50px auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0,0,0,0.2);
            padding: 30px;
            backdrop-filter: blur(10px);
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header img {
            max-width: 150px;
            margin-bottom: 20px;
        }

        .page-header h1 {
            color: var(--bs-primary);
            font-weight: 600;
        }

        .page-list {
            list-style: none;
            padding: 0;
        }

        .page-item {
            margin-bottom: 15px;
        }

        .page-link {
            display: block;
            padding: 15px 20px;
            background: white;
            color: var(--bs-primary);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            font-weight: 500;
        }

        .page-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }

        .page-link i {
            margin-right: 10px;
            color: var(--bs-secondary);
        }

        .back-to-home {
            text-align: center;
            margin-top: 30px;
        }

        .back-to-home a {
            color: var(--bs-primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .back-to-home a:hover {
            color: var(--bs-secondary);
        }

        @media (max-width: 768px) {
            .page-container {
                margin: 20px;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <img src="img/klogo-.png" alt="Dr. Kiran Neuro Centre">
            <h1>Page Navigation</h1>
        </div>
        
        <ul class="page-list">
            <li class="page-item">
                <a href="index.php" class="page-link">
                    <i class="fas fa-home"></i> Home Page
                </a>
            </li>
            <li class="page-item">
                <a href="pharmacy_login.php" class="page-link">
                    <i class="fas fa-pills"></i> Pharmacy Login
                </a>
            </li>
            <li class="page-item">
                <a href="pharmacy_dashboard.php" class="page-link">
                    <i class="fas fa-tachometer-alt"></i> Pharmacy Dashboard
                </a>
            </li>
            <li class="page-item">
                <a href="admin_login.php" class="page-link">
                    <i class="fas fa-user-shield"></i> Admin Login
                </a>
            </li>
            <li class="page-item">
                <a href="admin_calendar.php" class="page-link">
                    <i class="fas fa-calendar-alt"></i> Admin Calendar
                </a>
            </li>
            <li class="page-item">
                <a href="registration-form.php" class="page-link">
                    <i class="fas fa-user-plus"></i> Registration Form
                </a>
            </li>
            <li class="page-item">
                <a href="slot-booking.php" class="page-link">
                    <i class="fas fa-calendar-check"></i> Slot Booking
                </a>
            </li>
            <li class="page-item">
                <a href="confirmation.php" class="page-link">
                    <i class="fas fa-check-circle"></i> Confirmation Page
                </a>
            </li>
            <li class="page-item">
                <a href="test.php" class="page-link">
                    <i class="fas fa-check-circle"></i> test
                </a>
            </li>
            <li class="page-item">
                <a href="pharmacy_dashboard_new.php" class="page-link">
                    <i class="fas fa-check-circle"></i> new Dashboard
                </a>
            </li>
        </ul>

        <div class="back-to-home">
            <a href="index.php">
                <i class="fas fa-arrow-left"></i> Back to Home
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 