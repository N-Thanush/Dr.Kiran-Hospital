<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Handle logout
if (isset($_GET['logout'])) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: admin_login.php?logged_out=1');
    exit();
}

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_management.php');
    exit();
}

// Hard-coded admin credentials as fallback
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

// Process login attempt
$loginError = false;
$errorMessage = '';
$successMessage = '';

// Check for logout message
if (isset($_GET['logged_out'])) {
    $successMessage = 'You have been successfully logged out.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Simple validation
    if (empty($username) || empty($password)) {
        $loginError = true;
        $errorMessage = 'Please enter both username and password';
    } else {
        $loginSuccessful = false;
        
        // Check credentials
        if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
            $loginSuccessful = true;
        } else {
            try {
                require_once 'connect.php';
                
                $query = "SELECT * FROM admin_users WHERE username = ?";
                $stmt = $conn->prepare($query);
                
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result && $result->num_rows === 1) {
                        $admin = $result->fetch_assoc();
                        if (password_verify($password, $admin['password'])) {
                            $loginSuccessful = true;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Database error during login: " . $e->getMessage());
            }
        }
        
        if ($loginSuccessful) {
            // Set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            
            // Redirect to admin dashboard
            header('Location: admin_management.php?login_success=1');
            exit();
        } else {
            $loginError = true;
            $errorMessage = 'Invalid username or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Dr. Kiran Neuro Centre</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header img {
            height: 60px;
            margin-bottom: 15px;
        }
        .form-control {
            border-radius: 5px;
            padding: 10px 15px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #1e3c72, #88bbcc);
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            width: 100%;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #88bbcc, #1e3c72);
        }
        .back-to-home {
            text-align: center;
            margin-top: 20px;
        }
        .back-to-home a {
            color: #1e3c72;
            text-decoration: none;
        }
        .back-to-home a:hover {
            color: #88bbcc;
        }
        .credentials-reminder {
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            background-color: #f0f7ff;
            border-left: 3px solid #1e3c72;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <img src="img/klogo-.png" alt="Dr. Kiran Neuro Centre">
                <h2>Admin Login</h2>
            </div>
            
            <?php if ($loginError): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($successMessage): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i><?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            
            <!-- <div class="credentials-reminder">
                <p class="mb-0"><strong>Default credentials:</strong> Username: admin, Password: admin123</p>
            </div> -->
            
            <div class="back-to-home">
                <a href="index.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

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

        // Show notifications based on URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('logged_out')) {
            showNotification('You have been successfully logged out', 'success');
        }
        if (urlParams.has('login_success')) {
            showNotification('Login successful!', 'success');
        }

        // Handle form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!username || !password) {
                e.preventDefault();
                showNotification('Please enter both username and password', 'error');
            }
        });
    </script>
</body>
</html> 