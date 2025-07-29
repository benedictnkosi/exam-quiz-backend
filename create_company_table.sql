-- Create Company Table with Enterprise Fields
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

-- Insert sample data
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