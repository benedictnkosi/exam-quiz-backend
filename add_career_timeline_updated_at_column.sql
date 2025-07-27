-- Add career_timeline_updated_at column to politicians table
-- This column will track when the career timeline was last updated

ALTER TABLE politicians 
ADD COLUMN career_timeline_updated_at DATETIME NULL 
COMMENT 'Timestamp when career timeline was last updated'; 