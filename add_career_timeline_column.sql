-- Add career_timeline column to politicians table
-- This column will store JSON data containing the politician's career timeline

ALTER TABLE politicians 
ADD COLUMN career_timeline JSON NULL 
COMMENT 'JSON array containing politician career timeline with positions, dates, and achievements';

-- Add career_timeline_updated_at column to track when career timeline was last updated
ALTER TABLE politicians 
ADD COLUMN career_timeline_updated_at DATETIME NULL 
COMMENT 'Timestamp when career timeline was last updated'; 