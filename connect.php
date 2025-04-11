<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = 'mysql';  // AMPPS default password
$dbName = 'dr_kiran_appointments';

try {
    // Establish connection
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Set charset to ensure special characters are handled properly
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("Error setting charset: " . $conn->error);
    }

    // Test the connection with a simple query
    if (!$conn->query("SELECT 1")) {
        throw new Exception("Database test failed: " . $conn->error);
    }

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
