-- Migration Script: Rename companies table to tender_companies
-- This script should be run if the companies table already exists in your database

-- =====================================================
-- STEP 1: Check if companies table exists
-- =====================================================
SET @table_exists = (SELECT COUNT(*) 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'companies');

-- =====================================================
-- STEP 2: Rename table if it exists
-- =====================================================
SET @sql = IF(@table_exists > 0, 
    'RENAME TABLE companies TO tender_companies;', 
    'SELECT "companies table does not exist, skipping rename" as message;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- STEP 3: Update foreign key constraints in tender_bid_winners table
-- =====================================================
-- Drop existing foreign key constraint
ALTER TABLE tender_bid_winners 
DROP FOREIGN KEY IF EXISTS fk_tender_bid_winner_company;

-- Add new foreign key constraint with updated reference
ALTER TABLE tender_bid_winners 
ADD CONSTRAINT fk_tender_bid_winner_company 
FOREIGN KEY (company_id) REFERENCES tender_companies (id) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- =====================================================
-- STEP 4: Update foreign key constraints in company_directors table
-- =====================================================
-- Drop existing foreign key constraint
ALTER TABLE company_directors 
DROP FOREIGN KEY IF EXISTS fk_company_director_company;

-- Add new foreign key constraint with updated reference
ALTER TABLE company_directors 
ADD CONSTRAINT fk_company_director_company 
FOREIGN KEY (company_id) REFERENCES tender_companies (id) 
ON DELETE CASCADE ON UPDATE CASCADE;

-- =====================================================
-- STEP 5: Verify the changes
-- =====================================================
-- Show all tables in the database
SELECT table_name 
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name LIKE '%company%'
ORDER BY table_name;

-- Show foreign key constraints
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND REFERENCED_TABLE_NAME = 'tender_companies'
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- =====================================================
-- STEP 6: Update any existing views or stored procedures
-- =====================================================
-- If you have any views that reference the old table name, update them here
-- Example:
-- DROP VIEW IF EXISTS company_summary_view;
-- CREATE VIEW company_summary_view AS
-- SELECT * FROM tender_companies WHERE status = 'active';

-- =====================================================
-- STEP 7: Update application code references
-- =====================================================
-- Remember to update your Symfony entities and repositories:
-- 1. Update Company entity table name annotation
-- 2. Update CompanyRepository class name if needed
-- 3. Update any service classes that reference the old table name
-- 4. Update any Doctrine queries that use the old table name

SELECT 'Migration completed successfully!' as status; 