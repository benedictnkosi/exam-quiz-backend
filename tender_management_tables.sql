-- Tender Management System Database Schema
-- This file contains all SQL statements to create the database tables

-- =====================================================
-- TENDERS TABLE
-- =====================================================
CREATE TABLE tenders (
    id INT AUTO_INCREMENT NOT NULL,
    category VARCHAR(255) NOT NULL,
    tender_description LONGTEXT NOT NULL,
    advertised_at DATETIME DEFAULT NULL,
    awarded_at DATETIME DEFAULT NULL,
    tender_number VARCHAR(100) NOT NULL,
    organ_of_state VARCHAR(255) NOT NULL,
    province VARCHAR(100) NOT NULL,
    closing_date DATETIME NOT NULL,
    place_where_goods_works_or_services_are_required LONGTEXT NOT NULL,
    contact_person VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telephone_number VARCHAR(50) NOT NULL,
    successful_bidders JSON DEFAULT NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    UNIQUE KEY unique_tender_number (tender_number),
    INDEX tender_category_idx (category),
    INDEX tender_province_idx (province),
    INDEX tender_organ_of_state_idx (organ_of_state),
    INDEX tender_closing_date_idx (closing_date),
    INDEX tender_created_idx (created),
    INDEX tender_updated_idx (updated)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;


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


-- =====================================================
-- TENDER BID WINNERS TABLE
-- =====================================================
CREATE TABLE tender_bid_winners (
    id INT AUTO_INCREMENT NOT NULL,
    tender_id INT NOT NULL,
    company_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    INDEX tender_bid_winner_tender_idx (tender_id),
    INDEX tender_bid_winner_company_idx (company_id),
    INDEX tender_bid_winner_company_name_idx (company_name),
    INDEX tender_bid_winner_created_idx (created),
    CONSTRAINT fk_tender_bid_winner_tender 
        FOREIGN KEY (tender_id) REFERENCES tenders (id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tender_bid_winner_company 
        FOREIGN KEY (company_id) REFERENCES tender_companies (id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    -- Ensure a company can only win a specific tender once
    UNIQUE KEY unique_tender_company (tender_id, company_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- =====================================================
-- COMPANY DIRECTORS TABLE
-- =====================================================
CREATE TABLE company_directors (
    id INT AUTO_INCREMENT NOT NULL,
    director_name VARCHAR(255) NOT NULL,
    director_id_number VARCHAR(50) NOT NULL,
    company_id INT NOT NULL,
    created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY(id),
    INDEX company_director_company_idx (company_id),
    INDEX company_director_id_number_idx (director_id_number),
    INDEX company_director_name_idx (director_name),
    INDEX company_director_created_idx (created),
    CONSTRAINT fk_company_director_company 
        FOREIGN KEY (company_id) REFERENCES tender_companies (id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    -- Ensure a director can only be associated with a company once with a specific ID number
    UNIQUE KEY unique_company_director_id (company_id, director_id_number)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- =====================================================
-- SAMPLE DATA INSERTIONS
-- =====================================================

-- Insert sample companies
INSERT INTO tender_companies (company_names, full_name, surname, id_number, residential_address, company_address, email_address, phone_number, status) VALUES
('["ABC Construction Ltd", "ABC Construction"]', 'John', 'Doe', '8001015009087', '123 Main Street, Pretoria, Gauteng', '456 Business Park, Johannesburg, Gauteng', 'john.doe@abcconstruction.com', '+27 11 123 4567', 'active'),
('["XYZ Engineering", "XYZ Engineering Services"]', 'Jane', 'Smith', '8502156001234', '789 Oak Avenue, Cape Town, Western Cape', '321 Industrial Zone, Cape Town, Western Cape', 'jane.smith@xyzengineering.com', '+27 21 987 6543', 'active'),
('["DEF Infrastructure", "DEF Infrastructure Solutions"]', 'Mike', 'Johnson', '9003307005678', '456 Pine Road, Durban, KwaZulu-Natal', '654 Harbour View, Durban, KwaZulu-Natal', 'mike.johnson@definfrastructure.com', '+27 31 456 7890', 'active');

-- Insert sample tenders
INSERT INTO tenders (category, tender_description, advertised_at, tender_number, organ_of_state, province, closing_date, place_where_goods_works_or_services_are_required, contact_person, email, telephone_number, successful_bidders) VALUES
('Construction', 'Building construction project for new office complex in Pretoria CBD', '2024-01-15 10:00:00', 'TEN-2024-001', 'Department of Public Works', 'Gauteng', '2024-12-31 23:59:59', 'Pretoria CBD, Gauteng', 'John Doe', 'john.doe@example.com', '+27 12 345 6789', '[]'),
('IT Services', 'Development of web application for government portal', '2024-02-01 09:00:00', 'TEN-2024-002', 'Department of Communications', 'Western Cape', '2024-06-30 23:59:59', 'Cape Town, Western Cape', 'Jane Smith', 'jane.smith@example.com', '+27 21 123 4567', '[]'),
('Infrastructure', 'Road construction and maintenance project', '2024-01-20 14:00:00', 'TEN-2024-003', 'Department of Transport', 'KwaZulu-Natal', '2024-08-15 23:59:59', 'Durban, KwaZulu-Natal', 'Mike Johnson', 'mike.johnson@example.com', '+27 31 789 0123', '[]');

-- Insert sample tender bid winners
INSERT INTO tender_bid_winners (tender_id, company_id, company_name) VALUES
(1, 1, 'ABC Construction Ltd'),
(2, 2, 'XYZ Engineering'),
(3, 3, 'DEF Infrastructure');

-- Insert sample company directors
INSERT INTO company_directors (director_name, director_id_number, company_id) VALUES
('John Doe', '8001015009087', 1),
('Jane Smith', '8502156001234', 2),
('Mike Johnson', '9003307005678', 3),
('Sarah Wilson', '8704258009012', 1),
('David Brown', '9206109003456', 2);

-- =====================================================
-- USEFUL QUERIES
-- =====================================================

-- Get all tenders with their bid winners
SELECT 
    t.id,
    t.tender_number,
    t.category,
    t.tender_description,
    t.organ_of_state,
    t.province,
    t.closing_date,
    tbw.company_name as winning_company
FROM tenders t
LEFT JOIN tender_bid_winners tbw ON t.id = tbw.tender_id
ORDER BY t.created DESC;

-- Get companies with their directors
SELECT 
    c.id,
    c.full_name,
    c.surname,
    c.email_address,
    cd.director_name,
    cd.director_id_number
FROM tender_companies c
LEFT JOIN company_directors cd ON c.id = cd.company_id
WHERE c.status = 'active'
ORDER BY c.created DESC;

-- Get companies that have won tenders
SELECT 
    c.id,
    c.full_name,
    c.surname,
    c.email_address,
    COUNT(tbw.id) as tender_wins
FROM tender_companies c
INNER JOIN tender_bid_winners tbw ON c.id = tbw.company_id
GROUP BY c.id, c.full_name, c.surname, c.email_address
ORDER BY tender_wins DESC;

-- Get directors who serve multiple companies
SELECT 
    cd.director_name,
    cd.director_id_number,
    COUNT(DISTINCT cd.company_id) as companies_served
FROM company_directors cd
GROUP BY cd.director_name, cd.director_id_number
HAVING companies_served > 1
ORDER BY companies_served DESC;

-- Get active tenders (not yet closed)
SELECT 
    id,
    tender_number,
    category,
    organ_of_state,
    province,
    closing_date,
    DATEDIFF(closing_date, NOW()) as days_remaining
FROM tenders 
WHERE closing_date > NOW()
ORDER BY closing_date ASC;

-- Get awarded tenders
SELECT 
    t.id,
    t.tender_number,
    t.category,
    t.organ_of_state,
    t.awarded_at,
    tbw.company_name as winning_company
FROM tenders t
INNER JOIN tender_bid_winners tbw ON t.id = tbw.tender_id
WHERE t.awarded_at IS NOT NULL
ORDER BY t.awarded_at DESC;

-- =====================================================
-- INDEX OPTIMIZATION QUERIES
-- =====================================================

-- Analyze table performance
ANALYZE TABLE tenders;
ANALYZE TABLE tender_companies;
ANALYZE TABLE tender_bid_winners;
ANALYZE TABLE company_directors;

-- Show table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)',
    table_rows
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name IN ('tenders', 'tender_companies', 'tender_bid_winners', 'company_directors')
ORDER BY (data_length + index_length) DESC;

-- =====================================================
-- CLEANUP QUERIES (if needed)
-- =====================================================

-- Drop tables in correct order (if needed)
-- DROP TABLE IF EXISTS company_directors;
-- DROP TABLE IF EXISTS tender_bid_winners;
-- DROP TABLE IF EXISTS tenders;
-- DROP TABLE IF EXISTS tender_companies; 