<?php
require_once 'connect.php';

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
    echo "Hash: " . $hashed_password;
} else {
    echo "Error creating admin user: " . $stmt->error;
}
?> 