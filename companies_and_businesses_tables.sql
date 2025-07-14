-- Table: companies
CREATE TABLE companies (
    id SERIAL PRIMARY KEY,
    company_names JSON NOT NULL, -- Array of company names
    full_name VARCHAR(255) NOT NULL,
    surname VARCHAR(255) NOT NULL,
    id_number VARCHAR(50) NOT NULL,
    residential_address TEXT NOT NULL,
    company_address TEXT NOT NULL,
    email_address VARCHAR(255) NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    id_copy VARCHAR(255), -- File path or URL
    power_of_attorney VARCHAR(255) -- File path or URL
);

-- Table: businesses
CREATE TABLE businesses (
    id SERIAL PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    domain VARCHAR(255),
    email VARCHAR(255),
    contact_number VARCHAR(50),
    whatsapp_number VARCHAR(50),
    company_id INTEGER NOT NULL,
    CONSTRAINT fk_company FOREIGN KEY(company_id) REFERENCES companies(id)
); 