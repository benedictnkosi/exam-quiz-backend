# Accounting Questions API Documentation

## Base URL
```
http://localhost:8000/api/accounting-questions
```

## Authentication
All endpoints are currently public (no authentication required).

---

## 📥 **Import Questions**

### **POST** `/api/accounting-questions/import`

Import accounting questions from JSON format.

#### **Parameters:**
- `overwrite` (query, optional): Set to `true` to update existing questions instead of skipping them

#### **Request Body:**
```json
{
  "topic": "Statement of Comprehensive Income",
  "level": "Level 1: Basics",
  "questions": [
    {
      "id": "sci_q1",
      "type": "tap-to-select",
      "prompt": "Is Rent Income an income or an expense?",
      "options": ["Income", "Expense"],
      "answer": "Income"
    },
    {
      "id": "sci_q2",
      "type": "categorise",
      "prompt": "Drag each item to the correct category.",
      "categories": ["Income", "Operating Expense"],
      "items": {
        "Rent Income": "Income",
        "Packing Material": "Operating Expense",
        "Service Fees": "Income",
        "Audit Fees": "Operating Expense"
      }
    },
    {
      "id": "sci_q3",
      "type": "multi-step",
      "context": "Given: Sales = R900 000; Cost of Sales = R600 000; Rent Income = R50 000; Salaries = R180 000; Insurance = R20 000; Income Tax Rate = 28%",
      "steps": [
        {
          "prompt": "What is the Gross Profit?",
          "options": ["R300 000", "R250 000", "R400 000"],
          "answer": "R300 000"
        },
        {
          "prompt": "What is the Total Operating Expenses?",
          "options": ["R200 000", "R180 000", "R250 000"],
          "answer": "R200 000"
        }
      ]
    }
  ]
}
```

#### **Examples:**

**Import with Duplication Checking (Default):**
```bash
curl -X POST http://localhost:8000/api/accounting-questions/import \
  -H "Content-Type: application/json" \
  -d @questions.json
```

**Import with Overwrite:**
```bash
curl -X POST "http://localhost:8000/api/accounting-questions/import?overwrite=true" \
  -H "Content-Type: application/json" \
  -d @questions.json
```

#### **Response:**
```json
{
  "message": "Import completed",
  "imported": 3,
  "updated": 0,
  "skipped": 0,
  "errors": [],
  "skipped_details": [],
  "updated_details": []
}
```

---

## 📋 **List All Questions**

### **GET** `/api/accounting-questions`

Get all questions grouped by topic and level.

#### **Example:**
```bash
curl http://localhost:8000/api/accounting-questions
```

#### **Response:**
```json
{
  "data": {
    "Statement of Comprehensive Income": {
      "Level 1: Basics": [
        {
          "id": "sci_q1",
          "type": "tap-to-select",
          "prompt": "Is Rent Income an income or an expense?",
          "options": ["Income", "Expense"],
          "answer": "Income"
        }
      ],
      "Level 2: Core Practice": [
        {
          "id": "sci_l2_q1",
          "type": "step-flow",
          "prompt": "Sales for the year were R500 000. The mark-up is 25% on cost. What is the Cost of Sales?",
          "options": ["R375 000", "R400 000", "R450 000"],
          "answer": "R400 000",
          "explanation": "Cost = Sales / 1.25 = R400 000"
        }
      ]
    }
  }
}
```

---

## 🏷️ **Get Questions by Topic**

### **GET** `/api/accounting-questions/topic/{topic}`

Get all questions for a specific topic.

#### **Example:**
```bash
curl "http://localhost:8000/api/accounting-questions/topic/Statement%20of%20Comprehensive%20Income"
```

#### **Response:**
```json
{
  "topic": "Statement of Comprehensive Income",
  "data": [
    {
      "id": "sci_q1",
      "type": "tap-to-select",
      "prompt": "Is Rent Income an income or an expense?",
      "options": ["Income", "Expense"],
      "answer": "Income"
    },
    {
      "id": "sci_l2_q1",
      "type": "step-flow",
      "prompt": "Sales for the year were R500 000. The mark-up is 25% on cost. What is the Cost of Sales?",
      "options": ["R375 000", "R400 000", "R450 000"],
      "answer": "R400 000",
      "explanation": "Cost = Sales / 1.25 = R400 000"
    }
  ]
}
```

---

## 📊 **Get Questions by Level**

### **GET** `/api/accounting-questions/level/{level}`

Get all questions for a specific level.

#### **Example:**
```bash
curl "http://localhost:8000/api/accounting-questions/level/Level%201:%20Basics"
```

#### **Response:**
```json
{
  "level": "Level 1: Basics",
  "data": [
    {
      "id": "sci_q1",
      "type": "tap-to-select",
      "prompt": "Is Rent Income an income or an expense?",
      "options": ["Income", "Expense"],
      "answer": "Income"
    },
    {
      "id": "sci_q2",
      "type": "categorise",
      "prompt": "Drag each item to the correct category.",
      "categories": ["Income", "Operating Expense"],
      "items": {
        "Rent Income": "Income",
        "Packing Material": "Operating Expense",
        "Service Fees": "Income",
        "Audit Fees": "Operating Expense"
      }
    }
  ]
}
```

---

## 🎯 **Get Questions by Topic and Level**

### **GET** `/api/accounting-questions/topic/{topic}/level/{level}`

Get questions for a specific topic and level combination.

#### **Example:**
```bash
curl "http://localhost:8000/api/accounting-questions/topic/Statement%20of%20Comprehensive%20Income/level/Level%201:%20Basics"
```

#### **Response:**
```json
{
  "topic": "Statement of Comprehensive Income",
  "level": "Level 1: Basics",
  "data": [
    {
      "id": "sci_q1",
      "type": "tap-to-select",
      "prompt": "Is Rent Income an income or an expense?",
      "options": ["Income", "Expense"],
      "answer": "Income"
    },
    {
      "id": "sci_q2",
      "type": "categorise",
      "prompt": "Drag each item to the correct category.",
      "categories": ["Income", "Operating Expense"],
      "items": {
        "Rent Income": "Income",
        "Packing Material": "Operating Expense",
        "Service Fees": "Income",
        "Audit Fees": "Operating Expense"
      }
    }
  ]
}
```

---

## 🔤 **Get Questions by Type**

### **GET** `/api/accounting-questions/type/{type}`

Get all questions of a specific type.

#### **Available Types:**
- `tap-to-select`
- `categorise`
- `true-false`
- `drag-to-sort`
- `matching`
- `step-flow`
- `multi-step`

#### **Example:**
```bash
curl "http://localhost:8000/api/accounting-questions/type/tap-to-select"
```

#### **Response:**
```json
{
  "type": "tap-to-select",
  "data": [
    {
      "id": "sci_q1",
      "type": "tap-to-select",
      "prompt": "Is Rent Income an income or an expense?",
      "options": ["Income", "Expense"],
      "answer": "Income"
    },
    {
      "id": "sci_l2_q6",
      "type": "tap-to-select",
      "prompt": "Which of these is NOT part of Operating Expenses?",
      "options": ["Salaries", "Rent Income", "Stationery"],
      "answer": "Rent Income"
    }
  ]
}
```

---

## 🔍 **Get Single Question**

### **GET** `/api/accounting-questions/{questionId}`

Get a specific question by its ID.

#### **Example:**
```bash
curl http://localhost:8000/api/accounting-questions/sci_q1
```

#### **Response:**
```json
{
  "data": {
    "id": "sci_q1",
    "type": "tap-to-select",
    "prompt": "Is Rent Income an income or an expense?",
    "options": ["Income", "Expense"],
    "answer": "Income"
  }
}
```

---

## ✏️ **Update Question**

### **PUT** `/api/accounting-questions/{questionId}`

Update a specific question.

#### **Request Body:**
```json
{
  "prompt": "Updated question prompt",
  "options": ["Updated Option 1", "Updated Option 2"],
  "answer": "Updated Option 1"
}
```

#### **Example:**
```bash
curl -X PUT http://localhost:8000/api/accounting-questions/sci_q1 \
  -H "Content-Type: application/json" \
  -d '{
    "prompt": "Updated: Is Rent Income an income or an expense?",
    "options": ["Income", "Expense", "Neither"],
    "answer": "Income"
  }'
```

#### **Response:**
```json
{
  "message": "Question updated successfully"
}
```

---

## 🗑️ **Delete Question**

### **DELETE** `/api/accounting-questions/{questionId}`

Soft delete a question (sets active to false).

#### **Example:**
```bash
curl -X DELETE http://localhost:8000/api/accounting-questions/sci_q1
```

#### **Response:**
```json
{
  "message": "Question deleted successfully"
}
```

---

## 📚 **Get Available Topics**

### **GET** `/api/accounting-questions/topics`

Get all available topics.

#### **Example:**
```bash
curl http://localhost:8000/api/accounting-questions/topics
```

#### **Response:**
```json
{
  "data": [
    "Statement of Comprehensive Income",
    "Balance Sheet",
    "Cash Flow Statement"
  ]
}
```

---

## 📈 **Get Available Levels**

### **GET** `/api/accounting-questions/levels`

Get all available levels.

#### **Example:**
```bash
curl http://localhost:8000/api/accounting-questions/levels
```

#### **Response:**
```json
{
  "data": [
    "Level 1: Basics",
    "Level 2: Core Practice",
    "Level 3: Advanced"
  ]
}
```

---

## 🎲 **Get Available Question Types**

### **GET** `/api/accounting-questions/types`

Get all available question types.

#### **Example:**
```bash
curl http://localhost:8000/api/accounting-questions/types
```

#### **Response:**
```json
{
  "data": [
    "tap-to-select",
    "categorise",
    "true-false",
    "drag-to-sort",
    "matching",
    "step-flow",
    "multi-step"
  ]
}
```

---

## 📝 **Question Types Reference**

### **1. tap-to-select**
```json
{
  "id": "q1",
  "type": "tap-to-select",
  "prompt": "What is the correct answer?",
  "options": ["Option A", "Option B", "Option C"],
  "answer": "Option A"
}
```

### **2. categorise**
```json
{
  "id": "q2",
  "type": "categorise",
  "prompt": "Drag each item to the correct category.",
  "categories": ["Category A", "Category B"],
  "items": {
    "Item 1": "Category A",
    "Item 2": "Category B"
  }
}
```

### **3. true-false**
```json
{
  "id": "q3",
  "type": "true-false",
  "prompt": "True or False: This statement is correct.",
  "answer": "True",
  "explanation": "This is why the answer is true."
}
```

### **4. drag-to-sort**
```json
{
  "id": "q4",
  "type": "drag-to-sort",
  "prompt": "Arrange these in the correct order.",
  "items": ["Item 1", "Item 2", "Item 3"],
  "correct_order": ["Item 1", "Item 2", "Item 3"]
}
```

### **5. matching**
```json
{
  "id": "q5",
  "type": "matching",
  "prompt": "Match each item to the correct category.",
  "pairs": {
    "Item A": "Category 1",
    "Item B": "Category 2"
  }
}
```

### **6. step-flow**
```json
{
  "id": "q6",
  "type": "step-flow",
  "prompt": "What is the result of this calculation?",
  "options": ["Result A", "Result B", "Result C"],
  "answer": "Result A",
  "explanation": "Step-by-step explanation of the calculation."
}
```

### **7. multi-step**
```json
{
  "id": "q7",
  "type": "multi-step",
  "context": "Given: Sales = R900 000; Cost of Sales = R600 000",
  "steps": [
    {
      "prompt": "What is the Gross Profit?",
      "options": ["R300 000", "R250 000", "R400 000"],
      "answer": "R300 000"
    },
    {
      "prompt": "What is the next step?",
      "options": ["Option A", "Option B", "Option C"],
      "answer": "Option A"
    }
  ]
}
```

---

## 🚨 **Error Responses**

### **400 Bad Request**
```json
{
  "error": "Invalid JSON format",
  "details": "Syntax error"
}
```

### **404 Not Found**
```json
{
  "error": "Question not found"
}
```

### **500 Internal Server Error**
```json
{
  "error": "Import failed",
  "details": "Database connection error"
}
```

---

## 🔧 **Testing with cURL**

### **Complete Import Example:**
```bash
# Create a test file
cat > test_questions.json << 'EOF'
{
  "topic": "Test Topic",
  "level": "Test Level",
  "questions": [
    {
      "id": "test_q1",
      "type": "tap-to-select",
      "prompt": "Test question?",
      "options": ["Yes", "No"],
      "answer": "Yes"
    }
  ]
}
EOF

# Import the questions
curl -X POST http://localhost:8000/api/accounting-questions/import \
  -H "Content-Type: application/json" \
  -d @test_questions.json

# Get all questions
curl http://localhost:8000/api/accounting-questions

# Get specific question
curl http://localhost:8000/api/accounting-questions/test_q1

# Update question
curl -X PUT http://localhost:8000/api/accounting-questions/test_q1 \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Updated test question?"}'

# Delete question
curl -X DELETE http://localhost:8000/api/accounting-questions/test_q1
```

---

## 📊 **Response Status Codes**

- **200 OK** - Request successful
- **201 Created** - Resource created successfully
- **400 Bad Request** - Invalid request data
- **404 Not Found** - Resource not found
- **500 Internal Server Error** - Server error

---

## 🔄 **Import Behavior**

### **Default Import (No Overwrite):**
- Skips existing questions with same ID
- Reports skipped questions in response
- Only imports new questions

### **Import with Overwrite:**
- Updates existing questions with same ID
- Reports updated questions in response
- Imports new questions as normal

### **Import Response Fields:**
- `imported` - Number of new questions imported
- `updated` - Number of existing questions updated
- `skipped` - Number of questions skipped (duplicates)
- `errors` - Array of error messages
- `skipped_details` - Detailed list of skipped questions
- `updated_details` - Detailed list of updated questions 