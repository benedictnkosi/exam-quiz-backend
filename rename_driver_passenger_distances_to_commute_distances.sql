-- Migration script to rename driver_passenger_distances to commute_distances
-- Run this if the old table already exists

-- Check if the old table exists and rename it
RENAME TABLE driver_passenger_distances TO commute_distances;

-- Note: If the table doesn't exist, this will show an error, which is fine
-- You can then run the commute_distances_table.sql to create the new table 