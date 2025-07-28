# Companies Table Rename Summary

## Overview
The `companies` table has been renamed to `tender_companies` to better reflect its specific purpose in the tender management system.

## Changes Made

### 1. Database Schema Updates

#### Updated Files:
- `tender_management_tables.sql` - Main schema file
- `rename_companies_to_tender_companies.sql` - Migration script for existing databases

#### Changes in `tender_management_tables.sql`:
- Table name: `companies` → `tender_companies`
- Updated all foreign key references
- Updated all sample data INSERT statements
- Updated all query examples
- Updated table analysis and size reporting queries

### 2. Symfony Entity Updates

#### Updated Files:
- `src/Entity/Company.php`

#### Changes:
```php
// Before
#[ORM\Table(name: "companies")]

// After  
#[ORM\Table(name: "tender_companies")]
```

### 3. Foreign Key Relationships

The following tables maintain their relationships with the renamed table:

#### `tender_bid_winners` table:
- Foreign key: `company_id` → `tender_companies.id`
- Constraint: `fk_tender_bid_winner_company`

#### `company_directors` table:
- Foreign key: `company_id` → `tender_companies.id`
- Constraint: `fk_company_director_company`

## Migration Process

### For New Installations:
Use the updated `tender_management_tables.sql` file directly.

### For Existing Databases:
Run the `rename_companies_to_tender_companies.sql` migration script:

```bash
mysql -u username -p database_name < rename_companies_to_tender_companies.sql
```

## Table Structure

### `tender_companies` Table:
```sql
CREATE TABLE tender_companies (
    id INT AUTO_INCREMENT NOT NULL,
    company_names JSON NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    surname VARCHAR(255) NOT NULL,
    id_number VARCHAR(50) NOT NULL,
    residential_address LONGTEXT NOT NULL,
    company_address LONGTEXT NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    id_copy VARCHAR(255) DEFAULT NULL,
    power_of_attorney VARCHAR(255) DEFAULT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'active',
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    INDEX company_status_idx (status),
    INDEX company_id_number_idx (id_number),
    INDEX company_email_idx (email_address),
    INDEX company_created_idx (created)
);
```

## Relationships

```
tender_companies (1) ←→ (many) company_directors
tender_companies (1) ←→ (many) tender_bid_winners
tenders (1) ←→ (many) tender_bid_winners
```

## Verification Queries

After running the migration, verify the changes with these queries:

```sql
-- Check if table was renamed
SHOW TABLES LIKE '%company%';

-- Check foreign key constraints
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = DATABASE() 
AND REFERENCED_TABLE_NAME = 'tender_companies';

-- Test data integrity
SELECT COUNT(*) as company_count FROM tender_companies;
SELECT COUNT(*) as director_count FROM company_directors;
SELECT COUNT(*) as bid_winner_count FROM tender_bid_winners;
```

## No Changes Required

The following files automatically work with the renamed table:
- `src/Entity/TenderBidWinner.php` - References Company entity class
- `src/Entity/CompanyDirector.php` - References Company entity class
- All repository classes - Use entity relationships
- All service classes - Use entity relationships
- All controller classes - Use entity relationships

## Benefits of the Rename

1. **Clarity**: Table name clearly indicates its purpose in the tender management system
2. **Namespace Separation**: Distinguishes from other potential company-related tables
3. **Consistency**: Aligns with other tender-specific table names
4. **Maintainability**: Makes the database schema more self-documenting

## Rollback (if needed)

If you need to rollback the changes:

```sql
-- Rename back to companies
RENAME TABLE tender_companies TO companies;

-- Update foreign key constraints
ALTER TABLE tender_bid_winners 
DROP FOREIGN KEY fk_tender_bid_winner_company;
ALTER TABLE tender_bid_winners 
ADD CONSTRAINT fk_tender_bid_winner_company 
FOREIGN KEY (company_id) REFERENCES companies (id);

ALTER TABLE company_directors 
DROP FOREIGN KEY fk_company_director_company;
ALTER TABLE company_directors 
ADD CONSTRAINT fk_company_director_company 
FOREIGN KEY (company_id) REFERENCES companies (id);

-- Update Symfony entity
-- Change #[ORM\Table(name: "tender_companies")] back to #[ORM\Table(name: "companies")]
```

## Next Steps

1. Run the migration script if you have an existing database
2. Clear Symfony cache: `php bin/console cache:clear`
3. Test the API endpoints to ensure everything works correctly
4. Update any documentation that references the old table name 