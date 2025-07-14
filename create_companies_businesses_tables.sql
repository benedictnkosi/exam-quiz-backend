-- Create companies table
CREATE TABLE companies (
    id INT AUTO_INCREMENT NOT NULL,
    company_names JSON NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    surname VARCHAR(255) NOT NULL,
    id_number VARCHAR(50) NOT NULL,
    residential_address LONGTEXT NOT NULL,
    company_address LONGTEXT NOT NULL,§
    email_address VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    id_copy VARCHAR(255) DEFAULT NULL,
    power_of_attorney VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY(id),
    INDEX full_name_idx (full_name),
    INDEX surname_idx (surname),
    INDEX id_number_idx (id_number),
    INDEX email_address_idx (email_address),
    INDEX phone_number_idx (phone_number)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

-- Create businesses table
CREATE TABLE businesses (
    id INT AUTO_INCREMENT NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    contact_number VARCHAR(50) DEFAULT NULL,
    whatsapp_number VARCHAR(50) DEFAULT NULL,
    company_id INT NOT NULL,
    PRIMARY KEY(id),
    INDEX business_name_idx (business_name),
    INDEX domain_idx (domain),
    INDEX email_idx (email),
    INDEX contact_number_idx (contact_number),
    INDEX whatsapp_number_idx (whatsapp_number),
    INDEX company_id_idx (company_id),
    CONSTRAINT fk_businesses_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB; 