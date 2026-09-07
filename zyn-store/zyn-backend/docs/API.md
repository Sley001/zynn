# ZYN Store API

Local base URL: `http://127.0.0.1:8000/api`

All requests should send `Accept: application/json`.

## Public endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/health` | Verify Laravel and PostgreSQL |
| GET | `/products` | Active catalog products |
| GET | `/products/{slug}` | One product |
| POST | `/orders` | Create an order |

### Create order

```json
{
  "customer_name": "Customer Name",
  "phone": "012345678",
  "province": "Phnom Penh",
  "district": "Tuol Kouk",
  "address_note": "Street and landmark",
  "payment_method": "khqr",
  "telegram_username": "customer_username",
  "age_confirmed": true,
  "items": [
    { "product_id": 1, "quantity": 2 }
  ]
}
```

Accepted payment methods: `khqr`, `payway`, `cod`.

The backend ignores any price sent by the browser. It locks each product row,
checks availability and stock, calculates totals from PostgreSQL, stores a price
snapshot in `order_items`, reduces stock, and creates the payment record inside
one database transaction.

## Admin authentication

### Login

`POST /api/admin/login`

```json
{
  "email": "admin@example.com",
  "password": "your-password"
}
```

Send the returned token on protected requests:

```http
Authorization: Bearer YOUR_TOKEN
```

## Protected admin endpoints

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/admin/logout` | Revoke current token |
| GET | `/admin/products` | List all products |
| POST | `/admin/products` | Add product and optional image |
| GET | `/admin/products/{slug}` | Product details |
| PUT/PATCH | `/admin/products/{slug}` | Update product |
| DELETE | `/admin/products/{slug}` | Delete product |
| GET | `/admin/orders` | Filter/search orders |
| GET | `/admin/orders/{order_number}` | Order details |
| PATCH | `/admin/orders/{order_number}` | Update order/payment status |

Product images use `multipart/form-data`. Product prices are written as integer
cents: `$4.00` is `400`.
