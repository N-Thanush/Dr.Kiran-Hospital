<?php
// Start session
session_start();

// Clear all session data
$_SESSION = array();

// Destroy the session
session_destroy();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page
header("Location: pharmacy_login.php");
exit();
