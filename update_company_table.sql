-- Update Company Table Structure
-- This script updates the tender_companies table with new enterprise fields

-- First, drop the existing table if it exists (be careful with this in production!)
-- DROP TABLE IF EXISTS tender_companies;

-- Create the updated tender_companies table
CREATE TABLE IF NOT EXISTS tender_companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enterprise_number VARCHAR(255) UNIQUE NOT NULL,
    enterprise_name VARCHAR(255) NOT NULL,
    enterprise_type VARCHAR(100) NOT NULL,
    enterprise_status VARCHAR(100) NOT NULL,
    compliance_notice VARCHAR(255) NULL,
    registration_date DATE NULL,
    physical_address TEXT NOT NULL,
    postal_address TEXT NULL,
    status VARCHAR(32) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for better performance
    INDEX idx_enterprise_number (enterprise_number),
    INDEX idx_enterprise_name (enterprise_name),
    INDEX idx_enterprise_type (enterprise_type),
    INDEX idx_enterprise_status (enterprise_status),
    INDEX idx_status (status)
);

-- If you need to migrate existing data, you can use this approach:
-- (Uncomment and modify as needed)

/*
-- Example migration from old structure to new structure
ALTER TABLE tender_companies 
ADD COLUMN enterprise_number VARCHAR(255) UNIQUE AFTER id,
ADD COLUMN enterprise_name VARCHAR(255) AFTER enterprise_number,
ADD COLUMN enterprise_type VARCHAR(100) AFTER enterprise_name,
ADD COLUMN enterprise_status VARCHAR(100) AFTER enterprise_type,
ADD COLUMN compliance_notice VARCHAR(255) NULL AFTER enterprise_status,
ADD COLUMN registration_date DATE NULL AFTER compliance_notice,
ADD COLUMN physical_address TEXT AFTER registration_date,
ADD COLUMN postal_address TEXT NULL AFTER physical_address;

-- Add indexes
ALTER TABLE tender_companies 
ADD INDEX idx_enterprise_number (enterprise_number),
ADD INDEX idx_enterprise_name (enterprise_name),
ADD INDEX idx_enterprise_type (enterprise_type),
ADD INDEX idx_enterprise_status (enterprise_status);

-- Drop old columns if they exist
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS company_names;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS full_name;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS surname;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS id_number;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS residential_address;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS company_address;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS email_address;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS phone_number;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS id_copy;
-- ALTER TABLE tender_companies DROP COLUMN IF EXISTS power_of_attorney;
*/

-- Sample data insertion
INSERT INTO tender_companies (
    enterprise_number,
    enterprise_name,
    enterprise_type,
    enterprise_status,
    compliance_notice,
    registration_date,
    physical_address,
    postal_address,
    status
) VALUES (
    'K2022836785',
    'ALUVE GUESTHOUSE',
    'Private Company',
    'In Business',
    'NONE',
    '2022-11-18',
    '187 KITCHENER AVENUE
KENSINGTON
JHB
GAUTENG
2194',
    NULL,
    'active'
);

-- Additional sample data
INSERT INTO tender_companies (
    enterprise_number,
    enterprise_name,
    enterprise_type,
    enterprise_status,
    compliance_notice,
    registration_date,
    physical_address,
    postal_address,
    status
) VALUES 
(
    'K2023456789',
    'TASC BUSINESS CONSULTING AND TRAINING',
    'Private Company',
    'In Business',
    'NONE',
    '2023-01-15',
    '123 Business Street
Pretoria
Gauteng
0001',
    'PO Box 123
Pretoria
0001',
    'active'
),
(
    'K2023987654',
    'BUSINESS OPTIMIZATION TRAINING INSTITUTE',
    'Private Company',
    'In Business',
    'NONE',
    '2023-03-20',
    '456 Training Avenue
Johannesburg
Gauteng
2000',
    'PO Box 456
Johannesburg
2000',
    'active'
); 