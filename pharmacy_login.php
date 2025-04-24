<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Clear any existing session data
session_unset();
session_destroy();
session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is already logged in
if (isset($_SESSION['pharmacy_user_id']) && !empty($_SESSION['pharmacy_user_id'])) {
    // Only redirect if we're not on the login page
    if (basename($_SERVER['PHP_SELF']) !== 'pharmacy_login.php') {
        header("Location: pharmacy_dashboard.php");
        exit();
    }
}

// Login handling
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'connect.php';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id, username, password FROM pharmacy_users WHERE username = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("s", $username);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Start a new session
                    session_regenerate_id(true);
                    $_SESSION['pharmacy_user_id'] = $user['id'];
                    $_SESSION['pharmacy_username'] = $user['username'];
                    header("Location: pharmacy_dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username or password";
                }
            } else {
                $error = "Invalid username or password";
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "An error occurred during login. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <title>Pharmacy Login</title>
    <link rel="icon" href="img/klogo-.png" type="image/x-icon" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="css/pharmacy_login.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet" />

    <script>
        // Prevent back after logout
        history.pushState(null, null, location.href);
        window.onpopstate = function () {
            history.go(1);
        };
    </script>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <img src="img/klogo-.png" alt="Logo" />
            </div>
            <h2 class="text-center mb-4">Pharmacy Login</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" novalidate>
                <!-- Dummy fields to fool Chrome autofill -->
                <input type="text" name="fakeusernameremembered" style="display:none">
                <input type="password" name="fakepasswordremembered" style="display:none">

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="username" 
                        name="username" 
                        autocomplete="off" 
                        required 
                        value=""
                    />
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            autocomplete="new-password" 
                            required 
                        />
                        <button type="button" class="btn btn-outline-secondary input-group-text" id="togglePassword">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <script>
                        document.getElementById('togglePassword').addEventListener('click', function () {
                            const passwordField = document.getElementById('password');
                            const icon = this.querySelector('i');
                            if (passwordField.type === 'password') {
                                passwordField.type = 'text';
                                icon.classList.remove('bi-eye');
                                icon.classList.add('bi-eye-slash');
                            } else {
                                passwordField.type = 'password';
                                icon.classList.remove('bi-eye-slash');
                                icon.classList.add('bi-eye');
                            }
                        });
                    </script>
                
                </div>

                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>

    <script>
        // Extra protection: clear inputs on page load
        window.onload = function () {
            setTimeout(() => {
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
            }, 10);
        };

        // Safari-specific fix for back/forward cache
        window.addEventListener("pageshow", function (event) {
            if (event.persisted || window.performance.navigation.type === 2) {
                window.location.reload();
            }
        });
    </script>
</body>
</html>
