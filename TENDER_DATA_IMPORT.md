# Tender Data Import Documentation

This document explains how to import tender data from the external etenders.gov.za API into your local database.

## Overview

The tender data import functionality allows you to:
- Fetch tender data from the external etenders.gov.za API
- Process and validate the data
- Save it to your local database
- Handle duplicates and validation errors

## API Endpoints

### Import Tender Data (Single Batch)
**POST** `/api/tenders/import`

### Import Tender Data (Batch Processing)
**POST** `/api/tenders/import-batch`

**Request Body (Single Batch):**
```json
{
    "apiUrl": "https://www.etenders.gov.za/Home/PaginatedTenderOpportunities?...",
    "start": 0,
    "length": 10,
    "draw": 1
}
```

**Request Body (Batch Processing):**
```json
{
    "apiUrl": "https://www.etenders.gov.za/Home/PaginatedTenderOpportunities?...",
    "batchSize": 50,
    "maxRecords": 20000,
    "startFrom": 0
}
```

**Response (Single Batch):**
```json
{
    "success": true,
    "data": {
        "total_processed": 10,
        "successful": 8,
        "skipped": 2,
        "errors": [
            "Tender number 'TNPA/2025/07/0002/100350/RFQ' already exists",
            "Validation failed: Field 'email' is required"
        ]
    },
    "message": "Tender data import completed"
}
```

**Response (Batch Processing):**
```json
{
    "success": true,
    "data": {
        "total_processed": 20000,
        "successful": 19850,
        "skipped": 150,
        "errors": [
            "Tender number 'TNPA/2025/07/0002/100350/RFQ' already exists"
        ],
        "batches_processed": 400,
        "start_from": 0,
        "end_at": 19950
    },
    "message": "Batch tender data import completed"
}
```

## Command Line Usage

### Import Command
```bash
# Single batch import using default URL and parameters
php bin/console app:import-tender-data

# Single batch import using custom URL
php bin/console app:import-tender-data --url="https://www.etenders.gov.za/Home/PaginatedTenderOpportunities?..."

# Single batch import with custom pagination parameters
php bin/console app:import-tender-data --start=20 --length=50 --draw=2

# Batch import for large datasets (recommended for 20,000 records)
php bin/console app:import-tender-data --batch --batch-size=50 --max-records=20000 --start-from=0

# Batch import with custom parameters
php bin/console app:import-tender-data --batch --url="https://www.etenders.gov.za/Home/PaginatedTenderOpportunities?..." --batch-size=100 --max-records=5000 --start-from=1000
```

### Command Options
- `--url` or `-u`: Specify the external API URL (optional, has default)
- `--start` or `-s`: Starting record number for single batch (default: 0)
- `--length` or `-l`: Number of records to fetch for single batch (default: 10, max: 100)
- `--draw` or `-d`: Draw parameter for DataTables (default: 1)
- `--batch` or `-b`: Enable batch processing mode
- `--batch-size`: Number of records per batch (default: 50, max: 100)
- `--max-records`: Maximum number of records to import (optional)
- `--start-from`: Starting record number for batch processing (default: 0)

## Data Mapping

The import process maps external API data to your local tender entity:

| External API Field | Local Entity Field | Notes |
|-------------------|-------------------|-------|
| `category` | `category` | Direct mapping |
| `description` | `tenderDescription` | Direct mapping |
| `tender_No` | `tenderNumber` | Used for uniqueness check |
| `organ_of_State` or `department` | `organOfState` | Fallback to department if organ_of_State not available |
| `province` | `province` | Direct mapping |
| `date_Published` | `advertisedAt` | Date parsing with fallback |
| `awardDate` | `awardedAt` | Date parsing with fallback |
| `closing_Date` | `closingDate` | Date parsing with fallback |
| `contactPerson` | `contactPerson` | Direct mapping |
| `email` | `email` | Direct mapping |
| `telephone` | `telephoneNumber` | Direct mapping |
| `awards[].company` | `successfulBidders` | Array of company names |

### Location Building
The `placeWhereGoodsWorksOrServicesAreRequired` field is built from:
- `streetname`
- `surburb`
- `town`
- `code`

Combined as: "streetname, surburb, town, code"

## Parameters

### Single Batch Parameters

The single batch import process supports the following parameters:

- **start**: Starting record number (0-based index)
- **length**: Number of records to fetch (1-100)
- **draw**: DataTables draw parameter for tracking requests

### Batch Processing Parameters

The batch processing mode supports the following parameters:

- **batchSize**: Number of records per batch (1-100, default: 50)
- **maxRecords**: Maximum number of records to import (optional)
- **startFrom**: Starting record number for batch processing (default: 0)

These parameters are used to control which portion of the external API data to import and how to process large datasets efficiently.

## Validation

The import process includes several validation steps:

1. **Parameter Validation**: Validates pagination parameters
2. **Tender Number Uniqueness**: Checks if tender number already exists in database
3. **Required Fields**: Validates all required fields are present
4. **Email Format**: Validates email format
5. **Date Format**: Validates and parses date strings

## Error Handling

### Common Errors
- **Missing tender number**: Tender data without a tender number is skipped
- **Duplicate tender number**: Tenders with existing numbers are skipped
- **Validation failures**: Tenders with invalid data are skipped
- **API errors**: Network or API errors are logged and reported

### Error Reporting
Errors are collected and returned in the response, including:
- Total processed count
- Successful imports count
- Skipped count
- Detailed error messages

## Logging

The import process logs:
- Start and completion of import
- API response details
- Individual tender processing results
- Errors and warnings

Logs can be found in your application's log files.

## Example Usage

### Using the API Endpoint
```bash
# Single batch import
curl -X POST http://localhost:8000/api/tenders/import \
  -H "Content-Type: application/json" \
  -d '{
    "apiUrl": "https://www.etenders.gov.za/Home/PaginatedTenderOpportunities?draw=3&columns%5B0%5D%5Bdata%5D=&columns%5B0%5D%5Bname%5D=&columns%5B0%5D%5Bsearchable%5D=true&columns%5B0%5D%5Borderable%5D=false&columns%5B0%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B0%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B1%5D%5Bdata%5D=category&columns%5B1%5D%5Bname%5D=&columns%5B1%5D%5Bsearchable%5D=true&columns%5B1%5D%5Borderable%5D=true&columns%5B1%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B1%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B2%5D%5Bdata%5D=description&columns%5B2%5D%5Bname%5D=&columns%5B2%5D%5Bsearchable%5D=true&columns%5B2%5D%5Borderable%5D=false&columns%5B2%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B2%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B3%5D%5Bdata%5D=eSubmission&columns%5B3%5D%5Bname%5D=&columns%5B3%5D%5Bsearchable%5D=true&columns%5B3%5D%5Borderable%5D=true&columns%5B3%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B3%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B4%5D%5Bdata%5D=date_Published&columns%5B4%5D%5Bname%5D=&columns%5B4%5D%5Bsearchable%5D=true&columns%5B4%5D%5Borderable%5D=true&columns%5B4%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B4%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B5%5D%5Bdata%5D=awardDate&columns%5B5%5D%5Bname%5D=&columns%5B5%5D%5Bsearchable%5D=true&columns%5B5%5D%5Borderable%5D=true&columns%5B5%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B5%5D%5Bsearch%5D%5Bregex%5D=false&columns%5B6%5D%5Bdata%5D=actions&columns%5B6%5D%5Bname%5D=&columns%5B6%5D%5Bsearchable%5D=true&columns%5B6%5D%5Borderable%5D=true&columns%5B6%5D%5Bsearch%5D%5Bvalue%5D=&columns%5B6%5D%5Bsearch%5D%5Bregex%5D=false&order%5B0%5D%5Bcolumn%5D=2&order%5B0%5D%5Bdir%5D=desc&start=10&length=10&search%5Bvalue%5D=&search%5Bregex%5D=false&status=2&_=1753713431584"
  }'

# Batch import for large datasets
curl -X POST http://localhost:8000/api/tenders/import-batch \
  -H "Content-Type: application/json" \
  -d '{
    "apiUrl": "https://www.etenders.gov.za/Home/PaginatedTenderOpportunities?...",
    "batchSize": 50,
    "maxRecords": 20000,
    "startFrom": 0
  }'
```

### Using the Command Line
```bash
# Single batch import with default parameters
php bin/console app:import-tender-data

# Single batch import with custom parameters
php bin/console app:import-tender-data --start=20 --length=50 --draw=2

# Batch import for 20,000 records (recommended)
php bin/console app:import-tender-data --batch --batch-size=50 --max-records=20000 --start-from=0

# Batch import with custom parameters
php bin/console app:import-tender-data --batch --url="https://www.etenders.gov.za/Home/PaginatedTenderOpportunities?..." --batch-size=100 --max-records=5000 --start-from=1000
```

## Security Considerations

- The import process validates URLs before making requests
- User-Agent headers are set to mimic a browser
- Timeout limits are set to prevent hanging requests
- Error messages are sanitized to prevent information disclosure

## Performance Considerations

- The import process processes tenders one by one to avoid memory issues
- Database transactions are used for data consistency
- Progress reporting is available for long-running imports
- Error handling prevents one bad record from stopping the entire import

## Troubleshooting

### Common Issues

1. **API Timeout**: Increase timeout settings in the service
2. **Memory Issues**: Process smaller batches of data
3. **Duplicate Errors**: Check if tenders already exist before import
4. **Validation Errors**: Review the external API data format

### Debug Mode
Enable debug logging to see detailed information about the import process:

```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        main:
            level: debug
```

## Related Files

- `src/Service/TenderService.php` - Main import logic
- `src/Controller/TenderController.php` - API endpoint
- `src/Command/ImportTenderDataCommand.php` - Command line interface
- `src/Entity/Tender.php` - Tender entity definition 