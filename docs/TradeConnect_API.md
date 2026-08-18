# Trade Connect API Documentation

**Version:** v1  
**Project stage:** Proof of Concept / Figma-parity backend  
**Base URL (local):** `http://127.0.0.1:8000/api/v1`  
**Alternate local host:** `http://localhost:8000/api/v1`  
**Default format:** JSON  
**File uploads:** `multipart/form-data`  
**Authentication:** JWT Bearer token  
**Last updated:** 18 August 2026

Trade Connect is a farm-produce marketplace API. Buyers browse verified-farmer listings, create multi-item orders, pay through the Paystack checkout flow, view order history, and raise disputes. Administrators manage farmers, catalog data, listings, orders, buyers, disputes, notifications, dashboard activity, and farmer payouts.

This document describes the current PoC API contract. It preserves the original v1 compatibility fields where the backend intentionally supports older clients, while documenting the richer Figma-facing fields and workflows that should be used by new frontend code.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication and Roles](#authentication-and-roles)
3. [Response Conventions](#response-conventions)
4. [Error Handling](#error-handling)
5. [Enums and Important Domain Rules](#enums-and-important-domain-rules)
6. [Public Marketplace Endpoints](#public-marketplace-endpoints)
7. [Authentication and Profile Endpoints](#authentication-and-profile-endpoints)
8. [Buyer Order and Payment Endpoints](#buyer-order-and-payment-endpoints)
9. [Buyer Dispute Endpoints](#buyer-dispute-endpoints)
10. [Admin Dashboard, Activity and Notification Endpoints](#admin-dashboard-activity-and-notification-endpoints)
11. [Admin Category and Produce Endpoints](#admin-category-and-produce-endpoints)
12. [Admin Farmer Endpoints](#admin-farmer-endpoints)
13. [Admin Listing Endpoints](#admin-listing-endpoints)
14. [Admin Order and Farmer Payout Endpoints](#admin-order-and-farmer-payout-endpoints)
15. [Admin User and Buyer Endpoints](#admin-user-and-buyer-endpoints)
16. [Admin Dispute Endpoints](#admin-dispute-endpoints)
17. [Media Storage and Image URLs](#media-storage-and-image-urls)
18. [Typical End-to-End Workflows](#typical-end-to-end-workflows)
19. [Frontend Integration Contract](#frontend-integration-contract)
20. [Intentional PoC Non-Endpoints](#intentional-poc-non-endpoints)
21. [Complete Endpoint Index](#complete-endpoint-index)

---

# Getting Started

Start Laravel from the backend project root:

```bash
php artisan serve
```

The default local API base URL is:

```text
http://127.0.0.1:8000/api/v1
```

All versioned API routes are prefixed with:

```text
/api/v1
```

Public media uses Laravel's `public` filesystem disk. Make sure the storage symlink exists:

```bash
php artisan storage:link
```

The physical files are stored under:

```text
storage/app/public/
```

and are served through:

```text
/storage/...
```

For local frontend development, the backend CORS configuration allows common localhost/127.0.0.1 ports and private LAN addresses used by local devices.

---

# Authentication and Roles

Protected routes require a JWT:

```http
Authorization: Bearer {access_token}
```

The API currently supports these roles:

| Role | Meaning | Main access |
|---|---|---|
| `admin` | Administrator | `/api/v1/admin/*`, plus shared authenticated profile routes |
| `user` | Buyer | Marketplace + buyer orders/payments/disputes + profile routes |

Buyer-specific routes are protected by the buyer-role middleware. An authenticated admin cannot act as a buyer through `/orders`, buyer payment routes, or buyer dispute routes.

## PoC registration behavior

Public registration currently accepts both `admin` and `user` roles intentionally for the PoC. This is a known PoC decision and is not a recommendation for production deployment.

## JWT lifetime

The authentication response contains `expires_in`, which should be treated as the authoritative lifetime for the issued token. The supplied project environment example configures a long PoC token lifetime. Frontends should not hard-code a token duration.

---

# Response Conventions

## Single resource

```json
{
  "data": {
    "id": 1
  }
}
```

## Non-paginated collection

```json
{
  "data": [
    { "id": 1 },
    { "id": 2 }
  ]
}
```

## Paginated collection

Paginated list endpoints keep records in `data` and add Laravel pagination information in `links` and `meta`.

```json
{
  "data": [],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 4,
    "per_page": 20,
    "to": 20,
    "total": 73
  }
}
```

The frontend should use:

```text
response.data       -> current page records
response.meta       -> pagination state
response.links      -> navigation URLs
```

Do not assume `data` contains every database record when the endpoint is paginated.

## Message-only success

```json
{
  "message": "Logged out successfully."
}
```

## Authentication response

```json
{
  "access_token": "eyJ...",
  "token_type": "bearer",
  "expires_in": 5184000,
  "user": {
    "id": 1,
    "account_code": "BYR-000001",
    "name": "Jane Doe",
    "email": "jane@example.com",
    "role": "user",
    "status": "active"
  }
}
```

`expires_in` depends on JWT configuration and should not be assumed to always equal the example value.

---

# Error Handling

API errors are returned as JSON.

| Status | Meaning | Typical case |
|---|---|---|
| `400` | Bad request | Invalid request shape in exceptional cases |
| `401` | Unauthenticated | Missing, invalid, expired or blacklisted JWT |
| `403` | Forbidden | Wrong role or inactive account |
| `404` | Not found / not owned | Resource missing or intentionally hidden from caller |
| `409` | Conflict | Payment verification reference/amount/currency conflict |
| `422` | Validation or workflow failure | Invalid form fields, state transition, stock rule, etc. |
| `502` | Provider failure | Paystack temporarily unavailable or returned invalid provider data |

## Standard validation error

```json
{
  "message": "Validation failed.",
  "errors": {
    "field": [
      "Validation message."
    ]
  }
}
```

## Authentication examples

```json
{
  "message": "Unauthenticated."
}
```

```json
{
  "message": "Invalid credentials."
}
```

An inactive account may receive:

```json
{
  "message": "Account is inactive."
}
```

## Ownership hiding

Buyer-owned resources generally return `404` rather than leaking that another user's resource exists. Example:

```json
{
  "message": "Order not found."
}
```

---

# Enums and Important Domain Rules

## Status values

| Field | Allowed values |
|---|---|
| User role | `admin`, `user` |
| User status | `active`, `inactive` |
| Farmer status | `active`, `inactive` |
| Farmer verification | `pending`, `verified`, `rejected` |
| Legacy listing status | `active`, `inactive` |
| Listing publication status | `pending`, `live`, `inactive` |
| Order fulfillment status | `new`, `in_transit`, `delivered`, `cancelled` |
| Payment status | `pending`, `paid`, `failed`, `refunded` |
| Canonical dispute status | `open`, `resolved`, `closed` |
| Enhanced dispute workflow status | `under_review`, `resolved`, `closed` |
| Delivery method | `standard`, `pickup`, `express` |
| Listing label | `fresh`, `organic`, `seasonal` |

## Order state graph

The backend fulfillment graph is intentionally:

```text
new -> in_transit -> delivered
 |
 +-> cancelled
```

Rules:

- `delivered` and `cancelled` are terminal.
- A paid order cannot be cancelled until a refund workflow exists.
- Payment status is separate from fulfillment status.
- Payment status does not otherwise gate the current PoC `new -> in_transit -> delivered` progression.
- The frontend must not send Figma-only presentation states such as `confirmed` or `processing`.

## Delivery pricing

| Method | Fee | Deliver-by behavior |
|---|---:|---|
| `standard` | NGN 1,500.00 | Conservative upper bound: 3 business days |
| `pickup` | NGN 0.00 | 24 hours after order placement |
| `express` | NGN 0.00 | Retained for compatibility; no approved express fee yet |

The server calculates delivery fees. Clients must not send `delivery_fee`, `subtotal`, or `total` as authoritative values.

Legacy single-item order payloads that omit delivery fields retain their legacy zero-delivery-fee behavior.

## Listing publication eligibility

A listing is publicly visible only when:

- `publication_status = live`;
- legacy `status = active`;
- the owning farmer has `status = active`; and
- the owning farmer has `verification_status = verified`.

## Dispute compatibility

The canonical v1 `status` remains backward-compatible:

```text
open
resolved
closed
```

The richer UI should use the additive field:

```text
workflow_status = under_review | resolved | closed
```

For an under-review dispute:

```json
{
  "status": "open",
  "workflow_status": "under_review"
}
```

---

# Public Marketplace Endpoints

No authentication is required for this section.

## Health Check

```http
GET /api/v1/health
```

**Response `200`**

```json
{
  "status": "ok",
  "service": "Trade Connect"
}
```

---

## Public Categories

```http
GET /api/v1/categories
```

Returns categories ordered by name, including lightweight nested produce records.

**Response `200`**

```json
{
  "data": [
    {
      "id": 1,
      "name": "Vegetables",
      "produce": [
        {
          "id": 7,
          "name": "Tomato"
        }
      ]
    }
  ]
}
```

---

## Marketplace Summary

```http
GET /api/v1/marketplace/summary
```

Counts only marketplace-visible listings belonging to active, verified farmers.

**Response `200`**

```json
{
  "data": {
    "listings": 18,
    "farmers": 7,
    "lgas": 5
  }
}
```

---

## Browse Public Listings

```http
GET /api/v1/listings
```

### Query parameters

| Parameter | Type | Required | Rules / meaning |
|---|---|---:|---|
| `search` | string | No | Search listing/produce/category/farmer/location text |
| `category_id` | integer | No | Existing category |
| `farmer_id` | integer | No | Existing farmer |
| `state` | string | No | Exact farmer state filter |
| `lga` | string | No | Exact farmer LGA filter |
| `label` | string | No | `fresh`, `organic`, `seasonal` |
| `availability` | string | No | `available`, `upcoming`, `out_of_stock` |
| `min_price` | number | No | Minimum selling price, >= 0 |
| `max_price` | number | No | Maximum selling price, >= 0; cannot be below `min_price` |
| `sort` | string | No | `price`, `stock`, `created_at`, `produce`, `farmer`, `category` |
| `order` | string | No | `asc` or `desc` |
| `page` | integer | No | >= 1 |
| `per_page` | integer | No | 1-100; default 20 |

Examples:

```http
GET /api/v1/listings?page=1&per_page=12
GET /api/v1/listings?search=tomato
GET /api/v1/listings?category_id=1&label=fresh
GET /api/v1/listings?state=Kaduna&availability=available
GET /api/v1/listings?min_price=1000&max_price=10000&sort=price&order=asc
```

### Listing response shape

```json
{
  "id": 15,
  "farmer_id": 4,
  "produce_id": 7,
  "price": "5000.00",
  "original_price": "5500.00",
  "discount_percent": "9.09",
  "discount_amount": "500.00",
  "unit": "kg",
  "stock": 100,
  "minimum_order_quantity": 1,
  "description": "Fresh tomatoes",
  "label": "fresh",
  "grade": "A",
  "available_from": "2026-08-18",
  "is_available": true,
  "status": "active",
  "publication_status": "live",
  "published_at": "2026-08-18T09:00:00.000000Z",
  "primary_image_url": "http://127.0.0.1:8000/storage/listing-images/15/example.jpg",
  "images": [
    {
      "id": 20,
      "url": "http://127.0.0.1:8000/storage/listing-images/15/example.jpg",
      "original_name": "tomato.jpg",
      "mime_type": "image/jpeg",
      "size": 182341,
      "position": 1,
      "created_at": "..."
    }
  ],
  "produce": {
    "id": 7,
    "name": "Tomato",
    "image_url": "http://127.0.0.1:8000/storage/produce-images/example.jpg",
    "category": {
      "id": 1,
      "name": "Vegetables"
    }
  },
  "farmer": {
    "id": 4,
    "name": "PoC Farmer",
    "farmer_code": "FAR-000004",
    "state": "Kaduna",
    "lga": "Kagarko"
  },
  "created_at": "...",
  "updated_at": "..."
}
```

`primary_image_url` prefers the first ordered listing image. If no listing-specific image exists, it falls back to the produce catalog image.

---

## Public Listing Detail

```http
GET /api/v1/listings/{listing}
```

Returns the same listing resource shape as the public list. A listing that is not marketplace-visible behaves as not found.

**Response `404`**

```json
{
  "message": "Listing not found."
}
```

---

# Authentication and Profile Endpoints

## Register

```http
POST /api/v1/register
```

**Content-Type:** `application/json`

### Body

| Field | Type | Required | Rules |
|---|---|---:|---|
| `name` | string | Yes* | max 255 |
| `first_name` | string | No | max 100; accepted with `last_name` as Figma-compatible alternative |
| `last_name` | string | No | max 100 |
| `email` | string | Yes | valid, unique, max 255 |
| `password` | string | Yes | Laravel password defaults; must be confirmed |
| `password_confirmation` | string | Yes | must match `password` |
| `role` | string | Yes | `admin` or `user` for the PoC |

`name` is automatically built from `first_name + last_name` when `name` is omitted and both names are present.

### Example

```json
{
  "first_name": "Jane",
  "last_name": "Doe",
  "email": "jane@example.com",
  "password": "password",
  "password_confirmation": "password",
  "role": "user"
}
```

**Response `201`** — JWT token response.

Registration immediately returns a JWT. Registration/login are not email-verification-gated in the PoC.

---

## Login

```http
POST /api/v1/login
```

```json
{
  "email": "jane@example.com",
  "password": "password"
}
```

**Response `200`** — JWT token response.

**Response `401`**

```json
{
  "message": "Invalid credentials."
}
```

**Response `403`** for inactive account:

```json
{
  "message": "Account is inactive."
}
```

---

## Get Current Profile

```http
GET /api/v1/me
```

**Auth:** any active authenticated account.

**Response `200`**

```json
{
  "data": {
    "id": 2,
    "account_code": "BYR-000002",
    "name": "Jane Doe",
    "email": "jane@example.com",
    "email_verified_at": null,
    "is_email_verified": false,
    "phone_number": "+2348012345678",
    "state": "Kaduna",
    "lga": "Kagarko",
    "address": "Demo address",
    "avatar_path": null,
    "avatar_url": null,
    "role": "user",
    "status": "active",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

---

## Update Current Profile

```http
PATCH /api/v1/me
```

Use JSON when no file is sent, or `multipart/form-data` when uploading an avatar.

| Field | Type | Required | Rules |
|---|---|---:|---|
| `name` | string | No | max 255 |
| `email` | string | No | valid, unique, max 255 |
| `phone_number` | string/null | No | max 30 |
| `state` | string/null | No | max 100 |
| `lga` | string/null | No | max 100 |
| `address` | string/null | No | max 1000 |
| `avatar` | file/null | No | JPEG/JPG/PNG, max 2 MB |

Changing email clears `email_verified_at`.

Avatar files are stored on the public filesystem disk under `user-avatars/`.

---

## Remove Current Avatar

```http
DELETE /api/v1/me/avatar
```

Returns the updated user profile.

---

## Change Password

```http
PATCH /api/v1/me/password
```

```json
{
  "current_password": "old-password",
  "new_password": "new-password"
}
```

The current password must be correct. The current backend request contract validates `new_password`; `new_password_confirmation` may be sent by clients but is not required by this route's current validation rule.

**Response `200`**

```json
{
  "message": "Password updated successfully."
}
```

---

## Send Email Verification Code

```http
POST /api/v1/email/verification/send
```

or:

```http
POST /api/v1/email/verification/resend
```

**Auth:** required.  
**Throttle:** 3 requests/minute for the verification group.

**Response `200`**

```json
{
  "message": "Verification code sent.",
  "expires_in": 600
}
```

If already verified:

```json
{
  "message": "Email is already verified."
}
```

---

## Verify Email Code

```http
POST /api/v1/email/verification/verify
```

```json
{
  "code": "123456"
}
```

`code` must be exactly 6 digits.

---

## Forgot Password

```http
POST /api/v1/password/forgot
```

```json
{
  "email": "jane@example.com"
}
```

The response intentionally does not reveal whether the email exists.

```json
{
  "message": "If that email is registered, a password reset code has been sent.",
  "expires_in": 600
}
```

---

## Verify Password Reset Code

```http
POST /api/v1/password/verify-code
```

```json
{
  "email": "jane@example.com",
  "code": "123456"
}
```

**Response `200`**

```json
{
  "message": "Verification code accepted.",
  "reset_token": "64-character-reset-token",
  "expires_in": 600
}
```

---

## Reset Password

```http
POST /api/v1/password/reset
```

```json
{
  "email": "jane@example.com",
  "reset_token": "64-character-reset-token",
  "password": "new-password",
  "password_confirmation": "new-password"
}
```

The backend requires a valid 64-character reset token and a valid password. The reset token is short-lived and single-use.

---

## Logout

```http
POST /api/v1/logout
```

**Auth:** required.

```json
{
  "message": "Logged out successfully."
}
```

The current JWT is blacklisted/revoked through the JWT guard.

---

# Buyer Order and Payment Endpoints

> **Requires:** active authenticated `user` role.

## List My Orders

```http
GET /api/v1/orders
```

### Query parameters

| Parameter | Type | Required | Rules |
|---|---|---:|---|
| `search` | string | No | max 255 |
| `status` | string | No | `new`, `in_transit`, `delivered`, `cancelled` |
| `payment_status` | string | No | `pending`, `paid`, `failed`, `refunded` |
| `sort` | string | No | `created_at`, `order_number`, `total`, `status`, `payment_status` |
| `order` | string | No | `asc`, `desc` |
| `page` | integer | No | >= 1 |
| `per_page` | integer | No | 1-100, default 20 |

Returns a paginated `OrderResource` collection.

---

## Create Order — Modern Multi-item Contract

```http
POST /api/v1/orders
```

### Body

| Field | Type | Required | Rules |
|---|---|---:|---|
| `items` | array | Yes | 1-50 items |
| `items[].listing_id` | integer | Yes | existing, distinct listing |
| `items[].quantity` | integer | Yes | >= 1 and meets listing minimum/stock rules |
| `delivery_method` | string | Yes | `standard`, `pickup`, `express` |
| `delivery_name` | string | Yes | max 255 |
| `delivery_phone` | string | Yes | max 30 |
| `delivery_state` | string | Yes | max 100 |
| `delivery_lga` | string | Yes | max 100 |
| `delivery_address` | string | Yes | max 1000 |
| `delivery_notes` | string/null | No | max 1000 |

### Server-owned fields

Clients must not send authoritative values for:

- `subtotal`
- `delivery_fee`
- `total`
- order `status`
- `payment_status`
- item `unit_price`
- item `discount_amount`
- item `line_total`

### Example

```json
{
  "items": [
    {
      "listing_id": 15,
      "quantity": 2
    },
    {
      "listing_id": 21,
      "quantity": 1
    }
  ],
  "delivery_method": "standard",
  "delivery_name": "Jane Doe",
  "delivery_phone": "+2348012345678",
  "delivery_state": "Kaduna",
  "delivery_lga": "Kagarko",
  "delivery_address": "Demo delivery address",
  "delivery_notes": "Call on arrival"
}
```

The order may contain items from multiple farmers. The server creates one parent order with immutable `order_items` snapshots.

Stock is decremented only after all items pass validation inside the transaction.

---

## Create Order — Legacy Single-item Compatibility

The original v1 payload is still accepted:

```http
POST /api/v1/orders
```

```json
{
  "listing_id": 15,
  "quantity": 1
}
```

The backend normalizes it into the modern parent-order/items architecture. When delivery fields are omitted on this legacy path, it preserves the legacy zero delivery fee.

Do not mix `items[]` with `listing_id` / `quantity` in the same request.

---

## Order Resource

Representative response:

```json
{
  "data": {
    "id": 30,
    "order_number": "ORD-000030",
    "user_id": 2,
    "listing_id": 15,
    "quantity": 2,
    "subtotal": "10000.00",
    "delivery_fee": "1500.00",
    "total": "11500.00",
    "status": "new",
    "payment_status": "pending",
    "delivery": {
      "method": "standard",
      "name": "Jane Doe",
      "phone": "+2348012345678",
      "state": "Kaduna",
      "lga": "Kagarko",
      "address": "Demo delivery address",
      "notes": "Call on arrival"
    },
    "items": [
      {
        "id": 50,
        "listing_id": 15,
        "farmer_id": 4,
        "produce_id": 7,
        "produce_name": "Tomato",
        "category_name": "Vegetables",
        "unit": "kg",
        "quantity": 2,
        "unit_price": "5000.00",
        "discount_amount": "1000.00",
        "line_total": "10000.00"
      }
    ],
    "placed_at": "...",
    "deliver_by": "...",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

Compatibility fields `listing_id`, `quantity`, `produce`, and `farmer` may appear for legacy/single-item consumers. New frontend code should use `items[]` as the authoritative purchased-item representation.

---

## Get My Order

```http
GET /api/v1/orders/{order}
```

Returns only an order owned by the authenticated buyer.

The detail endpoint loads the append-only `timeline`:

```json
{
  "timeline": [
    {
      "id": 1,
      "from_status": null,
      "to_status": "new",
      "changed_by": null,
      "note": null,
      "occurred_at": "..."
    }
  ]
}
```

---

## Cancel My Order

```http
PATCH /api/v1/orders/{order}/cancel
```

Only `new` orders can be cancelled. Stock is restored on successful cancellation.

A paid order cannot be cancelled until a refund workflow exists.

---

## Initialize Paystack Payment

```http
POST /api/v1/orders/{order}/payment/initialize
```

An empty body is sufficient.

Clients are prohibited from supplying payment amount, currency, reference, provider or payment status. The server calculates and owns them.

**Response `200`**

```json
{
  "data": {
    "order_id": 30,
    "order_number": "ORD-000030",
    "payment_status": "pending",
    "provider": "paystack",
    "reference": "TC-ORD-000030-ABC123...",
    "authorization_url": "https://checkout.paystack.com/...",
    "access_code": "...",
    "amount": "1150000",
    "currency": "NGN"
  }
}
```

`amount` is in Paystack's lower denomination (kobo).

If a pending Paystack checkout is already initialized, the endpoint returns the existing checkout information rather than creating a second checkout.

---

## Verify Paystack Payment

```http
POST /api/v1/orders/{order}/payment/verify
```

The backend verifies the stored reference with Paystack and checks the reference, amount and currency before marking the order paid.

**Response `200`**

```json
{
  "data": {
    "order_id": 30,
    "order_number": "ORD-000030",
    "payment_status": "paid",
    "provider": "paystack",
    "reference": "TC-ORD-000030-ABC123...",
    "provider_status": "success",
    "paid_at": "..."
  }
}
```

Verification is idempotent for an already-paid order.

---

# Buyer Dispute Endpoints

> **Requires:** active authenticated `user` role.

A buyer can create a dispute only for an order they own. The backend supports order-wide disputes and optional item-specific disputes for multi-item orders.

## List My Disputes

```http
GET /api/v1/disputes
```

### Query parameters

| Parameter | Type | Required | Rules |
|---|---|---:|---|
| `search` | string | No | max 255 |
| `status` | string | No | `open`, `under_review`, `resolved`, `closed` accepted; `under_review` maps to canonical `open` |
| `unread` | boolean | No | true/false |
| `sort` | string | No | `created_at`, `updated_at`, `status`, `subject` |
| `order` | string | No | `asc`, `desc` |
| `page` | integer | No | >= 1 |
| `per_page` | integer | No | 1-100, default 20 |

List responses include unread state and a compact last-message projection rather than requiring the full thread.

---

## Create Dispute

```http
POST /api/v1/disputes
```

### JSON body

```json
{
  "order_id": 30,
  "order_item_id": 50,
  "subject": "Wrong quantity delivered",
  "message": "I ordered 5 bags but received 3."
}
```

`order_item_id` is optional. Omit it for an order-wide dispute.

### With evidence files

Use `multipart/form-data`:

| Field | Type | Required | Rules |
|---|---|---:|---|
| `order_id` | integer | Yes | existing order owned by buyer |
| `order_item_id` | integer | No | existing item belonging to the order |
| `subject` | string | Yes | max 255 |
| `message` | string | Yes | max 5000 |
| `attachments[]` | file[] | No | max 5; JPG/JPEG/PNG/WebP/PDF; max 5 MB each |

One dispute per parent order is retained.

---

## Get My Dispute

```http
GET /api/v1/disputes/{dispute}
```

Returns the full thread and relevant order/item/farmer context.

Representative fields:

```json
{
  "data": {
    "id": 4,
    "order_id": 30,
    "order_item_id": 50,
    "user_id": 2,
    "subject": "Wrong quantity delivered",
    "status": "open",
    "workflow_status": "under_review",
    "unread_count": 0,
    "is_unread": false,
    "buyer": {},
    "affected_farmer": {},
    "affected_item": {},
    "order": {},
    "messages": [],
    "under_review_at": "...",
    "resolved_at": null,
    "closed_at": null,
    "resolution_note": null,
    "created_at": "...",
    "updated_at": "..."
  }
}
```

---

## Mark Buyer Dispute Read

```http
PATCH /api/v1/disputes/{dispute}/read
```

Marks the current buyer's read boundary for the thread.

---

## Send Dispute Message

```http
POST /api/v1/disputes/{dispute}/messages
```

JSON:

```json
{
  "message": "Buyer follow-up message."
}
```

For attachments, use `multipart/form-data` with `message` and up to 5 `attachments[]` files.

Messages are allowed only while the dispute is open/under review.

---

## Download Buyer Dispute Attachment

```http
GET /api/v1/disputes/{dispute}/attachments/{attachment}
```

Evidence bytes are private and served through this authenticated authorization-checked route. Do not construct a public `/storage/...` URL for dispute attachments.

---

# Admin Dashboard, Activity and Notification Endpoints

> **Requires:** active authenticated `admin` role.

## Dashboard

```http
GET /api/v1/admin/dashboard
```

Representative response keys:

```json
{
  "data": {
    "total_orders": 42,
    "orders_change_percent": 12.5,
    "orders_today": 3,
    "total_listings": 18,
    "listings_change_percent": 5.0,
    "pending_listings": 2,
    "active_farmers": 7,
    "farmers_change_percent": 4.2,
    "pending_farmer_verifications": 1,
    "active_buyers": 25,
    "buyers_change_percent": 10.0,
    "active_users": 27,
    "new_buyers_this_week": 4,
    "comparison": {
      "basis": "month_to_date_vs_previous_month_to_date",
      "current_period": {},
      "previous_period": {}
    },
    "order_action_queue": [],
    "order_action_queue_count": 5
  }
}
```

Compatibility note: `active_users` retains the original meaning of all buyer/user-role accounts. `active_buyers` is the actual active subset.

The action queue includes only non-terminal `new` and `in_transit` orders.

---

## Gross Paid Revenue Series

```http
GET /api/v1/admin/dashboard/revenue
```

### Query parameters

| Parameter | Values |
|---|---|
| `period` | `week`, `month`, `year` (default `month`) |
| `farmer_id` | optional existing farmer ID |

This metric is **gross paid order-item revenue**, not money released to farmers.

Representative response:

```json
{
  "data": {
    "metric": "gross_paid_order_item_revenue",
    "period": "month",
    "farmer_id": null,
    "range": {
      "start": "2026-08-01",
      "end": "2026-08-31",
      "previous_start": "2026-07-01",
      "previous_end": "2026-07-31"
    },
    "summary": {
      "revenue": "500000.00",
      "previous_period_revenue": "420000.00",
      "change_percent": 19.05,
      "paid_orders": 12,
      "paid_order_items": 18,
      "unallocated_paid_revenue": "0.00",
      "unallocated_paid_order_items": 0
    },
    "series": []
  }
}
```

---

## Farmer Payout Dashboard

```http
GET /api/v1/admin/dashboard/payouts
```

Uses the same `period` and optional `farmer_id` query parameters.

This is the Figma-facing **money actually paid out to farmers** metric.

```json
{
  "data": {
    "metric": "farmer_payouts",
    "period": "month",
    "farmer_id": null,
    "range": {},
    "summary": {
      "paid_out": "350000.00",
      "previous_period_paid_out": "300000.00",
      "change_percent": 16.67,
      "payouts_count": 10,
      "active_farmers": 6,
      "average_per_bucket": "11290.32",
      "peak": {}
    },
    "series": []
  }
}
```

---

## Recent Activities

```http
GET /api/v1/admin/activities
```

| Parameter | Values |
|---|---|
| `type` | optional: `order`, `dispute`, `farmer`, `listing`, `buyer` |
| `limit` | 1-50; default 10 |

Returns normalized activity objects with fields such as `id`, `type`, `action`, `title`, `description`, `status`, `actor`, `entity`, `meta`, and `occurred_at`.

---

## List Admin Notifications

```http
GET /api/v1/admin/notifications
```

### Query parameters

| Parameter | Values |
|---|---|
| `status` | `all`, `unread`, `read` |
| `type` | `order`, `payment`, `dispute`, `farmer`, `listing`, `buyer`, `system` |
| `page` | >= 1 |
| `per_page` | 1-100; default 20 |

Response `meta.unread_count` is the total unread badge count for the authenticated admin, independent of the active list filter.

Notification resource:

```json
{
  "id": "uuid",
  "type": "listing",
  "title": "Listing published",
  "message": "...",
  "action_url": "/api/v1/admin/listings/15",
  "entity": {
    "type": "listing",
    "id": 15
  },
  "is_read": false,
  "read_at": null,
  "created_at": "..."
}
```

The Figma-explicit notification producers include new farmer and listing publication events, in addition to existing order/dispute-related notifications.

---

## Mark One Notification Read

```http
PATCH /api/v1/admin/notifications/{notification}/read
```

The notification is scoped to the current admin.

---

## Mark All Notifications Read

```http
PATCH /api/v1/admin/notifications/read-all
```

```json
{
  "data": {
    "marked_read_count": 5,
    "unread_count": 0
  }
}
```

---

# Admin Category and Produce Endpoints

> **Requires:** active authenticated `admin` role.

## Categories

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/admin/categories` | List categories |
| `POST` | `/api/v1/admin/categories` | Create category |
| `GET` | `/api/v1/admin/categories/{category}` | Get category |
| `PUT/PATCH` | `/api/v1/admin/categories/{category}` | Update category |
| `DELETE` | `/api/v1/admin/categories/{category}` | Delete category |

### Create body

```json
{
  "name": "Vegetables"
}
```

`name` is required, unique and max 255 characters.

---

## Produce

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/admin/produce` | List all produce |
| `POST` | `/api/v1/admin/produce` | Create produce |
| `GET` | `/api/v1/admin/produce/{produce}` | Get one produce |
| `PUT/PATCH` | `/api/v1/admin/produce/{produce}` | Update produce |
| `DELETE` | `/api/v1/admin/produce/{produce}` | Delete produce |

### Create Produce

```http
POST /api/v1/admin/produce
```

**Content-Type:** `multipart/form-data`

| Field | Type | Required | Rules |
|---|---|---:|---|
| `category_id` | integer | Yes | existing category |
| `name` | string | Yes | unique within category, max 255 |
| `image` | file | Yes | JPEG/JPG/PNG/WebP, max 5 MB |

Example cURL:

```bash
curl -X POST http://127.0.0.1:8000/api/v1/admin/produce \
  -H "Authorization: Bearer {admin_token}" \
  -F "category_id=1" \
  -F "name=Tomato" \
  -F "image=@/path/to/tomato.jpg"
```

### Current produce image behavior

New produce images are stored as files under:

```text
storage/app/public/produce-images/
```

The database stores `image_path` and `image_mime`; the old base64 `image` field is null for migrated/new filesystem-backed rows.

Representative response:

```json
{
  "data": {
    "id": 7,
    "category_id": 1,
    "name": "Tomato",
    "image": null,
    "image_path": "produce-images/abc123.jpg",
    "image_mime": "image/jpeg",
    "image_url": "http://127.0.0.1:8000/storage/produce-images/abc123.jpg",
    "created_at": "...",
    "updated_at": "..."
  }
}
```

A legacy fallback remains for an old row that still contains base64 data, but new frontend code should consume `image_url` and should not rely on raw `image` data.

### Update Produce

`category_id` and `name` are required. `image` is optional and replaces the existing stored image when supplied.

---

# Admin Farmer Endpoints

> **Requires:** active authenticated `admin` role.

## List Farmers

```http
GET /api/v1/admin/farmers
```

### Query parameters

| Parameter | Type / values |
|---|---|
| `search` | string |
| `state` | string |
| `lga` | string |
| `status` | `active`, `inactive` |
| `verification_status` | `pending`, `verified`, `rejected` |
| `sort` | `name`, `farmer_code`, `created_at`, `listings_count` |
| `order` | `asc`, `desc` |
| `page` | >= 1 |
| `per_page` | 1-100, default 20 |

Returns paginated `FarmerResource` objects.

---

## Create Farmer

```http
POST /api/v1/admin/farmers
```

Use `multipart/form-data` when sending a photo or `primary_produce_ids[]` from a form.

| Field | Type | Required | Rules |
|---|---|---:|---|
| `name` | string | Yes | max 255 |
| `email` | string/null | No | valid, unique, max 255 |
| `phone_number` | string | Yes | max 20 |
| `state` | string | Yes | max 255 |
| `lga` | string | Yes | max 255 |
| `address` | string/null | No | max 1000 |
| `gender` | string/null | No | max 30 |
| `date_of_birth` | date/null | No | not in future |
| `nin` | string/null | No | exactly 11 digits |
| `photo` | file/null | No | JPEG/JPG/PNG, max 2 MB |
| `primary_produce_ids[]` | integer[] | No | distinct existing produce IDs, max 50 |
| `farm_name` | string/null | No | max 255 |
| `farm_size_hectares` | number/null | No | >= 0 |
| `farming_method` | string/null | No | max 255 |
| `years_experience` | integer/null | No | 0-65535 |
| `farm_address` | string/null | No | max 1000 |
| `status` | string | No* | defaults to `active`; `active` or `inactive` |

Backend-controlled fields such as `farmer_code`, `verification_status`, `verified_at`, and `suspended_at` cannot be assigned through creation.

NIN is encrypted at rest and never returned raw. The API returns only the masked projection.

Photo files are stored under `farmer-photos/` on the public disk.

---

## Farmer Resource

Representative fields:

```json
{
  "id": 4,
  "farmer_code": "FAR-000004",
  "name": "PoC Farmer",
  "email": "farmer@example.com",
  "phone_number": "+2348011111111",
  "state": "Kaduna",
  "lga": "Kagarko",
  "address": "...",
  "gender": null,
  "date_of_birth": null,
  "nin": "*******8901",
  "has_nin": true,
  "photo_url": "http://127.0.0.1:8000/storage/farmer-photos/example.jpg",
  "primary_produce": [],
  "status": "active",
  "verification_status": "verified",
  "can_publish_listings": true,
  "profile_completeness": {
    "percentage": 85,
    "completed_fields": 11,
    "total_fields": 13,
    "missing_fields": []
  },
  "farm": {
    "name": "PoC Farm",
    "size_hectares": "4.50",
    "farming_method": "Mixed farming",
    "years_experience": 5,
    "address": "..."
  },
  "listings_count": 3,
  "verified_at": "...",
  "suspended_at": null,
  "created_at": "...",
  "updated_at": "..."
}
```

---

## Get Farmer Detail

```http
GET /api/v1/admin/farmers/{farmer}
```

The detail response intentionally contains both legacy complete collections and richer summary/previews.

Additional important fields:

```text
orders_count
completed_orders_count
total_earned            -> legacy earnings meaning retained
total_paid_out           -> actual farmer payout ledger total
summary.listings.*
summary.orders.*
summary.sales.paid_orders_count
summary.sales.total_earned
summary.sales.payouts_count
summary.sales.total_paid_out
listings                 -> full legacy collection
orders                   -> full legacy collection
recent_listings          -> bounded enhanced preview
recent_orders            -> bounded enhanced preview
```

`total_paid_out` is the correct field for the Figma “paid out” concept. Do not relabel legacy `total_earned` as payouts.

---

## Update Farmer

```http
PUT/PATCH /api/v1/admin/farmers/{farmer}
```

The current full update request expects the main required identity/location/status fields (`name`, `phone_number`, `state`, `lga`, `status`) and accepts the same optional profile/farm/NIN/photo/primary-produce fields as create.

Use the dedicated verification endpoint for verification changes.

---

## Set Farmer Operational Status

```http
PATCH /api/v1/admin/farmers/{farmer}/status
```

```json
{
  "status": "inactive"
}
```

Allowed: `active`, `inactive`.

Making a farmer ineligible can affect listing publication eligibility.

---

## Set Farmer Verification Status

```http
PATCH /api/v1/admin/farmers/{farmer}/verification
```

```json
{
  "verification_status": "verified"
}
```

Allowed: `pending`, `verified`, `rejected`.

Only active + verified farmers can have marketplace-visible live listings.

---

## Farmer Activity Log

```http
GET /api/v1/admin/farmers/{farmer}/activities
```

| Parameter | Rules |
|---|---|
| `search` | optional string, max 255 |
| `limit` | 1-100 |

Combines farmer milestones with listing, order-status, and dispute activity relevant to that farmer.

---

## Farmer Payout History

```http
GET /api/v1/admin/farmers/{farmer}/payouts
```

| Parameter | Rules |
|---|---|
| `per_page` | 1-100; default 20 |

Paginated response adds:

```json
{
  "summary": {
    "farmer_id": 4,
    "farmer_code": "FAR-000004",
    "total_paid_out": "350000.00",
    "payouts_count": 10
  }
}
```

---

## Farmer Listings

```http
GET /api/v1/admin/farmers/{farmer}/listings
```

Supported query parameters:

```text
search
category_id
status=active|inactive
publication_status=pending|live|inactive
sort=created_at|price|stock|produce|category
order=asc|desc
page
per_page (1-100)
```

---

## Farmer Orders

```http
GET /api/v1/admin/farmers/{farmer}/orders
```

Supported query parameters:

```text
search
status=new|in_transit|delivered|cancelled
payment_status=pending|paid|failed|refunded
sort=created_at|order_number|total|status|payment_status
order=asc|desc
page
per_page (1-100)
```

A parent order is included when at least one `order_item` belongs to the farmer. Do not infer farmer ownership from the legacy parent `listing_id` field.

---

## Delete Farmer

```http
DELETE /api/v1/admin/farmers/{farmer}
```

Use carefully. This is a destructive endpoint in the current PoC contract.

---

# Admin Listing Endpoints

> **Requires:** active authenticated `admin` role.

## List All Admin Listings

```http
GET /api/v1/admin/listings
```

### Query parameters

```text
search
farmer_id
category_id
status=active|inactive
publication_status=pending|live|inactive
sort=created_at|price|stock|produce|farmer|category
order=asc|desc
page
per_page (1-100, default 20)
```

---

## Create Listing for Farmer

```http
POST /api/v1/admin/farmers/{farmer}/listings
```

### Body

| Field | Type | Required | Rules |
|---|---|---:|---|
| `produce_id` | integer | Yes | existing; unique per farmer |
| `price` | number | Yes | >= 0 |
| `original_price` | number/null | No | >= price |
| `discount_percent` | number/null | No | 0-100; must match prices within 0.01 |
| `unit` | string/null | No | max 50; preferred for new clients |
| `stock` | integer | Yes | >= 0 |
| `minimum_order_quantity` | integer | No | >= 1 |
| `description` | string/null | No | max 5000 |
| `label` | string/null | No | `fresh`, `organic`, `seasonal` |
| `grade` | string/null | No | max 100 |
| `available_from` | date/null | No | availability date |
| `status` | string | No | legacy `active` / `inactive` |
| `publication_status` | string | No | preferred `pending` / `live` / `inactive` |

Recommended create-first-as-pending example:

```json
{
  "produce_id": 7,
  "price": 5000,
  "original_price": 5500,
  "discount_percent": 9.09,
  "unit": "kg",
  "stock": 100,
  "minimum_order_quantity": 1,
  "description": "PoC listing",
  "label": "fresh",
  "grade": "A",
  "available_from": "2026-08-18",
  "publication_status": "pending"
}
```

### Discount rule

If `original_price = 5500` and `price = 5000`, the correct discount is:

```text
((5500 - 5000) / 5500) * 100 = 9.09%
```

Sending `9` fails validation because the supplied percentage does not match the two prices within the accepted tolerance.

### Legacy listing-create compatibility

The original payload remains supported on the farmer-scoped route:

```json
{
  "produce_id": 7,
  "price": 5000,
  "stock": 100,
  "status": "active"
}
```

New frontend code should prefer the richer fields and explicit `publication_status`.

---

## Get Admin Listing

```http
GET /api/v1/admin/listings/{listing}
```

Returns the full `ListingResource` even when it is pending/inactive.

---

## Update / Publish Listing

```http
PATCH /api/v1/admin/listings/{listing}
```

`PUT` is also supported.

To publish:

```json
{
  "publication_status": "live"
}
```

A live listing requires the assigned farmer to be active and verified.

The update endpoint also accepts optional `farmer_id` for reassignment:

```json
{
  "farmer_id": 9
}
```

A live listing can only be reassigned to another active + verified farmer, and the one-listing-per-produce-per-farmer uniqueness rule still applies.

`published_at` reflects a real publication transition and is used by recent activity.

---

## Upload Listing Images

```http
POST /api/v1/admin/listings/{listing}/images
```

**Content-Type:** `multipart/form-data`

```text
images[] = file
```

Rules:

- 1-6 incoming images per request;
- listing can have at most 6 total images;
- JPG/JPEG/PNG/WebP;
- max 5 MB each.

Files are stored under:

```text
storage/app/public/listing-images/{listing_id}/
```

**Response `201`** returns the complete ordered listing-image collection.

---

## Reorder Listing Images

```http
PATCH /api/v1/admin/listings/{listing}/images/reorder
```

```json
{
  "image_ids": [20, 21, 22]
}
```

The array must contain every image belonging to that listing exactly once.

---

## Delete Listing Image

```http
DELETE /api/v1/admin/listings/{listing}/images/{listingImage}
```

Remaining positions are compacted so position 1 remains the primary image.

---

## Delete Listing

```http
DELETE /api/v1/admin/listings/{listing}
```

```json
{
  "message": "Listing deleted."
}
```

---

# Admin Order and Farmer Payout Endpoints

> **Requires:** active authenticated `admin` role.

## List All Orders

```http
GET /api/v1/admin/orders
```

### Query parameters

```text
search
status=new|in_transit|delivered|cancelled
payment_status=pending|paid|failed|refunded
farmer_id
sort=created_at|order_number|total|status|payment_status
order=asc|desc
page
per_page (1-100, default 20)
```

Search covers order number, buyer identity, item produce/category snapshot information, and farmer identity.

---

## Get Admin Order

```http
GET /api/v1/admin/orders/{order}
```

Returns buyer data, order items, legacy compatibility fields, delivery snapshot and append-only status timeline.

---

## Update Order Status

```http
PATCH /api/v1/admin/orders/{order}
```

Examples:

```json
{
  "status": "in_transit"
}
```

```json
{
  "status": "delivered"
}
```

```json
{
  "status": "cancelled"
}
```

Allowed transitions are enforced by the backend:

```text
new -> in_transit
new -> cancelled (only while not paid)
in_transit -> delivered
```

Invalid transition errors are returned as `422` validation failures.

Status changes append timeline events with actor/timestamp attribution.

---

## Release Farmer Payout

```http
POST /api/v1/admin/orders/{order}/farmers/{farmer}/payout
```

Optional body:

```json
{
  "reference": "BANK-REF-001",
  "notes": "August settlement"
}
```

The client must **not** send `amount`. The amount is calculated from immutable `order_items.line_total` rows belonging to that farmer. Delivery fee and other farmers' items are excluded.

Release requirements:

- farmer must be verified;
- parent order must have `payment_status = paid`;
- parent order must have fulfillment `status = delivered`;
- farmer must have payable items on the parent order;
- one payout per farmer + parent order.

The endpoint is idempotent. First release returns `201`; a repeated release returns the existing payout with `200` and:

```json
{
  "meta": {
    "already_released": true
  }
}
```

Representative payout resource:

```json
{
  "id": 9,
  "farmer_id": 4,
  "order_id": 30,
  "farmer_code": "FAR-000004",
  "farmer_name": "PoC Farmer",
  "order_number": "ORD-000030",
  "amount": "10000.00",
  "reference": "BANK-REF-001",
  "notes": "August settlement",
  "paid_at": "...",
  "released_by": {
    "id": 1,
    "account_code": "ADM-000001",
    "name": "Admin User",
    "email": "admin@example.com"
  },
  "created_at": "..."
}
```

---

# Admin User and Buyer Endpoints

> **Requires:** active authenticated `admin` role.

## Generic Users — compatibility/internal

```http
GET /api/v1/admin/users
GET /api/v1/admin/users/{user}
```

These are simple non-paginated generic user endpoints retained for compatibility/internal use. They expose only:

```text
id
name
email
role
created_at
updated_at
```

For buyer management screens, prefer `/admin/buyers`.

---

## List Buyers

```http
GET /api/v1/admin/buyers
```

### Query parameters

```text
search
state
lga
status=active|inactive
sort=name|account_code|created_at
order=asc|desc
page
per_page (1-100, default 20)
```

Buyer resource includes:

```text
id
account_code
name
email
phone_number
state
lga
address
avatar_path
role
status
orders_count
created_at
updated_at
```

---

## Get Buyer

```http
GET /api/v1/admin/buyers/{buyer}
```

An admin account ID requested through this buyer route returns:

```json
{
  "message": "Buyer not found."
}
```

---

## Activate / Deactivate Buyer

```http
PATCH /api/v1/admin/buyers/{buyer}/status
```

```json
{
  "status": "inactive"
}
```

Inactive users cannot log in or continue using protected routes with an existing token.

---

# Admin Dispute Endpoints

> **Requires:** active authenticated `admin` role.

## List Admin Disputes

```http
GET /api/v1/admin/disputes
```

Uses the same dispute-list query contract as the buyer list:

```text
search
status=open|under_review|resolved|closed
unread=true|false
sort=created_at|updated_at|status|subject
order=asc|desc
page
per_page (1-100)
```

Admin list includes buyer/order/item/farmer context and unread state.

---

## Get Admin Dispute

```http
GET /api/v1/admin/disputes/{dispute}
```

Returns the full dispute thread, attachments, resolution metadata and buyer/order context.

---

## Mark Admin Dispute Read

```http
PATCH /api/v1/admin/disputes/{dispute}/read
```

Read boundaries are stored independently per user/admin.

---

## Reply as Admin

```http
POST /api/v1/admin/disputes/{dispute}/messages
```

JSON:

```json
{
  "message": "Please share a delivery photo so we can review the case."
}
```

Or `multipart/form-data` with up to 5 `attachments[]` files (JPG/JPEG/PNG/WebP/PDF, max 5 MB each).

---

## Update Dispute Status

```http
PATCH /api/v1/admin/disputes/{dispute}
```

Allowed target statuses:

```text
resolved
closed
```

Optional resolution note:

```json
{
  "status": "resolved",
  "note": "Resolved after review."
}
```

The API does not allow setting a resolved/closed dispute back to canonical `open` through this endpoint.

After resolution/closure, new messages are no longer accepted.

---

## Download Admin Dispute Attachment

```http
GET /api/v1/admin/disputes/{dispute}/attachments/{attachment}
```

Private authorization-checked download route.

---

# Media Storage and Image URLs

The PoC deliberately stores normal public media as files rather than embedding new uploads as base64 in MySQL.

## Public media

| Media | Storage location |
|---|---|
| Produce images | `storage/app/public/produce-images/` |
| Listing images | `storage/app/public/listing-images/{listing_id}/` |
| Farmer photos | `storage/app/public/farmer-photos/` |
| User/admin avatars | `storage/app/public/user-avatars/` |

These are exposed through Laravel's storage link:

```text
public/storage -> storage/app/public
```

Example URL:

```text
http://127.0.0.1:8000/storage/produce-images/abc123.jpg
```

The frontend should render the URL supplied by the API. Do not reconstruct paths from database fields unless necessary.

## Private media

Dispute evidence is stored privately under the application's private storage area and must be downloaded through the authenticated dispute attachment endpoints.

Do not expose private dispute files through `/storage`.

## Image frontend priority

For a listing card/detail:

1. prefer `listing.primary_image_url`;
2. use `listing.images[]` for gallery/order;
3. `listing.produce.image_url` remains the catalog/fallback image.

---

# Typical End-to-End Workflows

## Admin catalog/listing setup

```text
1. POST  /api/v1/login
2. POST  /api/v1/admin/categories
3. POST  /api/v1/admin/produce
4. POST  /api/v1/admin/farmers
5. PATCH /api/v1/admin/farmers/{farmer}/verification
6. POST  /api/v1/admin/farmers/{farmer}/listings
7. POST  /api/v1/admin/listings/{listing}/images
8. PATCH /api/v1/admin/listings/{listing} -> publication_status=live
9. GET   /api/v1/listings -> confirm public visibility
```

## Buyer marketplace/order flow

```text
1. POST /api/v1/login
2. GET  /api/v1/categories
3. GET  /api/v1/marketplace/summary
4. GET  /api/v1/listings
5. GET  /api/v1/listings/{listing}
6. POST /api/v1/orders
7. GET  /api/v1/orders/{order}
```

Cart state remains frontend-owned for the PoC.

## Buyer payment flow

```text
1. POST /api/v1/orders
2. POST /api/v1/orders/{order}/payment/initialize
3. Frontend opens Paystack authorization_url
4. POST /api/v1/orders/{order}/payment/verify
5. GET  /api/v1/orders/{order}
```

## Admin fulfillment flow

```text
new
 -> PATCH /api/v1/admin/orders/{order} {"status":"in_transit"}
 -> PATCH /api/v1/admin/orders/{order} {"status":"delivered"}
```

## Farmer payout flow

```text
paid order + delivered order + verified farmer
  -> POST /api/v1/admin/orders/{order}/farmers/{farmer}/payout
  -> GET  /api/v1/admin/farmers/{farmer}/payouts
  -> GET  /api/v1/admin/dashboard/payouts
```

## Dispute flow

```text
1. Buyer: POST  /api/v1/disputes
2. Admin: GET   /api/v1/admin/disputes
3. Admin: POST  /api/v1/admin/disputes/{dispute}/messages
4. Buyer: POST  /api/v1/disputes/{dispute}/messages
5. Admin: PATCH /api/v1/admin/disputes/{dispute} -> resolved/closed
```

---

# Frontend Integration Contract

The following points are especially important for the frontend developer.

## Base URL

Use one environment-controlled base URL, for example:

```text
http://127.0.0.1:8000/api/v1
```

Do not hard-code endpoint hosts throughout components.

## JWT

Send:

```http
Authorization: Bearer {token}
```

on all protected routes.

## Pagination

Main list screens are paginated. Records are still in `data`, but `data` is only the current page.

Use `meta.current_page`, `meta.last_page`, `meta.total`, and `links` for navigation.

Farmer detail intentionally retains full legacy `listings` and `orders` collections, plus bounded `recent_listings`/`recent_orders` previews.

## Money

Treat backend totals as authoritative:

```text
listing price
order subtotal
delivery fee
order total
payment amount
payout amount
```

Never calculate a client total and send it as authoritative.

## Standard delivery

The frontend may display NGN 1,500 for standard delivery, but the backend recalculates it.

## Order statuses

Send only:

```text
new (server-created only)
in_transit
delivered
cancelled
```

Do not send `confirmed`, `processing`, or other Figma-only presentation stages.

## Payment status vs fulfillment status

They are different fields and must be rendered independently.

Example:

```json
{
  "status": "in_transit",
  "payment_status": "paid"
}
```

## Listing status vs publication status

New UI should primarily use:

```text
publication_status = pending | live | inactive
```

Legacy:

```text
status = active | inactive
```

is retained for compatibility/safety.

## Disputes

For older API compatibility:

```text
status=open
```

For the richer UI:

```text
workflow_status=under_review
```

## Images

Use URL fields such as:

```text
image_url
primary_image_url
photo_url
avatar_url
images[].url
```

Do not expect new produce images to be base64.

## Multipart forms

Use `FormData` for:

- produce image create/update;
- farmer photo create/update;
- profile avatar upload;
- listing image uploads;
- dispute message/create requests with attachments.

Do not manually set a multipart boundary; let the browser/HTTP library set `Content-Type` for `FormData`.

## Validation

Display Laravel `422` field errors from:

```json
{
  "errors": {
    "field": ["message"]
  }
}
```

rather than only showing a generic request failure.

---

# Intentional PoC Non-Endpoints

The following routes were discussed during parity review but are intentionally not required as separate endpoints for the current PoC.

| Proposed capability | Current PoC decision |
|---|---|
| `POST /refresh` | Not implemented; not required for PoC demo |
| State/LGA reference endpoints | Not implemented; frontend may use static Nigerian location data |
| `GET /delivery-methods` | Not implemented; frontend may use approved methods/fees while server remains authoritative |
| `GET /listings/{listing}/similar` | Not separate; use filtered public listing query and exclude current listing client-side |
| `POST /checkout/quote` | Not separate; `POST /orders` recalculates and validates checkout server-side |
| `GET /payments/{reference}/verify` | Not used; verify by parent order via `/orders/{order}/payment/verify` |
| `GET /orders/{order}/payment` | Not separate; order detail exposes `payment_status` and payment-related flow uses order endpoints |
| `PATCH /admin/orders/{order}/status` | Not separate; `PATCH /admin/orders/{order}` performs the status transition |
| `POST /admin/listings` | Not separate; create through `/admin/farmers/{farmer}/listings` |
| `PATCH /admin/listings/{listing}/status` | Not separate; general listing `PATCH` accepts publication state |
| Paystack webhook | Optional production reliability feature; not required by the controlled PoC checkout/verify flow |

These are not considered backend-parity defects for the current PoC because the required user capabilities are covered by the documented routes or intentionally frontend-owned static/state behavior.

---

# Complete Endpoint Index

## Public

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/health` |
| `POST` | `/api/v1/register` |
| `POST` | `/api/v1/login` |
| `POST` | `/api/v1/password/forgot` |
| `POST` | `/api/v1/password/verify-code` |
| `POST` | `/api/v1/password/reset` |
| `GET` | `/api/v1/categories` |
| `GET` | `/api/v1/marketplace/summary` |
| `GET` | `/api/v1/listings` |
| `GET` | `/api/v1/listings/{listing}` |

## Shared authenticated

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/me` |
| `PATCH` | `/api/v1/me` |
| `PATCH` | `/api/v1/me/password` |
| `DELETE` | `/api/v1/me/avatar` |
| `POST` | `/api/v1/logout` |
| `POST` | `/api/v1/email/verification/send` |
| `POST` | `/api/v1/email/verification/resend` |
| `POST` | `/api/v1/email/verification/verify` |

## Buyer

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/orders` |
| `POST` | `/api/v1/orders` |
| `GET` | `/api/v1/orders/{order}` |
| `PATCH` | `/api/v1/orders/{order}/cancel` |
| `POST` | `/api/v1/orders/{order}/payment/initialize` |
| `POST` | `/api/v1/orders/{order}/payment/verify` |
| `GET` | `/api/v1/disputes` |
| `POST` | `/api/v1/disputes` |
| `GET` | `/api/v1/disputes/{dispute}` |
| `PATCH` | `/api/v1/disputes/{dispute}/read` |
| `POST` | `/api/v1/disputes/{dispute}/messages` |
| `GET` | `/api/v1/disputes/{dispute}/attachments/{attachment}` |

## Admin dashboard / activity / notifications

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/dashboard` |
| `GET` | `/api/v1/admin/dashboard/revenue` |
| `GET` | `/api/v1/admin/dashboard/payouts` |
| `GET` | `/api/v1/admin/activities` |
| `GET` | `/api/v1/admin/notifications` |
| `PATCH` | `/api/v1/admin/notifications/read-all` |
| `PATCH` | `/api/v1/admin/notifications/{notification}/read` |

## Admin categories / produce

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/categories` |
| `POST` | `/api/v1/admin/categories` |
| `GET` | `/api/v1/admin/categories/{category}` |
| `PUT/PATCH` | `/api/v1/admin/categories/{category}` |
| `DELETE` | `/api/v1/admin/categories/{category}` |
| `GET` | `/api/v1/admin/produce` |
| `POST` | `/api/v1/admin/produce` |
| `GET` | `/api/v1/admin/produce/{produce}` |
| `PUT/PATCH` | `/api/v1/admin/produce/{produce}` |
| `DELETE` | `/api/v1/admin/produce/{produce}` |

## Admin farmers

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/farmers` |
| `POST` | `/api/v1/admin/farmers` |
| `GET` | `/api/v1/admin/farmers/{farmer}` |
| `PUT/PATCH` | `/api/v1/admin/farmers/{farmer}` |
| `DELETE` | `/api/v1/admin/farmers/{farmer}` |
| `PATCH` | `/api/v1/admin/farmers/{farmer}/status` |
| `PATCH` | `/api/v1/admin/farmers/{farmer}/verification` |
| `GET` | `/api/v1/admin/farmers/{farmer}/activities` |
| `GET` | `/api/v1/admin/farmers/{farmer}/payouts` |
| `GET` | `/api/v1/admin/farmers/{farmer}/listings` |
| `POST` | `/api/v1/admin/farmers/{farmer}/listings` |
| `GET` | `/api/v1/admin/farmers/{farmer}/orders` |

## Admin listings

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/listings` |
| `GET` | `/api/v1/admin/listings/{listing}` |
| `PUT/PATCH` | `/api/v1/admin/listings/{listing}` |
| `DELETE` | `/api/v1/admin/listings/{listing}` |
| `POST` | `/api/v1/admin/listings/{listing}/images` |
| `PATCH` | `/api/v1/admin/listings/{listing}/images/reorder` |
| `DELETE` | `/api/v1/admin/listings/{listing}/images/{listingImage}` |

## Admin orders / payouts

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/orders` |
| `GET` | `/api/v1/admin/orders/{order}` |
| `PATCH` | `/api/v1/admin/orders/{order}` |
| `POST` | `/api/v1/admin/orders/{order}/farmers/{farmer}/payout` |

## Admin users / buyers

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/users` |
| `GET` | `/api/v1/admin/users/{user}` |
| `GET` | `/api/v1/admin/buyers` |
| `GET` | `/api/v1/admin/buyers/{buyer}` |
| `PATCH` | `/api/v1/admin/buyers/{buyer}/status` |

## Admin disputes

| Method | Endpoint |
|---|---|
| `GET` | `/api/v1/admin/disputes` |
| `GET` | `/api/v1/admin/disputes/{dispute}` |
| `PATCH` | `/api/v1/admin/disputes/{dispute}/read` |
| `POST` | `/api/v1/admin/disputes/{dispute}/messages` |
| `GET` | `/api/v1/admin/disputes/{dispute}/attachments/{attachment}` |
| `PATCH` | `/api/v1/admin/disputes/{dispute}` |

---

## Handoff Notes

- This document describes the PoC/Figma-parity backend contract, including the produce-image filesystem migration.
- Existing v1 compatibility fields are intentionally documented where they remain part of the API.
- New frontend code should prefer multi-item `items[]`, `publication_status`, URL-based media fields, and `workflow_status` where applicable.
- The server remains authoritative for money, delivery fee, stock validation, payment state, fulfillment state, listing publication eligibility, and farmer payout amounts.
- The accompanying Postman collection can be used as an executable reference for the main requests and workflows.

