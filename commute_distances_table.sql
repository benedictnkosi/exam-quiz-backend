-- Create commute_distances table
CREATE TABLE commute_distances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    driver_uid VARCHAR(255) NOT NULL,
    passenger_uid VARCHAR(255) NOT NULL,
    home_distance DECIMAL(10,2) NOT NULL,
    work_distance DECIMAL(10,2) NOT NULL,
    max_distance DECIMAL(10,2) NOT NULL,
    calculated_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    
    -- Indexes for performance
    INDEX driver_passenger_idx (driver_uid, passenger_uid),
    INDEX home_distance_idx (home_distance),
    INDEX work_distance_idx (work_distance),
    INDEX max_distance_idx (max_distance),
    INDEX calculated_at_idx (calculated_at),
    
    -- Unique constraint to prevent duplicate calculations
    UNIQUE KEY unique_driver_passenger (driver_uid, passenger_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 