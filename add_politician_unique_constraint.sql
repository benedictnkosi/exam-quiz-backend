-- Add unique constraint to prevent duplicate politicians with same full name and country
-- This ensures no politician with the same full name can exist in the same country

-- First, remove any existing duplicates (keep the one with the highest ID)
DELETE p1 FROM politicians p1
INNER JOIN politicians p2 
WHERE p1.id < p2.id 
AND p1.full_name = p2.full_name 
AND p1.country = p2.country;

-- Add unique constraint
ALTER TABLE politicians 
ADD CONSTRAINT politician_full_name_country_unique 
UNIQUE (full_name, country); 