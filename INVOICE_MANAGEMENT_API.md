# Invoice Management API

This API provides CRUD operations for Customers, Products, and Invoices (with invoice items).

Base URL: `/api/invoice-management`

---

## Customer Endpoints

### List Customers
- **GET** `/customers`

### Get Customer
- **GET** `/customers/{id}`

### Create Customer
- **POST** `/customers`
- **Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "1234567890",
  "address": "123 Main St"
}
```

### Update Customer
- **PUT** `/customers/{id}`
- **Body:** (same as create)

### Delete Customer
- **DELETE** `/customers/{id}`

---

## Product Endpoints

### List Products
- **GET** `/products`

### Get Product
- **GET** `/products/{id}`

### Create Product
- **POST** `/products`
- **Body:**
```json
{
  "name": "Widget",
  "description": "A useful widget",
  "price": 19.99
}
```

### Update Product
- **PUT** `/products/{id}`
- **Body:** (same as create)

### Delete Product
- **DELETE** `/products/{id}`

---

## Invoice Endpoints

### List Invoices
- **GET** `/invoices`

### Get Invoice
- **GET** `/invoices/{id}`

### Create Invoice
- **POST** `/invoices`
- **Body:**
```json
{
  "invoice_number": "INV-001",
  "date": "2024-06-01T12:00:00",
  "customer_id": 1,
  "total": 100.00,
  "items": [
    { "product_id": 1, "quantity": 2, "price": 19.99 },
    { "product_id": 2, "quantity": 1, "price": 59.99 }
  ]
}
```

### Update Invoice
- **PUT** `/invoices/{id}`
- **Body:** (same as create; replaces all items)

### Delete Invoice
- **DELETE** `/invoices/{id}`

---

## Notes
- All endpoints return JSON.
- For `Invoice` creation and update, the `items` array specifies products, quantities, and prices.
- Standard HTTP status codes are used for success and errors. 