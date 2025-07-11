-- Create commute_distance_messages table for tracking message dates
CREATE TABLE commute_distance_messages (
    id INT AUTO_INCREMENT NOT NULL,
    commute_distance_id INT NOT NULL,
    passenger_last_message_date DATETIME DEFAULT NULL,
    driver_last_message_date DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY(id),
    INDEX commute_distance_idx (commute_distance_id),
    INDEX passenger_message_date_idx (passenger_last_message_date),
    INDEX driver_message_date_idx (driver_last_message_date)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB; 