# AI Accounting Questions Generation

This document describes the AI-powered accounting question generation system that uses OpenAI's GPT models to create engaging, educational accounting questions.

## Overview

The AI Accounting Question Generator automatically creates accounting questions in various formats and difficulty levels, saving them directly to the `accounting_question` table. The system supports multiple question types and ensures educational quality through carefully crafted prompts.

## Features

- **Multiple Question Types**: Supports 7 different question formats
- **Difficulty Levels**: Level 1 (Basics), Level 2 (Core Practice), Level 3 (Advanced), Level 4 (Expert)
- **Comprehensive Topics**: Covers all major accounting concepts
- **Automatic Saving**: Questions are automatically saved to the database
- **Token Usage Tracking**: Monitors OpenAI API usage
- **Error Handling**: Robust error handling and logging
- **Rate Limiting**: Built-in delays to avoid API rate limits
- **Duplication Avoidance**: Automatically fetches existing questions and instructs AI to avoid similar content
- **Level Filtering**: Filters existing questions by level to reduce payload and focus on similar difficulty
- **Payload Optimization**: Concise formatting of existing questions to minimize API token usage

## Question Types Supported

### 1. tap-to-select
Multiple choice questions with a single correct answer.

**Example:**
```json
{
  "id": "ai_financial_statements_income_statement_tap_to_select_1234567_abcd",
  "type": "tap-to-select",
  "prompt": "Is Rent Revenue an income or an expense? 💰",
  "options": ["Income", "Expense"],
  "answer": "Income"
}
```

### 2. categorise
Drag and drop items into categories.

**Example:**
```json
{
  "id": "ai_financial_statements_income_statement_categorise_1234567_efgh",
  "type": "categorise",
  "prompt": "Drag each item to the correct category. 📊",
  "categories": ["Income", "Operating Expense"],
  "items": {
    "Rent Income": "Income",
    "Packing Material": "Operating Expense",
    "Service Fees": "Income",
    "Audit Fees": "Operating Expense"
  }
}
```

### 3. true-false
True or false questions with explanations.

**Example:**
```json
{
  "id": "ai_financial_statements_income_statement_true_false_1234567_ijkl",
  "type": "true-false",
  "prompt": "Depreciation is an income item. 🤔",
  "answer": "False",
  "explanation": "Depreciation is an operating expense, not income."
}
```

### 4. drag-to-sort
Arrange items in the correct order.

**Example:**
```json
{
  "id": "ai_financial_statements_income_statement_drag_to_sort_1234567_mnop",
  "type": "drag-to-sort",
  "prompt": "Put these in the correct order as they appear on the income statement. 📋",
  "items": [
    "Sales",
    "Cost of Sales",
    "Gross Profit",
    "Operating Expenses",
    "Net Profit"
  ],
  "correct_order": [
    "Sales",
    "Cost of Sales",
    "Gross Profit",
    "Operating Expenses",
    "Net Profit"
  ]
}
```

### 5. matching
Match items to their correct categories.

**Example:**
```json
{
  "id": "ai_financial_statements_income_statement_matching_1234567_qrst",
  "type": "matching",
  "prompt": "Match each item to the correct section. 🔗",
  "pairs": {
    "Sales": "Income",
    "Bad Debts": "Operating Expense",
    "Directors' Fees": "Operating Expense",
    "Interest Income": "Other Income"
  }
}
```

### 6. step-flow
Single calculation or problem-solving questions.

**Example:**
```json
{
  "id": "ai_financial_statements_income_statement_step_flow_1234567_uvwx",
  "type": "step-flow",
  "prompt": "Sales for the year were R500 000. The mark-up is 25% on cost. What is the Cost of Sales? 🧮",
  "options": [
    "R375 000",
    "R400 000",
    "R450 000"
  ],
  "answer": "R400 000",
  "explanation": "Cost = Sales / 1.25 = R400 000"
}
```

### 7. multi-step
Complex problems with multiple steps.

**Example:**
```json
{
  "id": "ai_financial_statements_income_statement_multi_step_1234567_yzab",
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
```

## API Endpoints

### Generate Single Question

**POST** `/api/accounting-questions/generate/single`

**Request Body:**
```json
{
  "topic": "Financial Statements - Income Statement",
  "level": "Level 1: Basics",
  "questionType": "tap-to-select",
  "mainTopic": "Financial Statements"
}
```

**Response:**
```json
{
  "message": "Question generated successfully",
  "data": {
    "id": "ai_financial_statements_income_statement_tap_to_select_1234567_abcd",
    "type": "tap-to-select",
    "prompt": "Is Rent Revenue an income or an expense? 💰",
    "options": ["Income", "Expense"],
    "answer": "Income"
  },
  "token_usage": {
    "prompt_tokens": 150,
    "completion_tokens": 50,
    "total_tokens": 200
  }
}
```

### Generate Multiple Questions

**POST** `/api/accounting-questions/generate/multiple`

**Request Body:**
```json
{
  "topic": "Financial Statements - Income Statement",
  "level": "Level 1: Basics",
  "mainTopic": "Financial Statements",
  "count": 5,
  "questionTypes": ["tap-to-select", "categorise", "true-false"] // optional
}
```

**Response:**
```json
{
  "message": "Questions generation completed",
  "data": {
    "total_requested": 5,
    "successful": 4,
    "failed": 1,
    "results": [
      {
        "success": true,
        "question": { /* question data */ },
        "token_usage": { /* token usage data */ }
      }
      // ... more results
    ]
  }
}
```

### Get Available Question Types

**GET** `/api/accounting-questions/generate/types`

**Response:**
```json
{
  "data": {
    "tap-to-select": "Multiple choice with single correct answer",
    "categorise": "Drag and drop items into categories",
    "true-false": "True or false questions with explanations",
    "drag-to-sort": "Arrange items in correct order",
    "matching": "Match items to their correct categories",
    "step-flow": "Single calculation or problem-solving question",
    "multi-step": "Complex problem with multiple steps"
  }
}
```

### Get Available Topics and Levels

**GET** `/api/accounting-questions/generate/metadata`

**Response:**
```json
{
  "data": {
    "topics": [
      "Financial Statements - Income Statement",
      "Financial Statements - Balance Sheet",
      "Financial Statements - Cash Flow Statement",
      "Accounting Equation",
      "Double Entry Bookkeeping",
      "Trial Balance",
      "Adjustments",
      "Closing Entries",
      "Inventory Valuation",
      "Depreciation",
      "Bad Debts",
      "Bank Reconciliation",
      "Petty Cash",
      "Payroll Accounting",
      "Cost Accounting",
      "Budgeting",
      "Financial Ratios",
      "Audit and Internal Control"
    ],
    "levels": {
      "Level 1: Basics": "Fundamental concepts and definitions (Grades 8-9)",
      "Level 2: Core Practice": "Application and calculations (Grades 10-11)",
      "Level 3: Advanced": "Complex scenarios and analysis (Grade 12)",
      "Level 4: Expert": "University preparation and advanced analysis (Grade 12+)"
    }
  }
}
```

## Console Command

### Generate Questions via Command Line

```bash
# Generate a single question
php bin/console app:generate-accounting-questions "Financial Statements - Income Statement" "Level 1: Basics" "Financial Statements" --type="tap-to-select"

# Generate multiple questions
php bin/console app:generate-accounting-questions "Financial Statements - Income Statement" "Level 1: Basics" "Financial Statements" --count=5 --type="tap-to-select"

# Generate with different main topic
php bin/console app:generate-accounting-questions "Financial Statements - Income Statement" "Level 1: Basics" "Accounting Fundamentals" --count=3

# Dry run (show what would be generated without saving)
php bin/console app:generate-accounting-questions "Financial Statements - Income Statement" "Level 1: Basics" "Financial Statements" --dry-run
```

## Available Topics

1. **Financial Statements - Income Statement**
2. **Financial Statements - Balance Sheet**
3. **Financial Statements - Cash Flow Statement**
4. **Accounting Equation**
5. **Double Entry Bookkeeping**
6. **Trial Balance**
7. **Adjustments**
8. **Closing Entries**
9. **Inventory Valuation**
10. **Depreciation**
11. **Bad Debts**
12. **Bank Reconciliation**
13. **Petty Cash**
14. **Payroll Accounting**
15. **Cost Accounting**
16. **Budgeting**
17. **Financial Ratios**
18. **Audit and Internal Control**

## Available Levels

- **Level 1: Basics** - Fundamental concepts and definitions (Grades 8-9, ages 13-15)
- **Level 2: Core Practice** - Application and calculations (Grades 10-11, ages 15-17)
- **Level 3: Advanced** - Complex scenarios and analysis (Grade 12, ages 17-18)
- **Level 4: Expert** - University preparation and advanced analysis (Grade 12+, ages 17-18+)

## Technical Implementation

### Services

- **AccountingQuestionGeneratorService**: Main service for generating questions
- **OpenAIService**: Handles communication with OpenAI API
- **AccountingQuestionService**: Manages existing questions

### Key Features

1. **Prompt Engineering**: Carefully crafted prompts ensure educational quality
2. **JSON Parsing**: Robust parsing of AI responses with fallback mechanisms
3. **Unique ID Generation**: Automatic generation of unique question IDs
4. **Database Integration**: Seamless saving to the accounting_question table
5. **Error Handling**: Comprehensive error handling and logging
6. **Rate Limiting**: Built-in delays to respect API rate limits
7. **Duplication Avoidance**: Fetches existing questions for the topic and type, then instructs AI to create significantly different content
8. **Level Filtering**: Filters existing questions by level to reduce payload size and focus on similar difficulty questions
9. **Payload Optimization**: Uses concise formatting and limits to 5 existing questions to minimize API token usage

### File Structure

```
src/
├── Service/
│   ├── AccountingQuestionGeneratorService.php
│   ├── OpenAIService.php
│   └── AccountingQuestionService.php
├── Controller/
│   └── AccountingQuestionGeneratorController.php
├── Command/
│   └── GenerateAccountingQuestionsCommand.php
└── Entity/
    └── AccountingQuestion.php
```

## Usage Examples

### Example 1: Generate a Basic Income Statement Question

```bash
curl -X POST http://localhost:8000/api/accounting-questions/generate/single \
  -H "Content-Type: application/json" \
  -d '{
    "topic": "Financial Statements - Income Statement",
    "level": "Level 1: Basics",
    "questionType": "tap-to-select",
    "mainTopic": "Financial Statements"
  }'
```

### Example 2: Generate Multiple Questions for Different Types

```bash
curl -X POST http://localhost:8000/api/accounting-questions/generate/multiple \
  -H "Content-Type: application/json" \
  -d '{
    "topic": "Financial Statements - Income Statement",
    "level": "Level 2: Core Practice",
    "mainTopic": "Financial Statements",
    "count": 5,
    "questionTypes": ["tap-to-select", "categorise", "true-false", "drag-to-sort", "matching"]
  }'
```

### Example 3: Generate Advanced Balance Sheet Questions

```bash
curl -X POST http://localhost:8000/api/accounting-questions/generate/multiple \
  -H "Content-Type: application/json" \
  -d '{
    "topic": "Financial Statements - Balance Sheet",
    "level": "Level 3: Advanced",
    "mainTopic": "Financial Statements",
    "count": 3,
    "questionTypes": ["step-flow", "multi-step"]
  }'
```

## Error Handling

The system handles various error scenarios:

1. **API Errors**: Network issues, rate limiting, invalid responses
2. **Parsing Errors**: Invalid JSON responses from AI
3. **Validation Errors**: Missing required fields, invalid question types
4. **Database Errors**: Connection issues, constraint violations

All errors are logged and returned with appropriate HTTP status codes.

## Monitoring and Logging

- **Token Usage**: Track OpenAI API usage for cost monitoring
- **Success/Failure Rates**: Monitor generation success rates
- **Response Times**: Track API response times
- **Error Logging**: Comprehensive error logging for debugging

## Best Practices

1. **Start Small**: Begin with single questions to test the system
2. **Monitor Usage**: Keep track of token usage to manage costs
3. **Validate Output**: Review generated questions for educational quality
4. **Use Appropriate Levels**: Match question complexity to student level
5. **Diversify Types**: Use different question types for variety
6. **Test Thoroughly**: Test with various topics and levels before production use

## Future Enhancements

1. **Question Quality Scoring**: Implement quality assessment for generated questions
2. **Bulk Generation**: Support for generating large question banks
3. **Custom Prompts**: Allow custom prompt templates
4. **Question Review Workflow**: Add approval process for generated questions
5. **Analytics Dashboard**: Web interface for monitoring and management
6. **Question Templates**: Pre-defined templates for common question patterns