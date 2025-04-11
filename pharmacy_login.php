<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Debug connection
try {
    require_once 'connect.php';
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    echo "<!-- Database connection successful -->";
} catch (Exception $e) {
    die("Connection error: " . $e->getMessage());
}

if (isset($_SESSION['pharmacy_user_id'])) {
    header("Location: pharmacy_dashboard.php");
    exit();
}

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Debug login attempt
    echo "<!-- Login attempt with username: " . htmlspecialchars($username) . " -->";
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            // Check if table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'pharmacy_users'");
            if ($table_check->num_rows == 0) {
                throw new Exception("pharmacy_users table does not exist");
            }
            
            // Get all users for debugging
            $all_users = $conn->query("SELECT id, username, password FROM pharmacy_users");
            echo "<!-- Found " . $all_users->num_rows . " users in database -->";
            while ($user = $all_users->fetch_assoc()) {
                echo "<!-- User: " . $user['username'] . " with hash: " . $user['password'] . " -->";
            }
            
            $sql = "SELECT id, username, password FROM pharmacy_users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $username);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            echo "<!-- Query found " . $result->num_rows . " matching users -->";
            
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                echo "<!-- Attempting to verify password for user: " . htmlspecialchars($row['username']) . " -->";
                echo "<!-- Stored hash: " . htmlspecialchars($row['password']) . " -->";
                echo "<!-- Verifying against password: " . substr($password, 0, 1) . "..." . substr($password, -1) . " -->";
                
                if (password_verify($password, $row['password'])) {
                    echo "<!-- Password verification successful! -->";
                    $_SESSION['pharmacy_user_id'] = $row['id'];
                    $_SESSION['pharmacy_username'] = $row['username'];
                    header("Location: pharmacy_dashboard.php");
                    exit();
                } else {
                    echo "<!-- Password verification failed -->";
                    $error = "Invalid password";
                }
            } else {
                $error = "User not found";
            }
        } catch (Exception $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Debug: Print the current users in the database
$debug_query = "SELECT id, username FROM pharmacy_users";
$debug_result = $conn->query($debug_query);
echo "<!-- Current users in database: -->";
while ($row = $debug_result->fetch_assoc()) {
    echo "<!-- User ID: " . $row['id'] . ", Username: " . $row['username'] . " -->";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Login - Dr. Kiran Neuro Centre</title>
    <link rel="icon" href="img/klogo-.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #1e3c72;
            --bs-secondary: #88bbcc;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            padding: 30px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 0 30px rgba(0,0,0,0.2);
            backdrop-filter: blur(10px);
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo img {
            max-width: 150px;
            height: auto;
        }

        .form-control {
            border: 2px solid #e1e1e1;
            padding: 12px;
            height: auto;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(30, 60, 114, 0.25);
            border-color: var(--bs-primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--bs-primary), var(--bs-secondary));
            border: none;
            padding: 12px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .form-label {
            color: var(--bs-primary);
            font-weight: 500;
        }

        h2 {
            color: var(--bs-primary);
            font-weight: 600;
        }

        @media (max-width: 576px) {
            .login-container {
                margin: 10px;
                padding: 20px;
            }
            .logo img {
                max-width: 120px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <img src="img/klogo-.png" alt="Hospital Logo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTUwIiBoZWlnaHQ9IjE1MCIgZmlsbD0iI2YxZjFmMSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LXNpemU9IjE4IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBhbGlnbm1lbnQtYmFzZWxpbmU9Im1pZGRsZSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmaWxsPSIjOTk5Ij5Ib3NwaXRhbCBMb2dvPC90ZXh0Pjwvc3ZnPg=='">
            </div>
            <h2 class="text-center mb-4">Pharmacy Login</h2>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST" action="" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 