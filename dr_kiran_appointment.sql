-- Dr. Kiran Hospital Appointment System Database Setup
-- Main database file containing all tables and configurations

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Create and use the database
DROP DATABASE IF EXISTS dr_kiran_appointments;
CREATE DATABASE dr_kiran_appointments;
USE dr_kiran_appointments;

-- Drop any existing tables that might exist
DROP TABLE IF EXISTS `appointment_notes`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `time_slots_config`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `available_slots`;
DROP TABLE IF EXISTS `staff`;
DROP TABLE IF EXISTS `patients`;
DROP TABLE IF EXISTS `doctors`;

-- Create essential tables

-- Create admin_users table (essential for login and access control)
CREATE TABLE `admin_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'reception', 'doctor') NOT NULL DEFAULT 'reception',
    `full_name` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_username` (`username`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password is 'admin123')
INSERT INTO `admin_users` (username, password, role, full_name) VALUES 
('admin', '$2y$10$XMM2PGIlrv0adwJ3fx0v0.xFT9T6FTSrA7Kx.xr4kBYAD2X1m0hRa', 'admin', 'System Administrator');

-- Create appointments table (core functionality)
CREATE TABLE `appointments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `patient_name` VARCHAR(100) NULL DEFAULT NULL,
    `patient_phone` VARCHAR(20) NULL DEFAULT NULL,
    `reason` TEXT NULL DEFAULT NULL,
    `preferred_specialty` VARCHAR(100) NULL DEFAULT NULL,
    `is_booked` TINYINT(1) NOT NULL DEFAULT 0,
    `is_available` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('available', 'confirmed', 'cancelled', 'completed') NOT NULL DEFAULT 'available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_appointment_date` (`appointment_date`),
    INDEX `idx_specialty` (`preferred_specialty`),
    UNIQUE KEY `unique_appointment` (`appointment_date`, `appointment_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create time_slots_config table (essential for slot management)
CREATE TABLE `time_slots_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slot_type` ENUM('morning', 'evening') NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `interval_minutes` INT NOT NULL DEFAULT 15,
    UNIQUE KEY `unique_slot_type` (`slot_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default time slots configuration
INSERT INTO `time_slots_config` (slot_type, start_time, end_time, interval_minutes) VALUES
('morning', '10:00:00', '13:00:00', 15),
('evening', '18:00:00', '20:00:00', 15);

DELIMITER //

CREATE PROCEDURE generate_available_slots(p_date DATE, p_slot_type VARCHAR(10))
BEGIN
    DECLARE v_start_time TIME;
    DECLARE v_end_time TIME;
    DECLARE v_interval INT;
    DECLARE v_current_time TIME;

    SELECT start_time, end_time, interval_minutes 
    INTO v_start_time, v_end_time, v_interval
    FROM time_slots_config 
    WHERE slot_type = p_slot_type;

    SET v_current_time = v_start_time;

    WHILE v_current_time < v_end_time DO
        INSERT IGNORE INTO appointments (
            appointment_date,
            appointment_time,
            is_available,
            is_booked,
            status
        ) VALUES (
            p_date,
            v_current_time,
            1,
            0,
            'available'
        );
        SET v_current_time = ADDTIME(v_current_time, SEC_TO_TIME(v_interval * 60));
    END WHILE;

    SELECT 'Slots generated successfully' as message;
END //

CREATE PROCEDURE book_appointment(p_date DATE, p_time TIME, p_name VARCHAR(100), p_phone VARCHAR(20), p_reason TEXT, p_specialty VARCHAR(100))
BEGIN
    UPDATE appointments
    SET patient_name = p_name,
        patient_phone = p_phone,
        reason = p_reason,
        preferred_specialty = p_specialty,
        is_booked = 1,
        is_available = 0,
        status = 'confirmed'
    WHERE appointment_date = p_date
    AND appointment_time = p_time
    AND is_available = 1;
    
    SELECT IF(ROW_COUNT() > 0, 'Appointment booked successfully', 'Slot not available') AS message;
END //

DELIMITER ;

-- Create stored procedure for cancelling appointments
DELIMITER //

CREATE PROCEDURE `cancel_appointment`(
    IN p_date DATE,
    IN p_time TIME
)
BEGIN
    UPDATE appointments
    SET patient_name = NULL,
        patient_phone = NULL,
        reason = NULL,
        preferred_specialty = NULL,
        is_booked = 0,
        is_available = 1,
        status = 'available',
        updated_at = CURRENT_TIMESTAMP
    WHERE appointment_date = p_date
    AND appointment_time = p_time;
    
    SELECT ROW_COUNT() > 0 AS success,
           'Appointment cancelled successfully' AS message;
END //

DELIMITER ;

-- Log successful execution
SELECT "Dr. Kiran Hospital database setup completed successfully." AS Message; 