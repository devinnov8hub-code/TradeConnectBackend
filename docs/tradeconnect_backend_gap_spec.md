# Trade Connect Backend Gap and Endpoint Specification

Date: 2026-08-11

## 1. Purpose

This document maps the Trade Connect Figma flows to the current Laravel backend and defines:

- what can be retained;
- what must be enhanced;
- what must be redesigned;
- what new database structures and endpoints are required;
- the recommended implementation order;
- unresolved product decisions that should be confirmed before coding.

Sources reviewed:

- Figma file `Trade Connect`, page/node `666:7746` (`Admin and Buyer`);
- uploaded Laravel project `tradeconnect.zip`;
- current routes, controllers, requests, resources, models, migrations, tests, and API documentation.

## 2. Executive conclusion

Recommendation: **extend the existing backend; do not rebuild it**.

The current project already has a useful foundation:

- Laravel 13.8 and PHP 8.3;
- versioned `/api/v1` routes;
- JWT authentication;
- admin middleware;
- Form Requests with JSON validation errors;
- Eloquent models and enums;
- API Resources;
- feature tests for auth, listings, orders, disputes, and admin catalog operations.

The largest issue is not code quality. It is that several current data models are smaller than the product shown in Figma.

### P0 blockers before frontend integration

1. **Public registration accepts an `admin` role.** A caller can currently request an admin account through the public register endpoint. Public registration must create buyers only; admin creation must use a protected invitation/bootstrap process.
2. **The order model supports only one listing per order.** Figma shows one buyer order containing multiple items.
3. **Checkout and Paystack are not implemented.** There is no delivery snapshot, payment state, provider reference, webhook, or payment verification flow.
4. **Several workflow states do not match Figma.** Farmer pending verification, listing pending/live, detailed order progress, buyer active/inactive, and dispute under-review/unread state are absent or incomplete.
5. **Hard cascading deletes can erase transaction history.** Deleting categories, produce, listings, farmers, or users can cascade into orders and disputes. Transactional records should not disappear.

## 3. Current backend inventory

### 3.1 Current domain models

| Model | Current key fields | Main limitation |
|---|---|---|
| User | name, email, password, role | No account code, phone, address, avatar, or active/inactive state |
| Category | name | Adequate base catalog model |
| Produce | category_id, name, base64 image | Image is global and stored in the database; no slug or metadata |
| Farmer | name, state, lga, status, phone_number | Missing the detailed personal/farm profile shown in Figma |
| Listing | farmer_id, produce_id, price, stock, status | Missing description, label, grade, unit, minimum order, available date, discount, and listing images |
| Order | user_id, listing_id, quantity, total, status | Single-item only; no order number, delivery, payment, items, or timeline |
| Dispute | order_id, user_id, subject, status | No attachments, unread/read tracking, or optional affected order item |
| DisputeMessage | dispute_id, user_id, body | No attachments or read receipts |

### 3.2 Existing endpoint groups worth retaining

- Public listing browse/detail.
- Category CRUD.
- Produce CRUD.
- Farmer CRUD.
- Admin listing CRUD foundation.
- Buyer order ownership checks and stock locking logic.
- Buyer/admin dispute threads.
- Admin authorization middleware.
- Consistent JSON validation and not-found responses.

Most of these require extension, not replacement.

## 4. Figma-derived functional scope

### Buyer scope

- Buyer sign-up, login, email OTP, password reset.
- Marketplace summary, categories, listing cards, listing detail, similar products.
- Cart quantity changes and removal.
- Checkout with delivery address, notes, delivery method, and Paystack.
- Multi-item order history/detail with payment, delivery, and progress timeline.
- Dispute list/thread, attachments, under-review/resolved status.
- Profile, photo, address, and password changes.

### Admin scope

- Admin authentication and settings.
- Dashboard metrics, trends, revenue/payout chart, recent activity, order action queue.
- Notification list, filtering, unread state, and mark-as-read.
- Farmer list/search/filter/pagination, add farmer, profile, suspension, listings, orders, and activity.
- Buyer list/search/filter/pagination and activation/deactivation.
- Category creation.
- Listing list/search/filter/pagination, create, edit, pending/live state, images, pricing, stock, and farmer assignment.
- Order list/search/filter/pagination, preview, detailed progress, and status transitions.
- Dispute list/search/unread filter, thread, attachments, replies, and resolution.

## 5. Recommended domain decisions

These are the recommended defaults for implementation. Items marked **confirm** should be agreed with the product owner before their migration is finalized.

### 5.1 Account creation and roles

- Public `POST /register` creates a buyer only.
- The request must not accept `role` from an unauthenticated caller.
- Admin accounts should be created by a database seeder, one-time bootstrap command, or invitation from an authenticated privileged admin.
- Add `status` to users: `active`, `inactive`.
- Add a stable human-readable `account_code` such as `BYR-000011` or `ADM-000001`.

**Confirm:** whether the Figma admin sign-up screen is only for an initial system bootstrap or is intended to be public.

### 5.2 Orders

Recommended model: one buyer order has many order items.

```text
orders
  has many order_items
order_items
  belongs to listing
  belongs to farmer through the listing snapshot/reference
```

A single parent order can contain items from multiple farmers because Trade Connect appears to coordinate delivery through a common address/depot flow.

**Confirm:** whether different farmers require independent fulfillment statuses. If yes, add `order_fulfillments` grouped by farmer. For the first release, one order-level fulfillment status is simpler.

### 5.3 Cart

Recommended MVP: keep cart state in the frontend and submit its items to checkout. A persistent server cart is useful but not required to make the Figma flow work.

### 5.4 Listing/catalog boundary

- `produce` remains the reusable catalog identity: Rice, Cassava, Maize.
- Farmer-specific commercial data belongs on `listings`: price, stock, unit, description, grade, label, available date, minimum order, discount, images, and publication state.
- Listing labels seen in Figma include `fresh`, `organic`, and `seasonal`.
- Publication states seen in Figma include `pending` and `live`; add `inactive` for withdrawal/archiving.

**Confirm:** whether one farmer can create more than one listing for the same produce. The current unique constraint prevents this. Seasonal batches and different grades usually require multiple listings.

### 5.5 Farmer state

Do not overload one status column with two separate concerns.

- Operational status: `active`, `inactive`.
- Verification status: `pending`, `verified`, `rejected`.

The Figma display pill can be derived:

- pending verification -> `Pending`;
- verified + active -> `Active`;
- inactive -> `Inactive`.

### 5.6 Money and stock

- Server calculates all prices, discounts, delivery fees, subtotals, and totals.
- Never accept a client-provided total as authoritative.
- Use decimal quantities because units such as kilograms may later be fractional.
- Paystack amounts are converted to kobo only at the provider boundary.

### 5.7 Images

Move images out of base64 database columns and store them on a filesystem/object-storage disk. Store only paths/URLs and metadata in the database.

### 5.8 Deletion and history

- Use soft deletes for users, farmers, produce, and listings where practical.
- Do not hard-delete orders, payments, order items, status events, disputes, or dispute messages.
- Replace destructive cascade behavior with `restrict`, `nullOnDelete`, snapshots, or application-level archive rules.

## 6. Proposed database changes

### 6.1 `users` additions

| Column | Type | Notes |
|---|---|---|
| account_code | string unique | `ADM-...` or `BYR-...` |
| phone_number | string nullable | Normalize before saving |
| state | string nullable | Or FK to a locations table |
| lga | string nullable | Or FK to a locations table |
| address | text nullable | Profile/default delivery address |
| avatar_path | string nullable | Do not store base64 |
| status | string default active | `active`, `inactive` |
| last_login_at | timestamp nullable | Optional operational metadata |

Retain `name` for minimum migration churn. Registration can accept `first_name` and `last_name`, combine them, and persist `name`.

### 6.2 Verification codes

Create `verification_codes` or use a carefully namespaced cache store.

Suggested table:

| Column | Type |
|---|---|
| id | bigint |
| email | string indexed |
| purpose | string (`email_verification`, `password_reset`) |
| code_hash | string |
| expires_at | timestamp |
| attempts | unsigned small integer |
| consumed_at | timestamp nullable |
| created_at / updated_at | timestamps |

Codes must be hashed, short-lived, rate-limited, single-use, and attempt-limited.

### 6.3 `farmers` additions

| Column | Type | Notes |
|---|---|---|
| farmer_code | string unique | Prefer `FAR-...`; Figma also shows `FRM-` once, so standardize |
| email | string nullable unique | Shown in add/profile designs |
| address | text nullable | Personal/location address |
| avatar_path | string nullable | Max 2 MB in current design |
| gender | string nullable | Profile shows it; add form currently omits it |
| date_of_birth | date nullable | Profile shows it; add form currently omits it |
| nin_encrypted | text nullable | Encrypt at rest |
| nin_last4 | string nullable | Safe masked display |
| farm_name | string nullable | |
| farm_size_hectares | decimal(10,2) nullable | |
| farming_method | string nullable | |
| years_experience | unsigned small integer nullable | |
| farm_address | text nullable | |
| status | string | `active`, `inactive` |
| verification_status | string | `pending`, `verified`, `rejected` |
| verified_at | timestamp nullable | |
| suspended_at | timestamp nullable | |
| deleted_at | timestamp nullable | Soft delete |

Create pivot `farmer_produce`:

| Column | Type |
|---|---|
| farmer_id | FK |
| produce_id | FK |
| timestamps | timestamps |

### 6.4 `listings` additions/changes

| Column | Type | Notes |
|---|---|---|
| description | text nullable | Marketplace detail description |
| label | string nullable | `fresh`, `organic`, `seasonal` |
| grade | string nullable | Example: `Grade A - Premium` |
| unit | string default `kg` | Must become explicit; admin form currently implies kg |
| available_from | date nullable | Harvest/available date |
| minimum_order_quantity | decimal(12,2) default 1 | |
| stock | decimal(12,2) | Change from unsigned integer |
| price | decimal(12,2) | Existing field retained |
| original_price | decimal(12,2) nullable | Supports struck-through price |
| discount_percent | decimal(5,2) nullable | Optional/Figma shows a discount badge |
| publication_status | string | `pending`, `live`, `inactive` |
| published_at | timestamp nullable | |
| deleted_at | timestamp nullable | Soft delete |

Create `listing_images`:

| Column | Type |
|---|---|
| id | bigint |
| listing_id | FK |
| path | string |
| mime_type | string |
| size_bytes | unsigned bigint |
| sort_order | unsigned integer |
| is_primary | boolean |
| timestamps | timestamps |

### 6.5 `orders` redesign

Add/rebuild the parent order:

| Column | Type | Notes |
|---|---|---|
| order_number | string unique | Example `ORD-001234` |
| user_id | FK | Buyer |
| subtotal | decimal(12,2) | |
| delivery_fee | decimal(12,2) | Server calculated |
| total | decimal(12,2) | |
| status | string | See state machine below |
| payment_status | string | `pending`, `paid`, `failed`, `refunded` |
| delivery_method | string | `standard_delivery`, `depot_pickup` |
| delivery_name | string | Snapshot |
| delivery_phone | string | Snapshot |
| delivery_state | string | Snapshot |
| delivery_lga | string | Snapshot |
| delivery_address | text nullable | Required for standard delivery |
| delivery_notes | text nullable | |
| placed_at | timestamp nullable | Payment confirmed/order created |
| confirmed_at | timestamp nullable | |
| processing_at | timestamp nullable | |
| out_for_delivery_at | timestamp nullable | |
| deliver_by | timestamp nullable | Estimated date shown in Figma |
| delivered_at | timestamp nullable | |
| cancelled_at | timestamp nullable | |

Create `order_items`:

| Column | Type | Notes |
|---|---|---|
| id | bigint |
| order_id | FK |
| listing_id | nullable FK | Retain reference while preserving snapshots |
| farmer_id | nullable FK | Simplifies farmer reports |
| produce_id | nullable FK | Optional reference |
| produce_name | string | Snapshot |
| category_name | string nullable | Snapshot |
| image_path | string nullable | Snapshot/reference |
| unit | string | Snapshot |
| quantity | decimal(12,2) | |
| unit_price | decimal(12,2) | Snapshot |
| discount_amount | decimal(12,2) default 0 | |
| line_total | decimal(12,2) | |
| timestamps | timestamps |

Create `order_status_events`:

| Column | Type |
|---|---|
| id | bigint |
| order_id | FK |
| status | string |
| changed_by_user_id | nullable FK |
| note | text nullable |
| occurred_at | timestamp |

### 6.6 Payment records

Create `payments`:

| Column | Type |
|---|---|
| id | bigint |
| order_id | FK |
| provider | string default `paystack` |
| reference | string unique |
| amount | decimal(12,2) |
| currency | string default `NGN` |
| status | string |
| authorization_url | text nullable |
| access_code | string nullable |
| provider_payload | json nullable |
| paid_at | timestamp nullable |
| failed_at | timestamp nullable |
| refunded_at | timestamp nullable |
| timestamps | timestamps |

For safe stock handling, reserve/decrement stock while the payment is pending and release it when an unpaid order expires. This can use order-level reservation fields or a separate `stock_reservations` table plus an expiry job.

### 6.7 Dispute additions

Add to `disputes`:

- optional `order_item_id` to identify the affected product;
- status values `under_review`, `resolved`, `closed`;
- optional `resolved_at` and `closed_at`.

Create `dispute_message_attachments`:

| Column | Type |
|---|---|
| id | bigint |
| dispute_message_id | FK |
| path | string |
| original_name | string |
| mime_type | string |
| size_bytes | unsigned bigint |
| timestamps | timestamps |

Create `dispute_reads` for per-user unread tracking:

| Column | Type |
|---|---|
| dispute_id | FK |
| user_id | FK |
| last_read_message_id | nullable FK |
| read_at | timestamp |

### 6.8 Dashboard and notifications

- Add Laravel's database `notifications` table.
- Add an `activity_logs` table or a small domain-event projection for orders, listings, farmers, disputes, and payments.
- Add `farmer_payouts` only if the dashboard chart literally represents money paid to farmers. If it is gross sales/revenue, derive it from paid order items and change the UI wording.

### 6.9 Locations and delivery methods

Recommended reference endpoints can be backed by configuration/static data initially.

Optional tables:

- `states`;
- `lgas` with `state_id`;
- `delivery_methods` with code, name, fee, description, estimated_days, active.

## 7. Endpoint gap matrix

Legend:

- **KEEP**: current behavior is broadly sufficient.
- **ENHANCE**: route exists but request, response, filtering, validation, or authorization must change.
- **REDESIGN**: route/domain exists but the current data model cannot support Figma.
- **ADD**: route does not exist.
- **DEFER**: useful but not required for the first backend parity milestone.

Priorities:

- **P0**: required before safe/functional integration.
- **P1**: required for full primary-screen parity.
- **P2**: secondary polish, analytics, or optional persistence.

### 7.1 Authentication, session, and profile

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P0 | POST | `/v1/register` | REDESIGN | Accept buyer first/last name, email, password; force role to buyer/user; create account code; begin email verification |
| P0 | POST | `/v1/login` | ENHANCE | Reject inactive accounts; decide whether unverified users can log in; update last login; return richer profile |
| P0 | POST | `/v1/logout` | ADD | Invalidate current JWT using the enabled blacklist |
| P0 | POST | `/v1/refresh` | ADD | Refresh JWT within the configured refresh window |
| P1 | POST | `/v1/email/verification/send` | ADD | Send or resend a throttled OTP to an email |
| P1 | POST | `/v1/email/verification/verify` | ADD | Validate OTP and set `email_verified_at` |
| P1 | POST | `/v1/password/forgot` | ADD | Send password-reset OTP; return a non-enumerating response |
| P1 | POST | `/v1/password/verify-code` | ADD | Validate the six-digit code for the Figma OTP screen |
| P1 | POST | `/v1/password/reset` | ADD | Set new password using a verified reset code/token |
| P1 | GET | `/v1/me` | ENHANCE | Return account code, phone, state, LGA, address, avatar, status, and verification state |
| P1 | PATCH | `/v1/me` | ADD | Update full name, email, phone, state/LGA, and address |
| P1 | POST | `/v1/me/photo` | ADD | Upload/replace profile image; max 2 MB JPG/PNG per Figma |
| P1 | DELETE | `/v1/me/photo` | ADD | Remove profile image |
| P1 | PATCH | `/v1/me/password` | ADD | Require current password and set a new password |
| P1 | POST | `/v1/admin/admin-invitations` | ADD/DECISION | Secure alternative to public admin sign-up; only if multiple admins are required |

Security requirements for auth routes:

- rate-limit login, verification, resend, and password-reset routes;
- hash OTPs;
- expire and consume codes;
- do not reveal whether an email exists during password reset;
- shorten the current approximately 60-day access-token TTL or require refresh rotation.

### 7.2 Reference and marketplace metadata

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P1 | GET | `/v1/categories` | ADD | Public active categories for marketplace tabs |
| P1 | GET | `/v1/locations/states` | ADD | State selector data |
| P1 | GET | `/v1/locations/states/{state}/lgas` | ADD | LGA selector data scoped to state |
| P1 | GET | `/v1/delivery-methods` | ADD | Return Standard Delivery and Depot Pickup, fees, descriptions, and estimated days |
| P1 | GET | `/v1/marketplace/summary` | ADD | Counts shown in hero: live listings, active farmers, distinct LGAs |
| P2 | GET | `/v1/platform-policies` | ADD/OPTIONAL | Product details, delivery/availability, and returns/quality-guarantee copy if it is not static frontend content |

### 7.3 Public marketplace listings

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P0 | GET | `/v1/listings` | ENHANCE | Return live listings only; add pagination and richer fields |
| P1 | GET | `/v1/listings` query params | ENHANCE | `search`, `category_id`, `state`, `lga`, `label`, `availability`, `min_price`, `max_price`, `sort`, `order`, `page`, `per_page` |
| P0 | GET | `/v1/listings/{listing}` | ENHANCE | Description, label, grade, location, stock/availability, unit, min quantity, current/original price, discount, images, farmer summary |
| P1 | GET | `/v1/listings/{listing}/similar` | ADD | Similar live products based on category/produce/location, excluding current listing |

Recommended pagination shape:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 12,
    "total": 240,
    "last_page": 20
  },
  "links": {
    "next": null,
    "previous": null
  }
}
```

### 7.4 Cart

Recommended MVP: no server endpoint. Quantity changes, removal, and the cart popup can remain frontend state.

Optional persistent-cart endpoints:

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P2 | GET | `/v1/cart` | DEFER | Return authenticated buyer cart |
| P2 | POST | `/v1/cart/items` | DEFER | Add listing and quantity |
| P2 | PATCH | `/v1/cart/items/{item}` | DEFER | Change quantity with stock/minimum checks |
| P2 | DELETE | `/v1/cart/items/{item}` | DEFER | Remove item |
| P2 | DELETE | `/v1/cart` | DEFER | Clear cart |

### 7.5 Checkout and Paystack

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P0 | POST | `/v1/checkout/quote` | ADD | Validate item availability and minimum quantities; calculate subtotal, delivery fee, discounts, and total without creating a paid order |
| P0 | POST | `/v1/orders` | REDESIGN | Create pending-payment multi-item order, reserve stock, initialize Paystack, return authorization URL/reference |
| P0 | POST | `/v1/webhooks/paystack` | ADD | Verify Paystack signature; process events idempotently; mark payment/order paid; create status event |
| P1 | GET | `/v1/payments/{reference}/verify` | ADD | Server-side verification/polling fallback after redirect |
| P1 | GET | `/v1/orders/{order}/payment` | ADD/OPTIONAL | Return payment status and provider reference for frontend polling |

Suggested checkout quote/create payload:

```json
{
  "items": [
    { "listing_id": 14, "quantity": "5.00" },
    { "listing_id": 22, "quantity": "5.00" }
  ],
  "delivery": {
    "full_name": "Joy Smith",
    "phone_number": "+2348012345678",
    "state": "Kaduna",
    "lga": "Kagarko",
    "address": "14 Wuse Zone 4, Abuja, FCT",
    "notes": "Call on arrival",
    "method": "standard_delivery"
  }
}
```

Suggested create-order response outline:

```json
{
  "data": {
    "order": {
      "id": 123,
      "order_number": "ORD-001234",
      "status": "pending_payment",
      "payment_status": "pending",
      "subtotal": "20700.00",
      "delivery_fee": "1500.00",
      "total": "22200.00",
      "items": []
    },
    "payment": {
      "provider": "paystack",
      "reference": "TC-...",
      "authorization_url": "https://...",
      "access_code": "..."
    }
  }
}
```

### 7.6 Buyer orders

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P0 | GET | `/v1/orders` | REDESIGN | Paginated buyer orders with order number, item summary, total, status, payment state, placed date |
| P1 | GET | `/v1/orders` query params | ENHANCE | Search by order number or item name; filter by status; paginate |
| P0 | GET | `/v1/orders/{order}` | REDESIGN | Multi-item detail, delivery snapshot, payment, delivery method, deliver-by date, timeline events |
| P0 | PATCH | `/v1/orders/{order}/cancel` | ENHANCE | Enforce state transition, release all reserved stock, initiate refund when applicable, write status event |

### 7.7 Admin orders

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P0 | GET | `/v1/admin/orders` | REDESIGN | Paginated multi-item order list with buyer and concise item summary |
| P1 | GET | `/v1/admin/orders` query params | ENHANCE | Search, status, payment status, date range, buyer, farmer, page/per-page |
| P0 | GET | `/v1/admin/orders/{order}` | REDESIGN | Full order, items, buyer, farmers, delivery, payment, and status timeline |
| P0 | PATCH | `/v1/admin/orders/{order}/status` | ADD/PREFERRED | Explicit status transition endpoint; retain old PATCH route as compatibility wrapper if needed |
| P1 | POST | `/v1/admin/orders/{order}/confirm` | OPTIONAL | Convenience action for dashboard; generic status endpoint can perform the same transition |

### 7.8 Admin farmers

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P1 | GET | `/v1/admin/farmers` | ENHANCE | Search, LGA/state, operational status, verification status, pagination, listing count |
| P1 | POST | `/v1/admin/farmers` | ENHANCE | Multipart create with personal, location, farm, primary-produce, and photo fields; generate farmer code |
| P1 | GET | `/v1/admin/farmers/{farmer}` | ENHANCE | Overview/profile, stats, primary produce, top products; avoid embedding every order/listing unpaginated |
| P1 | PATCH | `/v1/admin/farmers/{farmer}` | ENHANCE | Edit profile/farm fields and primary produce |
| P1 | DELETE | `/v1/admin/farmers/{farmer}` | ENHANCE | Soft-delete/archive; block destructive deletion of transactional history |
| P1 | PATCH | `/v1/admin/farmers/{farmer}/status` | ADD | Suspend/reactivate farmer; record actor and timestamp |
| P1 | PATCH | `/v1/admin/farmers/{farmer}/verification` | ADD | Approve/reject pending verification |
| P1 | GET | `/v1/admin/farmers/{farmer}/listings` | ENHANCE | Paginated/searchable listing tab; route already exists |
| P1 | GET | `/v1/admin/farmers/{farmer}/orders` | ADD | Paginated order history derived through order items |
| P1 | GET | `/v1/admin/farmers/{farmer}/activities` | ADD | Activity Log tab |

Farmer overview response should include:

- total listings;
- completed orders;
- paid earnings, not merely all non-cancelled order totals;
- personal information;
- farm information;
- primary produce;
- top products;
- recent history timeline.

### 7.9 Admin buyers/users

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P1 | GET | `/v1/admin/buyers` | ENHANCE | Search, location, active/inactive status, pagination, order count |
| P1 | GET | `/v1/admin/buyers/{buyer}` | ENHANCE | Rich profile and order summary if a detail view is retained |
| P1 | PATCH | `/v1/admin/buyers/{buyer}/status` | ADD | Activate/deactivate buyer; action icon in Figma indicates account disable/remove behavior |
| P2 | GET | `/v1/admin/buyers/{buyer}/orders` | ADD/OPTIONAL | Separate paginated history if buyer detail is expanded |
| P2 | GET | `/v1/admin/users` | KEEP/INTERNAL | Generic user listing; not a primary Figma screen |
| P2 | GET | `/v1/admin/users/{user}` | KEEP/INTERNAL | Generic user detail |

Inactive buyers must be prevented from logging in or using protected routes.

### 7.10 Categories and produce catalog

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P1 | GET | `/v1/admin/categories` | KEEP/ENHANCE | Existing CRUD foundation; add optional search/pagination only if catalog grows |
| P1 | POST | `/v1/admin/categories` | KEEP | Drives Create Category in Add Listing |
| P1 | GET | `/v1/admin/categories/{category}` | KEEP | |
| P1 | PATCH/PUT | `/v1/admin/categories/{category}` | KEEP | |
| P1 | DELETE | `/v1/admin/categories/{category}` | ENHANCE | Archive/restrict instead of cascading through orders |
| P1 | GET | `/v1/admin/produce` | ENHANCE | Include category; add search/category filter for listing/farmer forms |
| P1 | POST | `/v1/admin/produce` | ENHANCE | Store image path rather than base64; image should be optional if listing images become authoritative |
| P1 | GET | `/v1/admin/produce/{produce}` | KEEP/ENHANCE | Include category and usage counts |
| P1 | PATCH/PUT | `/v1/admin/produce/{produce}` | ENHANCE | File storage and archive-safe rules |
| P1 | DELETE | `/v1/admin/produce/{produce}` | ENHANCE | Archive/restrict if used by listings/order history |

### 7.11 Admin listings

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P0 | GET | `/v1/admin/listings` | ENHANCE | Search/filter/pagination; include pending/live state, farmer code, category, unit |
| P0 | POST | `/v1/admin/listings` | ADD/PREFERRED | Transactional Figma-aligned create endpoint with farmer, produce/catalog info, pricing, stock, date, label, images, and publication state |
| P1 | POST | `/v1/admin/farmers/{farmer}/listings` | ENHANCE/COMPAT | Existing nested route may delegate to the same service as the preferred route |
| P0 | GET | `/v1/admin/listings/{listing}` | ENHANCE | Complete edit payload |
| P0 | PATCH/PUT | `/v1/admin/listings/{listing}` | ENHANCE | Farmer reassignment rules, price, stock, minimum, status, metadata |
| P1 | PATCH | `/v1/admin/listings/{listing}/status` | ADD | Publish live, save pending, or deactivate; generic update may wrap this |
| P1 | POST | `/v1/admin/listings/{listing}/images` | ADD | Add/reorder listing images after creation |
| P1 | DELETE | `/v1/admin/listings/{listing}/images/{image}` | ADD | Remove non-required images; preserve at least one primary image if product requires it |
| P1 | DELETE | `/v1/admin/listings/{listing}` | ENHANCE | Archive/soft delete rather than destructive deletion |

Suggested unified create fields (`multipart/form-data`):

```text
farmer_id
produce_id OR produce_name + category_id
available_from
label
grade
description
unit
price
original_price
discount_percent
stock
minimum_order_quantity
publication_status
images[]
```

### 7.12 Buyer disputes

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P1 | GET | `/v1/disputes` | ENHANCE | Search, status, unread, pagination; return last message and unread count |
| P1 | POST | `/v1/disputes` | ENHANCE | Create against owned order; optional order item; initial message and attachments |
| P1 | GET | `/v1/disputes/{dispute}` | ENHANCE | Full thread, attachments, order summary, current status |
| P1 | POST | `/v1/disputes/{dispute}/messages` | ENHANCE | Multipart message with `attachments[]`; allow only under-review/open state |
| P1 | PATCH | `/v1/disputes/{dispute}/read` | ADD | Record last-read message/read timestamp |

### 7.13 Admin disputes

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P1 | GET | `/v1/admin/disputes` | ENHANCE | Search, all/unread, status, pagination, last message, unread count |
| P1 | GET | `/v1/admin/disputes/{dispute}` | ENHANCE | Thread, attachments, buyer/order context |
| P1 | POST | `/v1/admin/disputes/{dispute}/messages` | ENHANCE | Multipart reply with attachments |
| P1 | PATCH | `/v1/admin/disputes/{dispute}` | ENHANCE | Transition under-review to resolved/closed; timestamp transition |
| P1 | PATCH | `/v1/admin/disputes/{dispute}/read` | ADD | Admin read state |

### 7.14 Dashboard, activity, revenue, and notifications

| Priority | Method | Route | Status | Required behavior |
|---|---|---|---|---|
| P1 | GET | `/v1/admin/dashboard` | ENHANCE | Summary cards, comparison percentages, today/week counts, pending listing/farmer counts, recent activity preview, order action queue preview |
| P1 | GET | `/v1/admin/dashboard/revenue` | ADD | Period-filtered monthly/weekly farmer revenue or payout series and summary metrics |
| P1 | GET | `/v1/admin/activities` | ADD | Paginated Recent Activity modal; filter by event type/date |
| P1 | GET | `/v1/admin/notifications` | ADD | Paginated/groupable notifications; unread and type filters |
| P1 | PATCH | `/v1/admin/notifications/{notification}/read` | ADD | Mark one as read |
| P1 | PATCH | `/v1/admin/notifications/read-all` | ADD | Mark all visible/current-admin notifications as read |

Recommended dashboard response outline:

```json
{
  "data": {
    "summary": {
      "total_orders": 1284,
      "orders_change_percent": 18,
      "orders_today": 12,
      "total_listings": 342,
      "listings_change_percent": 7,
      "pending_listings": 5,
      "active_farmers": 84,
      "farmers_change_percent": 12,
      "pending_farmer_verifications": 3,
      "active_buyers": 916,
      "buyers_change_percent": 24,
      "new_buyers_this_week": 31
    },
    "recent_activities": [],
    "order_action_queue": []
  }
}
```

## 8. Actions that do not necessarily require new endpoints

| Figma action | Recommended handling |
|---|---|
| Back, close modal, discard changes | Frontend navigation/state only |
| Quantity stepper in a non-persistent cart | Frontend state; server validates at quote/checkout |
| Open/close product accordions | Frontend state; content can be static or returned by listing/policy endpoint |
| Cart icon badge | Frontend state unless persistent cart is chosen |
| Log out | Could delete token client-side, but a server logout endpoint is recommended because JWT blacklisting is already enabled |
| Order progress rendering | Use status events returned by the order detail endpoint; no separate endpoint per step |
| Dashboard order queue | Can be embedded in dashboard or obtained from filtered admin orders; no dedicated queue endpoint is mandatory |


## 9. API conventions and backward compatibility

### 9.1 Route notation

The route tables in this document use `/v1/...` as shorthand. The externally exposed Laravel URLs are expected to remain under `/api/v1/...` unless the project changes its global API prefix.

### 9.2 Response format

Keep the project's existing Resource-based response style and standardize it across new endpoints:

```json
{
  "data": {},
  "message": "Optional human-readable message"
}
```

Validation errors should continue to use HTTP 422 and a predictable `errors` object. Authentication and authorization failures should use 401 and 403 respectively. Missing resources should use 404. State conflicts, such as cancelling an order that is already out for delivery, should use 409.

Paginated endpoints should return Laravel-compatible pagination metadata:

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
    "last_page": 3,
    "per_page": 10,
    "to": 10,
    "total": 30
  }
}
```

### 9.3 Query conventions

Use consistent query names across list endpoints:

- `search` for text search;
- `status` for one status value;
- `statuses[]` only where multi-select filtering is genuinely required;
- `category_id`, `farmer_id`, and `buyer_id` for relation filters;
- `date_from` and `date_to` for inclusive date filtering;
- `sort` and `direction=asc|desc` for sorting;
- `page` and `per_page` for pagination.

Enforce a safe maximum `per_page`, such as 100. Use database indexes on the fields used by the primary filters.

### 9.4 Idempotency and payment safety

`POST /v1/checkout/quote` is read-like and does not create a paid order. `POST /v1/checkout/paystack` should accept an idempotency key or a client checkout UUID so repeated button taps do not create multiple orders or payment references.

Paystack webhooks must:

1. verify the provider signature;
2. look up the payment by the unique reference;
3. verify amount and currency against server records;
4. transition the payment only once;
5. create/activate the order only through an idempotent transaction;
6. record the raw provider event for audit/debugging.

The browser callback is not sufficient proof of payment. The webhook or a server-to-server verification call is authoritative.

### 9.5 Compatibility during the order migration

For a short transition period, the redesigned Order Resource can expose both:

- the new `items` array; and
- a derived legacy `listing`, `quantity`, and `total` only when an order contains exactly one item.

Remove the legacy fields after the frontend and tests use `items`. Do not keep two independent sources of truth in the database.

## 10. Recommended state machines

State transitions should be enforced by domain/service methods or dedicated actions, not by accepting any arbitrary status string from a request.

### 10.1 User account status

```text
active <-> inactive
```

Rules:

- inactive buyers cannot obtain or use new authenticated sessions;
- deactivation does not delete orders, disputes, or payment history;
- reactivation is an admin-only action;
- an already issued JWT for a newly inactive account must be rejected by middleware or revoked.

### 10.2 Farmer verification and operational status

```text
verification: pending -> verified
                       -> rejected

operation:    active <-> inactive
```

Rules:

- a pending or rejected farmer cannot have a live listing unless the product owner explicitly permits it;
- suspension changes operational status to inactive and stores `suspended_at` and actor/reason;
- verification and suspension are separate concepts and should not overwrite one another.

### 10.3 Listing publication status

```text
pending -> live -> inactive
    ^         |       |
    +---------+-------+
```

Rules:

- a listing can become live only when its farmer is verified/active and required fields are valid;
- stock reaching zero changes marketplace availability, but should not necessarily archive the listing;
- `inactive` means intentionally withdrawn/archived;
- marketplace endpoints return only live listings whose farmer and produce are available;
- publication transitions should set `published_at` and emit an activity/notification event.

### 10.4 Order fulfillment status

Recommended values:

```text
pending_payment -> created -> confirmed -> processing
                                      -> out_for_delivery -> delivered

pending_payment -> cancelled/expired
created         -> cancelled
confirmed       -> cancelled (only under the agreed cancellation policy)
```

The Figma labels map as follows:

| API value | Figma label |
|---|---|
| created | New / Order Created |
| confirmed | Order Confirmed |
| processing | Processing |
| out_for_delivery | In Transit / Out for Delivery |
| delivered | Delivered |
| cancelled | Canceled |

Rules:

- payment state is separate from fulfillment state;
- each successful transition appends an `order_status_events` record;
- timestamps on the order are denormalized conveniences, while the event table is the audit trail;
- cancellation after processing should require an explicit admin/refund workflow;
- status updates must be transactional and idempotent.

### 10.5 Payment status

```text
pending -> paid
        -> failed
        -> expired
paid    -> refunded
        -> partially_refunded (future, if needed)
```

Rules:

- only verified provider events may mark a payment paid;
- paid amount and currency must match the expected payment;
- a failed or expired payment releases any stock reservation;
- refunds should not simply rewrite the original payment row without an audit record.

### 10.6 Dispute status

```text
under_review -> resolved -> closed
            -> closed
```

Rules:

- buyer creation begins at `under_review` (the current `open` value maps here);
- the buyer and admin may send messages while under review;
- whether a resolved dispute can be reopened is a product decision;
- closed threads are read-only;
- status transitions should record actor, timestamp, and optional resolution note.

## 11. Migration and backfill plan

Use additive, reversible migrations. Do not perform a destructive one-step rewrite in production.

### Stage A: safety and additive schema

1. Back up the production database and verify restoration.
2. Add new nullable/defaulted columns to users, farmers, listings, orders, and disputes.
3. Create `order_items`, `order_status_events`, `payments`, image, attachment, notification, and read-tracking tables.
4. Add soft-delete columns where selected.
5. Add required indexes and unique constraints, but defer constraints that existing data cannot yet satisfy.
6. Change destructive foreign-key behavior before allowing admin deletes in the new code.

### Stage B: backfill existing data

#### Users

- generate stable `ADM-...` or `BYR-...` codes according to role;
- set legacy users to `active` unless there is contrary operational data;
- do not infer phone/address fields that do not exist.

#### Farmers

- generate `FAR-...` codes;
- preserve existing active/inactive state;
- recommended legacy verification backfill: `verified`, so existing live inventory does not disappear;
- leave newly introduced personal/farm fields nullable;
- do not fabricate NIN, date of birth, email, or farm information.

#### Listings

- map legacy `active` to `live` and legacy `inactive` to `inactive`;
- default unit to `kg` only because the current application and Figma consistently imply kilograms; mark records for review if another unit is known;
- default minimum order quantity to 1;
- preserve existing price and stock;
- decode base64 images to the configured storage disk, verify the generated file, save the path, and retain the old column until the migration is validated.

#### Orders

For every legacy order:

1. generate an `order_number`;
2. create one `order_items` row from the old `listing_id`, `quantity`, and `total`;
3. snapshot produce/farmer/category/unit/price information available at migration time;
4. set parent subtotal and total from the legacy total; set delivery fee to zero;
5. map statuses:
   - `new` -> `created`;
   - `in_transit` -> `out_for_delivery`;
   - `delivered` -> `delivered`;
   - `cancelled` -> `cancelled`;
6. create one matching status event at the best available timestamp.

Do not automatically label every legacy order as paid. The current schema cannot prove payment. Use a temporary `payment_status=unknown` migration value or a documented legacy flag until the product owner decides how historical records should display.

#### Disputes

- map legacy `open` to `under_review`;
- retain `resolved` and `closed`;
- initialize read state conservatively; do not claim a message was read without evidence.

### Stage C: dual-read deployment

Deploy code that reads `order_items` but can temporarily serialize legacy-compatible fields for single-item orders. Write all new orders only to the new parent/item structure. Monitor errors, totals, stock, and Paystack events.

### Stage D: cleanup

After production verification and frontend migration:

- drop the old `orders.listing_id` and `orders.quantity` columns;
- remove obsolete image blobs only after every file has been verified and backed up;
- remove compatibility response fields;
- enforce final non-null and unique constraints;
- remove old enum values no longer accepted by the application.

## 12. Implementation sequence

The work should follow dependencies rather than screen order.

### Phase 0: decisions, baseline, and security (1-2 focused days)

- confirm the open product decisions in Section 14;
- run the existing test suite and record the baseline;
- add a regression test proving public registration cannot create an admin;
- make public registration buyer-only;
- add server logout/refresh or explicitly shorten/review JWT lifetime;
- add account-status checks to authentication middleware;
- create an API collection/OpenAPI baseline from current routes.

### Phase 1: identity, profiles, and reference data (3-5 focused days)

- user account codes and profile fields;
- profile read/update, avatar, and password change;
- email verification and password-reset OTP flows;
- states/LGAs and delivery-method reference endpoints;
- buyer activation/deactivation;
- pagination/filtering conventions shared by admin lists.

### Phase 2: farmers, catalog, and listings (4-6 focused days)

- expanded farmer schema, verification state, photo, primary produce pivot;
- farmer create/update/profile/suspend flows;
- richer listing fields, labels, units, minimum quantity, dates, multiple images;
- pending/live/inactive publication workflow;
- category/public metadata and similar-listing queries;
- storage migration away from base64.

### Phase 3: order and payment foundation (6-10 focused days)

- parent orders, order items, snapshots, and status events;
- legacy data backfill and compatibility Resource;
- checkout quote;
- delivery snapshots/methods/fees;
- Paystack initialization, callback verification, webhook, idempotency, and stock reservation;
- buyer and admin order list/detail/filter/status transitions;
- cancellation and payment-failure stock release.

This phase is the critical path. Dashboard revenue, farmer earnings, and reliable disputes all depend on it.

### Phase 4: disputes, notifications, and dashboard (5-8 focused days)

- dispute attachments and read state;
- admin/buyer search, pagination, unread filters, and status transitions;
- activity event projection;
- database notifications and mark-as-read endpoints;
- dashboard summary, order action queue, and revenue series;
- payout records only if payout behavior is confirmed.

### Phase 5: integration hardening and documentation (4-6 focused days)

- endpoint contract review with the frontend developer;
- complete feature tests and authorization matrix;
- concurrency tests for stock and duplicate webhook delivery;
- API documentation/examples;
- seeders/factories for realistic frontend integration data;
- performance indexes and query inspection;
- staging UAT and migration rehearsal.

Some work can overlap between developers, but Phase 3 should not begin against an unresolved order model.

## 13. Test and definition-of-done matrix

Every endpoint change should include feature tests. The following are minimum acceptance conditions.

### Authentication and account tests

- public registration always creates a buyer and ignores/rejects a supplied admin role;
- duplicate email and weak/mismatched passwords fail predictably;
- verification codes expire, are hashed, are rate-limited, and cannot be reused;
- password reset invalidates the used code and existing sessions as agreed;
- inactive accounts cannot authenticate or continue using protected endpoints;
- admin-only routes reject buyers.

### Profile and media tests

- users can update only their own profile;
- immutable account codes cannot be changed by clients;
- avatar/listing/dispute uploads enforce MIME type, size, and count;
- failed database writes remove newly uploaded orphan files, or a cleanup job handles them;
- NIN is encrypted and only a masked value is serialized.

### Farmer and listing tests

- farmer codes are unique under concurrent creation;
- pending/rejected/inactive farmers cannot publish live listings;
- listing price, stock, unit, minimum quantity, and discount combinations are validated;
- marketplace returns only eligible live listings;
- search/filter/pagination metadata is correct;
- editing a listing cannot alter historical order-item snapshots;
- deleting/archiving catalog data does not delete orders.

### Order and checkout tests

- one order can contain multiple valid items;
- all totals are recalculated on the server;
- minimum order and stock constraints are checked for every item;
- concurrent checkouts cannot oversell stock;
- a repeated idempotency key returns the same checkout/payment result;
- unauthorized buyers cannot view/cancel another buyer's order;
- invalid status transitions return 409;
- every transition records exactly one status event;
- cancellation releases reserved stock according to policy;
- order detail returns item snapshots even when source listings later change or are archived.

### Paystack tests

- initialization sends the server-calculated amount in kobo;
- invalid webhook signatures are rejected;
- wrong amount, currency, or reference never marks payment paid;
- duplicate webhook delivery is harmless;
- payment verification and order activation occur in a database transaction;
- failed/expired payments release stock;
- provider calls are faked in automated tests.

### Dispute tests

- only an order owner can open a buyer dispute;
- the chosen order item belongs to the order;
- message attachments are authorized and validated;
- unread counts change independently for buyer and admin;
- resolved/closed transition rules are enforced;
- one user's read action does not mark another user's thread read.

### Dashboard and notification tests

- every aggregate is based on explicit states (for example paid/delivered), not merely non-cancelled rows;
- date boundaries and timezone are tested;
- filter periods produce stable series, including months/weeks with zero values;
- notification read operations affect only the authenticated admin;
- dashboard queries avoid N+1 behavior and have indexes/query limits.

### Operational completion criteria

A phase is complete only when:

- migrations run forward and roll back in a clean environment;
- the relevant feature tests pass;
- the API contract is documented with sample requests/responses;
- authorization has been reviewed;
- no secrets or `.env` data are committed;
- the endpoint has been exercised against the corresponding frontend flow in staging.

## 14. Product decisions requiring confirmation

These decisions affect the schema or state model and should be answered before their dependent phase begins.

| ID | Decision | Recommended default | Consequence if different |
|---|---|---|---|
| D1 | Is admin sign-up public, bootstrap-only, or invite-only? | Bootstrap/invite only | Public admin sign-up is a critical privilege-escalation risk |
| D2 | Can one buyer order contain items from multiple farmers? | Yes | If no, checkout must split the cart into multiple orders |
| D3 | Do different farmers in one order progress independently? | No for MVP | If yes, add per-farmer `order_fulfillments` and status timelines |
| D4 | Does the admin Orders table represent whole orders or individual order items? | Whole orders, show a product summary plus item count | Treating rows as items changes URLs, pagination, and status ownership |
| D5 | Can a farmer have several listings for the same produce/batch/grade? | Yes | Current unique `farmer_id + produce_id` constraint must be removed/replaced |
| D6 | Are Fresh/Organic/Seasonal mutually exclusive labels or multiple tags? | One label for MVP | Multiple tags require a tag pivot rather than a string/enum |
| D7 | Is the dashboard chart gross farmer revenue or actual money paid out? | Confirm before naming/calculation | Actual payouts require payout records and settlement rules |
| D8 | When is stock reduced: checkout, payment initialization, or payment success? | Reserve at initialization, finalize at payment, expire/release automatically | Reducing only after payment risks overselling; reducing immediately risks stranded stock |
| D9 | Can buyers cancel after admin confirmation/processing? | Only before processing | Later cancellation requires refund and logistics policy |
| D10 | Is depot pickup available for every LGA/listing? | Configurable delivery method by location | A global boolean will not support regional availability |
| D11 | Are gender, date of birth, and NIN required when adding a farmer? | Nullable initially | Figma profile shows them, but Add Farmer does not collect them |
| D12 | Can a resolved dispute be reopened? | No for MVP | Reopening requires another transition and notification rules |
| D13 | Should buyer carts persist across devices/sessions? | No for MVP | Yes requires cart/cart-item endpoints and expiration/merge behavior |
| D14 | How should legacy orders display payment state? | `unknown` until verified | Marking them paid without evidence would falsify financial history |
| D15 | What is the canonical farmer prefix: `FAR` or `FRM`? | `FAR` | Figma contains both; code and UI should standardize before backfill |

### Design inconsistencies to resolve explicitly

1. Buyer order detail clearly shows one order with several items, while the admin order table visually shows one product per row. The backend should not imitate both models simultaneously. The recommended API treats each row as a parent order and returns a compact item summary.
2. Farmer Profile shows gender, date of birth, and NIN, but Add Farmer does not collect them. Keep them nullable or add them to the form; do not make them required silently.
3. The Figma labels the buyer Disputes page header as `Disputes / Admin`, although it is inside the Buyer Portal. Treat this as copy, not an authorization signal.
4. Listing image examples show more than one uploaded image, while existing `produce` stores one global base64 image. The recommended model makes product/listing imagery explicit and ordered.

## 15. Effort estimate

Assumptions:

- one experienced Laravel developer working primarily on this project;
- stable decisions and timely product-owner answers;
- access to Paystack test credentials and webhook configuration;
- frontend developer available for contract checks;
- no real-time WebSocket requirement for disputes/notifications in the first release;
- existing code remains the base rather than being rewritten.

### Estimated focused development time

| Scope | Estimate |
|---|---:|
| P0/P1 core parity: security, auth/profile, farmer/listing expansion, multi-item orders, checkout/payment, primary dispute enhancements, filters/pagination | 15-24 developer days |
| Full Figma parity including richer dashboard analytics, activity, notifications, and confirmed payout behavior | 25-35 developer days total |
| Frontend integration, migration rehearsal, UAT, and production hardening | Add 5-10 developer days |

A realistic calendar estimate for one full-time developer is approximately **4-7 weeks for a production-minded primary release**, or **6-10+ weeks** when work is part-time, requirements remain unsettled, or frontend integration uncovers contract changes.

This estimate is a range, not a promise. Paystack/payment behavior, order splitting/fulfillment, legacy data quality, and payout requirements are the largest variables.

Rebuilding from scratch would likely take longer because it would reproduce working authentication, resources, requests, middleware, disputes, catalog APIs, and tests before reaching the same missing product features.

## 16. Immediate implementation slice

The first code branch should be deliberately small and should not attempt checkout yet.

### Branch 1: security and contract baseline

1. Add a failing test showing that `POST /api/v1/register` cannot create an admin.
2. Remove public role selection and force buyer creation.
3. Add/verify admin bootstrap or seeder behavior.
4. Add user account status and enforce it in authentication middleware.
5. Add logout and refresh endpoints, or document a deliberate alternative JWT policy.
6. Add pagination response conventions and one representative paginated admin endpoint.
7. Publish the initial API contract/collection so frontend and backend naming is aligned.

### Branch 2: order data model

1. Add `order_number`, delivery/payment fields, `order_items`, and status events additively.
2. Backfill current single-item orders into one order item each.
3. Update Order relationships and Resources.
4. Preserve temporary compatibility fields for old tests/consumers.
5. Add multi-item order and transition tests before implementing Paystack.

Only after Branch 2 is stable should checkout/payment initialization be added. This keeps the highest-risk work testable and prevents Paystack logic from being built on a schema that will immediately change.

## 17. Recommended ownership of the specification

Treat this document as a living contract in the repository, for example:

```text
docs/backend-gap-spec.md
```

When a decision in Section 14 is confirmed, record the answer and date. When an endpoint is implemented, change its status from ADD/ENHANCE/REDESIGN to IMPLEMENTED and link the migration, controller, request, resource, and feature-test pull request. This provides a defensible record of why each endpoint exists and prevents Figma, frontend, and backend assumptions from drifting apart.
