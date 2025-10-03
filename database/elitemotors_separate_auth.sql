-- Create database
CREATE DATABASE IF NOT EXISTS `elitemotors_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `elitemotors_db`;

-- ============================================
-- USERS TABLE (For regular users)
-- ============================================
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `address` TEXT,
    `profile_image` VARCHAR(255),
    `is_active` TINYINT(1) DEFAULT 1,
    `email_verified` TINYINT(1) DEFAULT 0,
    `verification_token` VARCHAR(100),
    `reset_token` VARCHAR(100),
    `reset_token_expires` DATETIME,
    `last_login` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- ADMINS TABLE (For admin users)
-- ============================================
CREATE TABLE IF NOT EXISTS `admins` (
    `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) UNIQUE NOT NULL,
    `email` VARCHAR(100) UNIQUE NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `role` ENUM('admin', 'superadmin') DEFAULT 'admin',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- CARS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `cars` (
    `car_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `make` VARCHAR(50) NOT NULL,
    `model` VARCHAR(50) NOT NULL,
    `year` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `mileage` INT,
    `transmission` ENUM('Automatic', 'Manual', 'Semi-Automatic'),
    `fuel_type` ENUM('Petrol', 'Diesel', 'Electric', 'Hybrid', 'LPG', 'CNG'),
    `engine_size` DECIMAL(3,1),
    `color` VARCHAR(30),
    `description` TEXT,
    `image_path` VARCHAR(255),
    `is_sold` TINYINT(1) DEFAULT 0,
    `sold_at` DATETIME,
    `is_approved` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Insert default admin user
-- ============================================
-- Password: admin123
INSERT INTO `admins` (
    `username`,
    `email`,
    `password_hash`,
    `first_name`,
    `last_name`,
    `phone`,
    `role`,
    `is_active`
) VALUES (
    'admin',
    'admin@elitemotors.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Admin',
    'User',
    '1234567890',
    'superadmin',
    1
);

-- ============================================
-- PURCHASES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `purchases` (
    `purchase_id` INT AUTO_INCREMENT PRIMARY KEY,
    `car_id` INT NOT NULL,
    `buyer_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    `payment_method` VARCHAR(50),
    `transaction_id` VARCHAR(100),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`car_id`) REFERENCES `cars`(`car_id`) ON DELETE CASCADE,
    FOREIGN KEY (`buyer_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- TESTIMONIALS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `testimonials` (
    `testimonial_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `content` TEXT NOT NULL,
    `rating` TINYINT(1) NOT NULL CHECK (rating >= 1 AND rating <= 5),
    `is_approved` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- CONTACT MESSAGES TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `contact_messages` (
    `message_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(255),
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- Create indexes for better performance
-- ============================================
CREATE INDEX idx_users_email ON `users`(`email`);
CREATE INDEX idx_users_username ON `users`(`username`);
CREATE INDEX idx_admins_email ON `admins`(`email`);
CREATE INDEX idx_admins_username ON `admins`(`username`);
CREATE INDEX idx_cars_make_model ON `cars`(`make`, `model`);
CREATE INDEX idx_cars_price ON `cars`(`price`);
CREATE INDEX idx_cars_year ON `cars`(`year`);

-- ============================================
-- Create admin logs table
-- ============================================
CREATE TABLE IF NOT EXISTS `admin_logs` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT,
    `action` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `admins`(`admin_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
