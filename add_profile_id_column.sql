-- Add profile_id column to learner_completed_chapter table
-- This SQL script adds the profile_id column and creates an index for performance

-- Add the profile_id column (nullable integer)
ALTER TABLE learner_completed_chapter 
ADD COLUMN profile_id INT DEFAULT NULL;

-- Create index on profile_id for better query performance
CREATE INDEX learner_completed_chapter_profile_id_idx 
ON learner_completed_chapter (profile_id);

-- Verify the changes
DESCRIBE learner_completed_chapter;

-- Optional: Show the new index
SHOW INDEX FROM learner_completed_chapter WHERE Key_name = 'learner_completed_chapter_profile_id_idx'; 