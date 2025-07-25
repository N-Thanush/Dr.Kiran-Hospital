-- Create pharmacy tables in existing database
USE dr_kiran_appointments;

-- Drop existing tables if they exist
DROP TABLE IF EXISTS medicine_stock;
DROP TABLE IF EXISTS pharmacy_users;

-- Create pharmacy users table
CREATE TABLE IF NOT EXISTS pharmacy_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create medicine stock table
CREATE TABLE IF NOT EXISTS medicine_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    product_type ENUM('injections', 'syrup', 'syringes', 'tablets') NOT NULL,
    batch_no VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    mrp DECIMAL(10,2) NOT NULL,
    rate DECIMAL(10,2) NOT NULL,
    gst DECIMAL(5,2) NOT NULL,
    sgst DECIMAL(10,2),
    cgst DECIMAL(10,2),
    total_amount DECIMAL(10,2),
    expiry_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Delete existing admin user if exists and insert new one
DELETE FROM pharmacy_users WHERE username = 'admin';
INSERT INTO pharmacy_users (username, password) VALUES 
('admin', '$2y$10$8K1p/95btF6A0NzDE1qHyO8RyFvPv5mEEz0RhF1z7.Y1RvM5U7i6q'); 