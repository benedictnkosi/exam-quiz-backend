# Tender Management API - Postman Collection

This document provides instructions for importing and using the Postman collection for the Tender Management API.

## Files Included

1. **`Tender_Management_API.postman_collection.json`** - Main Postman collection
2. **`Tender_Management_Environment.postman_environment.json`** - Environment variables
3. **`POSTMAN_COLLECTION_README.md`** - This documentation file

## Import Instructions

### Step 1: Import the Collection
1. Open Postman
2. Click on "Import" button
3. Select "Upload Files" tab
4. Choose `Tender_Management_API.postman_collection.json`
5. Click "Import"

### Step 2: Import the Environment
1. In Postman, click on "Import" again
2. Select "Upload Files" tab
3. Choose `Tender_Management_Environment.postman_environment.json`
4. Click "Import"

### Step 3: Select Environment
1. In the top-right corner of Postman, click on the environment dropdown
2. Select "Tender Management Environment"

## Collection Structure

The collection is organized into 4 main folders:

### 1. Tenders
Contains all API endpoints for managing tenders:
- **Get All Tenders** - List tenders with filtering and pagination
- **Get Tender by ID** - Get specific tender details
- **Create Tender** - Create a new tender
- **Update Tender** - Update existing tender
- **Delete Tender** - Delete a tender
- **Get Tenders by Category** - Filter tenders by category
- **Get Tenders by Province** - Filter tenders by province
- **Get Active Tenders** - Get tenders that haven't closed
- **Get Awarded Tenders** - Get tenders that have been awarded
- **Add Successful Bidder** - Add bidder to tender's successful bidders list

### 2. Tender Bid Winners
Contains all API endpoints for managing tender bid winners:
- **Get All Tender Bid Winners** - List bid winners with filtering
- **Get Tender Bid Winner by ID** - Get specific bid winner details
- **Create Tender Bid Winner** - Create a new bid winner record
- **Update Tender Bid Winner** - Update existing bid winner
- **Delete Tender Bid Winner** - Delete a bid winner record
- **Get Bid Winners by Tender** - Get all winners for a specific tender
- **Get Bid Winners by Company** - Get all wins for a specific company
- **Get Winning Companies** - Get all companies that have won tenders
- **Get Recent Bid Winners** - Get recent bid winner records

### 3. Company Directors
Contains all API endpoints for managing company directors:
- **Get All Company Directors** - List directors with filtering
- **Get Company Director by ID** - Get specific director details
- **Create Company Director** - Create a new director record
- **Update Company Director** - Update existing director
- **Delete Company Director** - Delete a director record
- **Get Directors by Company** - Get all directors for a specific company
- **Search Directors by Name** - Search directors by name
- **Get Director by ID Number** - Get director by ID number
- **Get Unique Director Names** - Get all unique director names
- **Get Directors of Multiple Companies** - Get directors who serve multiple companies
- **Get Recent Directors** - Get recent director records

### 4. Companies
Contains basic API endpoints for managing companies:
- **Get All Companies** - List companies with pagination
- **Get Company by ID** - Get specific company details
- **Create Company** - Create a new company
- **Update Company** - Update existing company
- **Delete Company** - Delete a company

## Environment Variables

The environment includes the following variables:

| Variable | Description | Default Value |
|----------|-------------|---------------|
| `base_url` | Base URL for the API | `http://localhost:8000` |
| `api_version` | API version | `v1` |
| `content_type` | Content type for requests | `application/json` |
| `auth_token` | Authentication token (if needed) | Empty |
| `tender_id` | Default tender ID for testing | `1` |
| `company_id` | Default company ID for testing | `1` |
| `director_id` | Default director ID for testing | `1` |
| `bid_winner_id` | Default bid winner ID for testing | `1` |

## Usage Examples

### Creating a Complete Tender Workflow

1. **Create a Company**
   - Use "Create Company" request
   - Note the returned company ID

2. **Create a Tender**
   - Use "Create Tender" request
   - Note the returned tender ID

3. **Add Company Directors**
   - Use "Create Company Director" request
   - Use the company ID from step 1

4. **Create Tender Bid Winner**
   - Use "Create Tender Bid Winner" request
   - Use the tender ID from step 2 and company ID from step 1

### Testing Different Scenarios

#### Filtering Tenders
- Use query parameters in "Get All Tenders":
  - `category=Construction`
  - `province=Gauteng`
  - `organOfState=Department of Public Works`

#### Searching Directors
- Use "Search Directors by Name" with partial names
- Use "Get Director by ID Number" with specific ID numbers

#### Analyzing Bid Winners
- Use "Get Winning Companies" to see all companies that have won tenders
- Use "Get Directors of Multiple Companies" to identify directors serving multiple companies

## Request Examples

### Create Tender
```json
{
    "category": "Construction",
    "tenderDescription": "Building construction project for new office complex",
    "advertisedAt": "2024-01-15 10:00:00",
    "tenderNumber": "TEN-2024-001",
    "organOfState": "Department of Public Works",
    "province": "Gauteng",
    "closingDate": "2024-12-31 23:59:59",
    "placeWhereGoodsWorksOrServicesAreRequired": "Pretoria, Gauteng",
    "contactPerson": "John Doe",
    "email": "john.doe@example.com",
    "telephoneNumber": "+27 12 345 6789"
}
```

### Create Company Director
```json
{
    "directorName": "John Doe",
    "directorIdNumber": "8001015009087",
    "companyId": 1
}
```

### Create Tender Bid Winner
```json
{
    "tenderId": 1,
    "companyId": 1,
    "companyName": "ABC Construction Ltd"
}
```

## Response Format

All API responses follow this format:

```json
{
    "success": true,
    "data": {
        // Response data here
    },
    "message": "Operation completed successfully"
}
```

Error responses:
```json
{
    "success": false,
    "message": "Error description"
}
```

## Testing Tips

1. **Start with GET requests** to understand the current data structure
2. **Use the environment variables** to easily switch between different IDs
3. **Test error scenarios** by sending invalid data
4. **Use the filtering options** to test pagination and search functionality
5. **Create test data** using the POST requests before testing other operations

## Troubleshooting

### Common Issues

1. **Connection refused**: Make sure your Symfony server is running on `http://localhost:8000`
2. **404 errors**: Check that the API routes are properly configured
3. **Validation errors**: Ensure all required fields are provided in the request body
4. **Foreign key errors**: Make sure referenced entities (companies, tenders) exist before creating related records

### Environment Setup

If you need to change the base URL for different environments:

1. **Local Development**: `http://localhost:8000`
2. **Staging**: `https://staging-api.example.com`
3. **Production**: `https://api.example.com`

Update the `base_url` variable in the environment settings.

## Support

For issues with the API endpoints, refer to the main API documentation:
- `TENDER_API.md` - Tender API documentation
- `TENDER_BID_WINNERS_AND_DIRECTORS_API.md` - Bid winners and directors API documentation 