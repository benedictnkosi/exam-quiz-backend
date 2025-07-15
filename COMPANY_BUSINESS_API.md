# Company & Business API Documentation

This document describes the REST API endpoints for managing companies and businesses.

## Base URL
```
/api
```

## Authentication
All endpoints require proper authentication. Include appropriate headers for your authentication method.

## Response Format
All API responses follow this standard format:
```json
{
  "status": "OK|NOK",
  "message": "Success or error message",
  "data": { ... }
}
```

---

## Companies API

### Create Company
**POST** `/api/companies`

Creates a new company record.

#### Request Body
```json
{
  "company_names": ["Company Name 1", "Company Name 2"],
  "full_name": "John",
  "surname": "Doe",
  "id_number": "1234567890123",
  "residential_address": "123 Main Street, City, Province, 1234",
  "company_address": "456 Business Ave, City, Province, 1234",
  "email_address": "john.doe@example.com",
  "phone_number": "+27123456789",
  "id_copy": "path/to/id_copy.pdf",
  "power_of_attorney": "path/to/power_of_attorney.pdf"
}
```

#### Required Fields
- `full_name`
- `surname`
- `id_number`
- `residential_address`
- `company_address`
- `email_address`
- `phone_number`

#### Optional Fields
- `company_names` (array of company names)
- `id_copy` (file path/URL)
- `power_of_attorney` (file path/URL)

#### Response
```json
{
  "status": "OK",
  "message": "Company created successfully",
  "data": {
    "id": 1,
    "company_names": ["Company Name 1", "Company Name 2"],
    "full_name": "John",
    "surname": "Doe",
    "id_number": "1234567890123",
    "residential_address": "123 Main Street, City, Province, 1234",
    "company_address": "456 Business Ave, City, Province, 1234",
    "email_address": "john.doe@example.com",
    "phone_number": "+27123456789",
    "id_copy": "path/to/id_copy.pdf",
    "power_of_attorney": "path/to/power_of_attorney.pdf"
  }
}
```

---

### Get All Companies
**GET** `/api/companies`

Retrieves all companies.

#### Response
```json
{
  "status": "OK",
  "data": [
    {
      "id": 1,
      "company_names": ["Company Name 1", "Company Name 2"],
      "full_name": "John",
      "surname": "Doe",
      "id_number": "1234567890123",
      "residential_address": "123 Main Street, City, Province, 1234",
      "company_address": "456 Business Ave, City, Province, 1234",
      "email_address": "john.doe@example.com",
      "phone_number": "+27123456789",
      "id_copy": "path/to/id_copy.pdf",
      "power_of_attorney": "path/to/power_of_attorney.pdf"
    }
  ]
}
```

---

### Get Company by ID
**GET** `/api/companies/{id}`

Retrieves a specific company by ID.

#### Parameters
- `id` (integer) - Company ID

#### Response
```json
{
  "status": "OK",
  "data": {
    "id": 1,
    "company_names": ["Company Name 1", "Company Name 2"],
    "full_name": "John",
    "surname": "Doe",
    "id_number": "1234567890123",
    "residential_address": "123 Main Street, City, Province, 1234",
    "company_address": "456 Business Ave, City, Province, 1234",
    "email_address": "john.doe@example.com",
    "phone_number": "+27123456789",
    "id_copy": "path/to/id_copy.pdf",
    "power_of_attorney": "path/to/power_of_attorney.pdf"
  }
}
```

#### Error Response (404)
```json
{
  "status": "NOK",
  "message": "Company not found"
}
```

---

### Update Company
**PUT** `/api/companies/{id}`

Updates an existing company.

#### Parameters
- `id` (integer) - Company ID

#### Request Body
```json
{
  "full_name": "Jane",
  "email_address": "jane.doe@example.com",
  "phone_number": "+27987654321"
}
```

#### Response
```json
{
  "status": "OK",
  "message": "Company updated successfully",
  "data": {
    "id": 1,
    "company_names": ["Company Name 1", "Company Name 2"],
    "full_name": "Jane",
    "surname": "Doe",
    "id_number": "1234567890123",
    "residential_address": "123 Main Street, City, Province, 1234",
    "company_address": "456 Business Ave, City, Province, 1234",
    "email_address": "jane.doe@example.com",
    "phone_number": "+27987654321",
    "id_copy": "path/to/id_copy.pdf",
    "power_of_attorney": "path/to/power_of_attorney.pdf"
  }
}
```

---

### Delete Company
**DELETE** `/api/companies/{id}`

Deletes a company and all associated businesses.

#### Parameters
- `id` (integer) - Company ID

#### Response
```json
{
  "status": "OK",
  "message": "Company deleted successfully"
}
```

---

## Businesses API

### Create Business
**POST** `/api/businesses`

Creates a new business record.

#### Request Body
```json
{
  "business_name": "My Business",
  "domain": "mybusiness.com",
  "email": "info@mybusiness.com",
  "contact_number": "+27123456789",
  "whatsapp_number": "+27123456789",
  "company_id": 1
}
```

#### Required Fields
- `business_name`
- `company_id`

#### Optional Fields
- `domain`
- `email`
- `contact_number`
- `whatsapp_number`

#### Response
```json
{
  "status": "OK",
  "message": "Business created successfully",
  "data": {
    "id": 1,
    "business_name": "My Business",
    "domain": "mybusiness.com",
    "email": "info@mybusiness.com",
    "contact_number": "+27123456789",
    "whatsapp_number": "+27123456789",
    "company_id": 1
  }
}
```

---

### Get All Businesses
**GET** `/api/businesses`

Retrieves all businesses.

#### Response
```json
{
  "status": "OK",
  "data": [
    {
      "id": 1,
      "business_name": "My Business",
      "domain": "mybusiness.com",
      "email": "info@mybusiness.com",
      "contact_number": "+27123456789",
      "whatsapp_number": "+27123456789",
      "company_id": 1
    }
  ]
}
```

---

### Get Business by ID
**GET** `/api/businesses/{id}`

Retrieves a specific business by ID.

#### Parameters
- `id` (integer) - Business ID

#### Response
```json
{
  "status": "OK",
  "data": {
    "id": 1,
    "business_name": "My Business",
    "domain": "mybusiness.com",
    "email": "info@mybusiness.com",
    "contact_number": "+27123456789",
    "whatsapp_number": "+27123456789",
    "company_id": 1
  }
}
```

---

### Get Businesses by Company
**GET** `/api/businesses/company/{companyId}`

Retrieves all businesses associated with a specific company.

#### Parameters
- `companyId` (integer) - Company ID

#### Response
```json
{
  "status": "OK",
  "data": [
    {
      "id": 1,
      "business_name": "My Business",
      "domain": "mybusiness.com",
      "email": "info@mybusiness.com",
      "contact_number": "+27123456789",
      "whatsapp_number": "+27123456789",
      "company_id": 1
    },
    {
      "id": 2,
      "business_name": "Another Business",
      "domain": "anotherbusiness.com",
      "email": "info@anotherbusiness.com",
      "contact_number": "+27987654321",
      "whatsapp_number": "+27987654321",
      "company_id": 1
    }
  ]
}
```

---

### Update Business
**PUT** `/api/businesses/{id}`

Updates an existing business.

#### Parameters
- `id` (integer) - Business ID

#### Request Body
```json
{
  "business_name": "Updated Business Name",
  "email": "newemail@mybusiness.com",
  "contact_number": "+27987654321"
}
```

#### Response
```json
{
  "status": "OK",
  "message": "Business updated successfully",
  "data": {
    "id": 1,
    "business_name": "Updated Business Name",
    "domain": "mybusiness.com",
    "email": "newemail@mybusiness.com",
    "contact_number": "+27987654321",
    "whatsapp_number": "+27123456789",
    "company_id": 1
  }
}
```

---

### Delete Business
**DELETE** `/api/businesses/{id}`

Deletes a business.

#### Parameters
- `id` (integer) - Business ID

#### Response
```json
{
  "status": "OK",
  "message": "Business deleted successfully"
}
```

---

## Error Responses

### Validation Error (400)
```json
{
  "status": "NOK",
  "message": "Field 'business_name' is required"
}
```

### Not Found Error (404)
```json
{
  "status": "NOK",
  "message": "Company not found"
}
```

### Server Error (500)
```json
{
  "status": "NOK",
  "message": "Failed to create company: Database connection error"
}
```

---

## Usage Examples

### cURL Examples

#### Create a Company
```bash
curl -X POST http://localhost:8000/api/companies \
  -H "Content-Type: application/json" \
  -d '{
    "company_names": ["Acme Corp", "Acme Corporation"],
    "full_name": "John",
    "surname": "Smith",
    "id_number": "1234567890123",
    "residential_address": "123 Main St, Johannesburg, Gauteng, 2000",
    "company_address": "456 Business Ave, Johannesburg, Gauteng, 2000",
    "email_address": "john.smith@acme.com",
    "phone_number": "+27123456789"
  }'
```

#### Create a Business
```bash
curl -X POST http://localhost:8000/api/businesses \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "Acme Online Store",
    "domain": "acmeonline.com",
    "email": "info@acmeonline.com",
    "contact_number": "+27123456789",
    "whatsapp_number": "+27123456789",
    "company_id": 1
  }'
```

#### Get All Companies
```bash
curl -X GET http://localhost:8000/api/companies
```

#### Get Businesses for a Company
```bash
curl -X GET http://localhost:8000/api/businesses/company/1
```

---

## Database Schema

### Companies Table
| Field | Type | Description |
|-------|------|-------------|
| id | SERIAL | Primary key |
| company_names | JSON | Array of company names |
| full_name | VARCHAR(255) | First name |
| surname | VARCHAR(255) | Last name |
| id_number | VARCHAR(50) | ID number |
| residential_address | TEXT | Residential address |
| company_address | TEXT | Company address |
| email_address | VARCHAR(255) | Email address |
| phone_number | VARCHAR(50) | Phone number |
| id_copy | VARCHAR(255) | ID copy file path |
| power_of_attorney | VARCHAR(255) | Power of attorney file path |

### Businesses Table
| Field | Type | Description |
|-------|------|-------------|
| id | SERIAL | Primary key |
| business_name | VARCHAR(255) | Business name |
| domain | VARCHAR(255) | Website domain |
| email | VARCHAR(255) | Business email |
| contact_number | VARCHAR(50) | Contact number |
| whatsapp_number | VARCHAR(50) | WhatsApp number |
| company_id | INTEGER | Foreign key to companies table |

---

---

## Document Upload API

### Upload ID Copy
**POST** `/api/documents/company/{companyId}/id-copy`

Uploads an ID copy document for a specific company.

#### Parameters
- `companyId` (integer) - Company ID

#### Request
- **Content-Type:** `multipart/form-data`
- **Body:** Form data with `document` field containing the file

#### Supported File Types
- PDF files
- Image files (JPEG, JPG, PNG, GIF)
- Maximum file size: 10MB

#### Response
```json
{
  "status": "OK",
  "message": "ID copy uploaded successfully",
  "data": {
    "filename": "uploads/documents/1_id_copy_john_doe_123456.pdf",
    "url": "/uploads/documents/1_id_copy_john_doe_123456.pdf"
  }
}
```

---

### Upload Power of Attorney
**POST** `/api/documents/company/{companyId}/power-of-attorney`

Uploads a power of attorney document for a specific company.

#### Parameters
- `companyId` (integer) - Company ID

#### Request
- **Content-Type:** `multipart/form-data`
- **Body:** Form data with `document` field containing the file

#### Supported File Types
- PDF files
- Image files (JPEG, JPG, PNG, GIF)
- Maximum file size: 10MB

#### Response
```json
{
  "status": "OK",
  "message": "Power of attorney uploaded successfully",
  "data": {
    "filename": "uploads/documents/1_power_of_attorney_legal_doc_789012.pdf",
    "url": "/uploads/documents/1_power_of_attorney_legal_doc_789012.pdf"
  }
}
```

---

### Delete ID Copy
**DELETE** `/api/documents/company/{companyId}/id-copy`

Deletes the ID copy document for a specific company.

#### Parameters
- `companyId` (integer) - Company ID

#### Response
```json
{
  "status": "OK",
  "message": "ID copy deleted successfully"
}
```

---

### Delete Power of Attorney
**DELETE** `/api/documents/company/{companyId}/power-of-attorney`

Deletes the power of attorney document for a specific company.

#### Parameters
- `companyId` (integer) - Company ID

#### Response
```json
{
  "status": "OK",
  "message": "Power of attorney deleted successfully"
}
```

---

## Usage Examples

### cURL Examples

#### Upload ID Copy
```bash
curl -X POST http://localhost:8000/api/documents/company/1/id-copy \
  -F "document=@/path/to/id_copy.pdf"
```

#### Upload Power of Attorney
```bash
curl -X POST http://localhost:8000/api/documents/company/1/power-of-attorney \
  -F "document=@/path/to/power_of_attorney.pdf"
```

#### Delete ID Copy
```bash
curl -X DELETE http://localhost:8000/api/documents/company/1/id-copy
```

---

## Notes

- Document uploads are handled separately from company creation/updates
- Files are stored in `/public/uploads/documents/` directory
- Filenames are automatically generated with company ID, document type, and unique identifier
- Only PDF and image files are allowed (max 10MB)
- When a company is deleted, associated documents should be manually cleaned up
- The `company_names` field stores an array of company names as JSON
- When a company is deleted, all associated businesses are also deleted (cascade)
- All phone numbers should include country code (e.g., +27 for South Africa)
- Email addresses should be valid email format
- ID numbers should be unique per company 