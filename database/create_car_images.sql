-- Create car_images table
CREATE TABLE IF NOT EXISTS `car_images` (
    `image_id` INT AUTO_INCREMENT PRIMARY KEY,
    `car_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`car_id`) REFERENCES `cars`(`car_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for better performance
CREATE INDEX idx_car_images_car_id ON `car_images`(`car_id`);
