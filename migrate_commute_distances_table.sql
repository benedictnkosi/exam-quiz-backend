-- Migration script to update commute_distances table with separate distance fields
-- Run this if you have an existing table with the old 'distance' column

-- Add new columns
ALTER TABLE commute_distances 
ADD COLUMN home_distance DECIMAL(10,2) NULL AFTER passenger_uid,
ADD COLUMN work_distance DECIMAL(10,2) NULL AFTER home_distance,
ADD COLUMN max_distance DECIMAL(10,2) NULL AFTER work_distance;

-- Update existing records (if any) - set all distances to the old distance value
UPDATE commute_distances 
SET home_distance = distance,
    work_distance = distance,
    max_distance = distance
WHERE distance IS NOT NULL;

-- Make new columns NOT NULL
ALTER TABLE commute_distances 
MODIFY COLUMN home_distance DECIMAL(10,2) NOT NULL,
MODIFY COLUMN work_distance DECIMAL(10,2) NOT NULL,
MODIFY COLUMN max_distance DECIMAL(10,2) NOT NULL;

-- Drop the old distance column
ALTER TABLE commute_distances DROP COLUMN distance;

-- Add new indexes
ALTER TABLE commute_distances 
ADD INDEX home_distance_idx (home_distance),
ADD INDEX work_distance_idx (work_distance),
ADD INDEX max_distance_idx (max_distance);

-- Drop the old distance index if it exists
ALTER TABLE commute_distances DROP INDEX distance_idx; 