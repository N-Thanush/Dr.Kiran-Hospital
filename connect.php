<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dr_kiran_appointments";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

error_log("Database connection successful");

// Check if appointments table exists
$result = $conn->query("SHOW TABLES LIKE 'appointments'");
if ($result->num_rows == 0) {
    // Try to load the database initialization if the appointments table doesn't exist
    error_log("[Database] WARNING: appointments table does not exist");
    header("Location: db_init.php");
    exit();
}

// Helper function to safely execute queries
function executeQuery($conn, $query, $params = []) {
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        $types = '';
        $bindParams = [];
        
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_double($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b';
            }
            $bindParams[] = $param;
        }
        
        array_unshift($bindParams, $types);
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }
    
    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }
    
    return $stmt;
}
?>
