# Tender Number Validation Implementation

## Overview
The tender management system now includes comprehensive validation to ensure tender numbers are unique across the database. This prevents duplicate tender entries and maintains data integrity.

## Implementation Details

### 1. Database Level Validation

#### Unique Constraint
- **Table**: `tenders`
- **Column**: `tender_number`
- **Constraint**: `UNIQUE KEY unique_tender_number (tender_number)`

#### SQL Schema Update
```sql
-- In tender_management_tables.sql
CREATE TABLE tenders (
    -- ... other columns ...
    tender_number VARCHAR(100) NOT NULL,
    -- ... other columns ...
    UNIQUE KEY unique_tender_number (tender_number),
    -- ... other indexes ...
);
```

#### Migration Script
For existing databases, run:
```bash
mysql -u username -p database_name < add_tender_number_unique_constraint.sql
```

### 2. Entity Level Validation

#### Tender Entity
```php
// src/Entity/Tender.php
#[ORM\Column(type: Types::STRING, length: 100, nullable: false, unique: true)]
private string $tenderNumber;
```

### 3. Service Level Validation

#### TenderService Methods

##### `createTender(array $data): Tender`
```php
public function createTender(array $data): Tender
{
    // Check if tender number already exists
    if (isset($data['tenderNumber'])) {
        $existingTender = $this->getTenderByNumber($data['tenderNumber']);
        if ($existingTender) {
            throw new \InvalidArgumentException("Tender number '{$data['tenderNumber']}' already exists");
        }
    }
    
    // ... rest of creation logic
}
```

##### `validateTenderData(array $data): array`
```php
public function validateTenderData(array $data): array
{
    $errors = [];
    
    // ... other validations ...
    
    // Check if tender number already exists (for new tenders)
    if (isset($data['tenderNumber']) && !empty($data['tenderNumber'])) {
        $existingTender = $this->getTenderByNumber($data['tenderNumber']);
        if ($existingTender) {
            $errors[] = "Tender number '{$data['tenderNumber']}' already exists";
        }
    }
    
    return $errors;
}
```

##### `tenderNumberExists(string $tenderNumber): bool`
```php
public function tenderNumberExists(string $tenderNumber): bool
{
    return $this->getTenderByNumber($tenderNumber) !== null;
}
```

### 4. Controller Level Validation

#### TenderController

##### Create Tender Endpoint
```php
#[Route('', name: 'tender_create', methods: ['POST'])]
public function create(Request $request): JsonResponse
{
    try {
        $data = json_decode($request->getContent(), true);
        
        // Validate tender data including tender number uniqueness
        $validationErrors = $this->tenderService->validateTenderData($data);
        if (!empty($validationErrors)) {
            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validationErrors
            ], Response::HTTP_BAD_REQUEST);
        }
        
        $tender = $this->tenderService->createTender($data);
        
        return $this->json([
            'success' => true,
            'data' => $tender->toArray(),
            'message' => 'Tender created successfully'
        ], Response::HTTP_CREATED);
        
    } catch (\Exception $e) {
        return $this->json([
            'success' => false,
            'message' => 'Error creating tender: ' . $e->getMessage()
        ], Response::HTTP_BAD_REQUEST);
    }
}
```

##### Check Tender Number Endpoint
```php
#[Route('/check-number/{tenderNumber}', name: 'tender_check_number', methods: ['GET'])]
public function checkTenderNumber(string $tenderNumber): JsonResponse
{
    $exists = $this->tenderService->tenderNumberExists($tenderNumber);
    
    return $this->json([
        'success' => true,
        'data' => [
            'tenderNumber' => $tenderNumber,
            'exists' => $exists
        ],
        'message' => $exists ? 'Tender number already exists' : 'Tender number is available'
    ]);
}
```

## API Endpoints

### 1. Create Tender
- **URL**: `POST /api/tenders`
- **Validation**: Checks for duplicate tender number
- **Response**: Success or validation errors

#### Example Request:
```json
{
    "category": "Construction",
    "tenderDescription": "Building construction project",
    "tenderNumber": "TEN-2024-001",
    "organOfState": "Department of Public Works",
    "province": "Gauteng",
    "closingDate": "2024-12-31T23:59:59",
    "placeWhereGoodsWorksOrServicesAreRequired": "Pretoria CBD",
    "contactPerson": "John Doe",
    "email": "john.doe@example.com",
    "telephoneNumber": "+27 12 345 6789"
}
```

#### Example Success Response:
```json
{
    "success": true,
    "data": {
        "id": 1,
        "tenderNumber": "TEN-2024-001",
        "category": "Construction",
        // ... other fields
    },
    "message": "Tender created successfully"
}
```

#### Example Error Response:
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": [
        "Tender number 'TEN-2024-001' already exists"
    ]
}
```

### 2. Check Tender Number Availability
- **URL**: `GET /api/tenders/check-number/{tenderNumber}`
- **Purpose**: Check if a tender number is available before creation
- **Response**: Boolean indicating existence

#### Example Request:
```
GET /api/tenders/check-number/TEN-2024-001
```

#### Example Response (Available):
```json
{
    "success": true,
    "data": {
        "tenderNumber": "TEN-2024-001",
        "exists": false
    },
    "message": "Tender number is available"
}
```

#### Example Response (Exists):
```json
{
    "success": true,
    "data": {
        "tenderNumber": "TEN-2024-001",
        "exists": true
    },
    "message": "Tender number already exists"
}
```

## Validation Flow

### 1. Frontend Validation (Optional)
```javascript
// Check tender number availability before form submission
async function checkTenderNumber(tenderNumber) {
    const response = await fetch(`/api/tenders/check-number/${tenderNumber}`);
    const data = await response.json();
    return data.data.exists;
}

// Usage
const exists = await checkTenderNumber('TEN-2024-001');
if (exists) {
    alert('Tender number already exists');
    return;
}
```

### 2. Backend Validation
1. **Controller**: Receives request and calls validation
2. **Service**: Validates data including tender number uniqueness
3. **Repository**: Checks database for existing tender number
4. **Database**: Enforces unique constraint as final safety net

### 3. Error Handling
- **Validation Errors**: Returned as array in response
- **Database Errors**: Caught and returned as user-friendly messages
- **Duplicate Key Errors**: Handled gracefully with clear error messages

## Testing

### 1. Unit Tests
```php
// tests/Service/TenderServiceTest.php
public function testCreateTenderWithDuplicateNumber(): void
{
    // Create first tender
    $data1 = $this->getValidTenderData();
    $this->tenderService->createTender($data1);
    
    // Try to create second tender with same number
    $data2 = $this->getValidTenderData(); // Same tender number
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Tender number 'TEN-2024-001' already exists");
    $this->tenderService->createTender($data2);
}

public function testTenderNumberExists(): void
{
    $data = $this->getValidTenderData();
    $this->tenderService->createTender($data);
    
    $this->assertTrue($this->tenderService->tenderNumberExists('TEN-2024-001'));
    $this->assertFalse($this->tenderService->tenderNumberExists('TEN-2024-999'));
}
```

### 2. API Tests
```php
// tests/Controller/TenderControllerTest.php
public function testCreateTenderWithDuplicateNumber(): void
{
    // Create first tender
    $this->client->request('POST', '/api/tenders', [], [], [
        'CONTENT_TYPE' => 'application/json'
    ], json_encode($this->getValidTenderData()));
    
    // Try to create second tender with same number
    $this->client->request('POST', '/api/tenders', [], [], [
        'CONTENT_TYPE' => 'application/json'
    ], json_encode($this->getValidTenderData()));
    
    $this->assertResponseStatusCodeSame(400);
    $response = json_decode($this->client->getResponse()->getContent(), true);
    $this->assertContains("Tender number 'TEN-2024-001' already exists", $response['errors']);
}
```

## Benefits

1. **Data Integrity**: Prevents duplicate tender numbers at multiple levels
2. **User Experience**: Clear error messages for validation failures
3. **Performance**: Efficient database queries with proper indexing
4. **Maintainability**: Centralized validation logic in service layer
5. **Flexibility**: Optional frontend validation for better UX
6. **Reliability**: Database constraint as final safety net

## Migration Notes

### For New Installations
- Use the updated `tender_management_tables.sql` file
- Unique constraint is automatically created

### For Existing Databases
1. Run the migration script: `add_tender_number_unique_constraint.sql`
2. Check for existing duplicates before adding constraint
3. Clear Symfony cache: `php bin/console cache:clear`
4. Test the validation endpoints

### Rollback (if needed)
```sql
-- Remove unique constraint
ALTER TABLE tenders DROP INDEX unique_tender_number;
```

## Security Considerations

1. **Input Sanitization**: All tender numbers are validated and sanitized
2. **SQL Injection**: Protected by Doctrine ORM parameter binding
3. **Rate Limiting**: Consider implementing rate limiting for check endpoints
4. **Access Control**: Ensure proper authentication and authorization 