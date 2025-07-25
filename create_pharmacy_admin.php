<?php
require_once 'connect.php';

// Create pharmacy_users table if it doesn't exist
$createTableSQL = "CREATE TABLE IF NOT EXISTS pharmacy_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($createTableSQL)) {
    echo "Table created successfully or already exists<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Clear existing users
$conn->query("TRUNCATE TABLE pharmacy_users");

// Create new admin user with password hash
$username = 'admin';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert the new user
$sql = "INSERT INTO pharmacy_users (username, password) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $username, $hashed_password);

if ($stmt->execute()) {
    echo "Admin user created successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
} else {
    echo "Error creating admin user: " . $stmt->error . "<br>";
}
?> 