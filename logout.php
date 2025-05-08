<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to appropriate login page based on the user type
if (isset($_GET['type']) && $_GET['type'] === 'reception') {
    header('Location: reception_login.php?logged_out=1');
} else {
    header('Location: admin_login.php?logged_out=1');
}
exit(); 