-- Migration Script: Add unique constraint on tender_number
-- This script should be run if you have an existing tenders table

-- =====================================================
-- STEP 1: Check if unique constraint already exists
-- =====================================================
SET @constraint_exists = (
    SELECT COUNT(*) 
    FROM information_schema.table_constraints 
    WHERE table_schema = DATABASE() 
    AND table_name = 'tenders' 
    AND constraint_name = 'unique_tender_number'
);

-- =====================================================
-- STEP 2: Check for duplicate tender numbers before adding constraint
-- =====================================================
SELECT 
    tender_number,
    COUNT(*) as duplicate_count
FROM tenders 
GROUP BY tender_number 
HAVING COUNT(*) > 1;

-- =====================================================
-- STEP 3: Add unique constraint if it doesn't exist
-- =====================================================
SET @sql = IF(@constraint_exists = 0, 
    'ALTER TABLE tenders ADD UNIQUE KEY unique_tender_number (tender_number);', 
    'SELECT "Unique constraint on tender_number already exists" as message;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- STEP 4: Verify the constraint was added
-- =====================================================
SELECT 
    constraint_name,
    constraint_type,
    column_name
FROM information_schema.key_column_usage 
WHERE table_schema = DATABASE() 
AND table_name = 'tenders' 
AND constraint_name = 'unique_tender_number';

-- =====================================================
-- STEP 5: Test the constraint
-- =====================================================
-- Try to insert a duplicate tender number (this should fail)
-- Uncomment the lines below to test the constraint
/*
INSERT INTO tenders (
    category, 
    tender_description, 
    tender_number, 
    organ_of_state, 
    province, 
    closing_date, 
    place_where_goods_works_or_services_are_required, 
    contact_person, 
    email, 
    telephone_number
) VALUES (
    'Test Category',
    'Test Description',
    'TEN-2024-001', -- This should fail if TEN-2024-001 already exists
    'Test Organ',
    'Test Province',
    '2024-12-31 23:59:59',
    'Test Place',
    'Test Contact',
    'test@example.com',
    '+27 11 123 4567'
);
*/

SELECT 'Migration completed successfully!' as status; 