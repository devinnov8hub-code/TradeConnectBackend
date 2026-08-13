# Trade Connect Backend Gap and Endpoint Specification

Updated: 2026-08-13
Supersedes: 2026-08-11 draft
Repository target: `docs/backend-gap-spec.md`

## 1. Purpose

This document is the current implementation contract for the Trade Connect Laravel backend against the Figma Admin and Buyer flows.

It replaces the earlier gap-only view with a living status document that records:

- what is already implemented;
- what is partially implemented;
- deliberate PoC exceptions;
- remaining Figma/backend gaps;
- confirmed architecture decisions;
- blocked external work;
- the next implementation sequence;
- revised effort estimates based on observed development velocity.

The backend should continue to be extended incrementally. A rewrite is not recommended.

## 2. Current executive status

The project has moved from a basic CRUD backend to a much stronger Figma-aligned foundation.

Current high-level position:

| Area | Status | Summary |
|---|---|---|
| Authentication/account foundation | PARTIAL | JWT auth retained; account codes, richer user profile fields, active/inactive state, inactive-login blocking, and active-account middleware implemented. OTP/reset/profile editing/logout/refresh remain. |
| Admin buyers | IMPLEMENTED - CORE | Search, state/LGA/status filters, pagination, order count, detail resource, activate/deactivate action, and inactive-session enforcement implemented. |
| Admin farmers | IMPLEMENTED - CORE | Expanded profile/farm fields, farmer codes, operational status, verification status, list filtering/pagination, status actions, verification actions, nested listings, and nested orders implemented. |
| Farmer advanced profile | PARTIAL | Primary-produce pivot, photo/NIN handling, top-products response, activity timeline, and archive/soft-delete safety remain. |
| Public listings | IMPLEMENTED - CORE | Active-only listing browse, eager relationships, search/filter/sort, pagination, and explicit unit implemented. |
| Admin listings | IMPLEMENTED - CORE | Global and farmer-scoped search/filter/sort/pagination implemented. Rich listing metadata/publication workflow remains. |
| Order architecture | IMPLEMENTED - CORE | Parent orders, multi-item order items, snapshots, multi-farmer ownership, payment state, stock restoration, and compatibility reads implemented. |
| Admin orders | IMPLEMENTED - CORE | Search, fulfillment/payment filters, farmer filter, sorting, pagination, detail, status updates, locking, and cancellation stock restoration implemented. |
| Farmer order history | IMPLEMENTED | Paginated farmer-specific order history derives ownership from `order_items.farmer_id`. |
| Buyer order history | PARTIAL | Buyer order APIs exist, but list search/filter/pagination and full Figma timeline parity remain. |
| Paystack | CODE COMPLETE / E2E BLOCKED | Initialization/verification code and mocked tests are complete; TLS/network path passes; real provider E2E is blocked by unavailable organization credentials. Webhook is not confirmed in current routes. |
| Disputes | PARTIAL | Basic buyer/admin threads and replies exist. Attachments, unread/read state, search/filter/pagination, and richer transitions remain. |
| Notifications | NOT STARTED | Database notification flows and read APIs remain. |
| Dashboard | PARTIAL | Existing dashboard endpoint remains, but Figma summary metrics, activity projection, revenue series, and action queue need enhancement. |
| Production hardening | PARTIAL | Feature-test discipline is strong, but concurrency, destructive-delete safety, storage migration, API docs, staging/UAT, and real payment E2E remain. |

### 2.1 Current parity estimate

For a Figma-aligned PoC/MVP backend, current completion is approximately 60-70 percent.

That percentage is not a production-readiness score. The remaining work includes several low-visibility but important production items such as storage safety, concurrency, migration rehearsal, real Paystack verification, indexing, API contract review, and staging UAT.

## 3. Confirmed architecture and product decisions

The following decisions are now treated as current implementation rules unless explicitly revisited.

### 3.1 Orders

- One checkout creates one parent order.
- One parent order may contain items from multiple farmers.
- One fulfillment status is kept on the parent order for the current MVP.
- Farmer ownership/reporting is derived from `order_items.farmer_id`, not legacy `orders.listing_id`.
- Order-item snapshot fields preserve historical produce/category/unit/price information.
- Payment status is separate from fulfillment status.
- Client-provided money values are not authoritative.
- Delivery fee remains `0.00` until a real delivery-fee rule is confirmed.
- Legacy `orders.listing_id` and `orders.quantity` remain temporarily for compatibility.

### 3.2 Current order status model

The backend intentionally still uses the legacy fulfillment values:

```text
new
in_transit
cancelled
delivered
```

The richer Figma progression (`created`, `confirmed`, `processing`, `out_for_delivery`, etc.) is deferred rather than forcing broad enum churn before timeline/status-event work is implemented.

Current payment values are:

```text
pending
paid
failed
refunded
```

### 3.3 Public registration PoC exception

The current PoC intentionally allows public role selection, including admin.

This is a deliberate PoC exception and must not be confused with the production recommendation. Production hardening should restore buyer-only public registration and move admin creation to bootstrap/invite/seeder behavior.

### 3.4 Farmer state

Farmer state is split into two concerns:

```text
operational status: active | inactive
verification status: pending | verified | rejected
```

Existing farmers were backfilled with stable `FAR-######` codes. Legacy farmers were treated as verified for compatibility with existing inventory.

### 3.5 Cart

Persistent server-side cart remains deferred. Frontend cart state is acceptable for the current MVP as long as the server revalidates listings, stock, quantities, and totals when creating orders/payment flows.

## 4. Current data model status

### 4.1 Users

Implemented additions:

| Field | Status | Notes |
|---|---|---|
| account_code | IMPLEMENTED | Server-generated `BYR-######` or `ADM-######` |
| phone_number | IMPLEMENTED | Nullable |
| state | IMPLEMENTED | Nullable |
| lga | IMPLEMENTED | Nullable |
| address | IMPLEMENTED | Nullable |
| avatar_path | IMPLEMENTED - FOUNDATION | Column exists; upload flow not implemented |
| status | IMPLEMENTED | `active`, `inactive` |
| last_login_at | NOT IMPLEMENTED | Optional operational metadata |

Account code and status are server-controlled and not ordinary fillable client fields.

### 4.2 Farmers

Implemented:

- `farmer_code`;
- email;
- address;
- gender;
- date of birth;
- farm name;
- farm size in hectares;
- farming method;
- years of experience;
- farm address;
- operational status;
- verification status;
- `verified_at`;
- `suspended_at`.

Still required for full parity/hardening:

- avatar/photo storage;
- encrypted NIN plus masked output;
- `farmer_produce` pivot/primary produce;
- soft delete/archive rules;
- activity timeline/history;
- actor/reason audit for state changes if required for production.

### 4.3 Listings

Implemented:

- farmer relation;
- produce/category relation;
- price;
- stock;
- active/inactive status;
- explicit `unit`;
- public and admin pagination/search/filter/sort;
- unit snapshot into order items.

Still required for richer Figma parity:

- description;
- label (`fresh`, `organic`, `seasonal` or confirmed alternative);
- grade;
- available date;
- minimum order quantity;
- original price;
- discount fields;
- multiple listing images;
- storage-backed images instead of base64 catalog images;
- pending/live/inactive publication workflow;
- soft-delete/archive-safe behavior.

### 4.4 Orders and order items

Implemented parent-order foundation includes:

- order number;
- buyer relation;
- subtotal;
- delivery fee;
- total;
- fulfillment status;
- payment status;
- payment/provider fields used by the current payment flow;
- delivery details/timestamps already introduced by the order redesign;
- compatibility fields for legacy single-listing orders.

Implemented `order_items` includes snapshot-oriented fields such as:

- order/listing/farmer/produce references;
- produce name snapshot;
- category name snapshot;
- unit snapshot;
- quantity;
- unit price;
- discount amount;
- line total.

Still required:

- formal `order_status_events` audit/timeline;
- richer fulfillment statuses if Figma parity requires them;
- final legacy-column cleanup after frontend migration;
- confirmed delivery-fee calculation;
- explicit checkout quote flow if retained as a product requirement.

## 5. Authentication and account status

### 5.1 Implemented

- JWT login/registration foundation.
- Richer auth payload with account/profile/status fields.
- New accounts receive account codes.
- Users default to active.
- Inactive accounts are rejected at login.
- Already-issued JWTs for inactive users are rejected by `EnsureUserIsActive` middleware on protected routes.
- Admin routes also require the active-account check.

### 5.2 Remaining

| Priority | Method | Route | Status | Remaining behavior |
|---|---|---|---|---|
| PROD | POST | `/v1/register` | POC EXCEPTION | Production version should force buyer role and move admin creation to protected bootstrap/invite flow |
| P1 | POST | `/v1/logout` | NOT STARTED | Invalidate current JWT if blacklist/session policy is retained |
| P1 | POST | `/v1/refresh` | NOT STARTED | Refresh JWT within configured window |
| P1 | POST | `/v1/email/verification/send` | NOT STARTED | Throttled OTP/resend |
| P1 | POST | `/v1/email/verification/verify` | NOT STARTED | Verify code and set verification timestamp |
| P1 | POST | `/v1/password/forgot` | NOT STARTED | Non-enumerating reset initiation |
| P1 | POST | `/v1/password/verify-code` | NOT STARTED | Validate reset code |
| P1 | POST | `/v1/password/reset` | NOT STARTED | Complete password reset |
| P1 | GET | `/v1/me` | IMPLEMENTED - CORE | Richer payload now returned |
| P1 | PATCH | `/v1/me` | NOT STARTED | Self-profile update |
| P1 | POST/DELETE | `/v1/me/photo` | NOT STARTED | Avatar upload/remove |
| P1 | PATCH | `/v1/me/password` | NOT STARTED | Authenticated password change |

## 6. Endpoint implementation matrix

Status legend:

- IMPLEMENTED: current required core behavior exists and is tested.
- PARTIAL: route/domain exists, but Figma or production behavior remains.
- NOT STARTED: endpoint/feature is not implemented.
- DEFERRED: intentionally postponed for current MVP.
- BLOCKED: implementation cannot be fully exercised because of an external dependency.

### 6.1 Public marketplace

| Method | Route | Status | Current/remaining behavior |
|---|---|---|---|
| GET | `/v1/listings` | IMPLEMENTED - CORE | Active-only, eager relations, search, category/farmer filters, sorting, pagination, explicit unit |
| GET | `/v1/listings/{listing}` | PARTIAL | Core detail works; richer description/label/grade/images/minimum/discount fields remain |
| GET | `/v1/categories` | NOT STARTED | Public category metadata endpoint still useful for marketplace tabs |
| GET | `/v1/locations/states` | NOT STARTED | Reference data |
| GET | `/v1/locations/states/{state}/lgas` | NOT STARTED | Reference data |
| GET | `/v1/delivery-methods` | NOT STARTED | Needed once real delivery rules are confirmed |
| GET | `/v1/marketplace/summary` | NOT STARTED | Figma hero summary counts |
| GET | `/v1/listings/{listing}/similar` | NOT STARTED | Similar product suggestions |

### 6.2 Cart

Server cart endpoints remain DEFERRED for the current MVP.

### 6.3 Buyer orders and payment

| Method | Route | Status | Current/remaining behavior |
|---|---|---|---|
| GET | `/v1/orders` | PARTIAL | Buyer list exists; add search/filter/pagination to match admin conventions |
| POST | `/v1/orders` | IMPLEMENTED - CORE | Multi-item parent-order foundation; server-side order creation/stock/payment rules retained |
| GET | `/v1/orders/{order}` | PARTIAL | Multi-item detail exists; formal status timeline/event history remains |
| PATCH | `/v1/orders/{order}/cancel` | IMPLEMENTED - CORE | Ownership/state checks and stock restoration behavior exist |
| POST | `/v1/orders/{order}/payment/initialize` | IMPLEMENTED - CODE COMPLETE | Paystack initialization path implemented and mocked |
| POST | `/v1/orders/{order}/payment/verify` | IMPLEMENTED - CODE COMPLETE | Verification path implemented and mocked |
| POST | `/v1/webhooks/paystack` | NOT CONFIRMED | Not present in the latest committed route snapshot; treat as outstanding until verified |
| POST | `/v1/checkout/quote` | NOT STARTED / OPTIONAL | Still useful for a pre-order price/stock quote, but not required if create-order performs equivalent validation |

Paystack operational status:

```text
Integration code: CODE COMPLETE
Mocked automated tests: PASS
TLS/network path: PASS
Real provider E2E: BLOCKED - organization Paystack credentials unavailable
```

Do not disable TLS verification to work around the missing credentials.

### 6.4 Admin orders

| Method | Route | Status | Current behavior |
|---|---|---|---|
| GET | `/v1/admin/orders` | IMPLEMENTED - CORE | Search, status, payment status, farmer filter, sort, pagination, multi-item eager loading |
| GET | `/v1/admin/orders/{order}` | IMPLEMENTED - CORE | Buyer/items/farmers/legacy compatibility loaded |
| PATCH | `/v1/admin/orders/{order}` | IMPLEMENTED - CORE | Transactional status update, terminal-state protection, paid-cancellation protection, timestamps, stock restoration |
| PATCH | `/v1/admin/orders/{order}/status` | OPTIONAL | Current compatibility PATCH route can remain unless frontend contract prefers explicit `/status` |

Remaining admin-order parity:

- formal order status timeline/events;
- optional date range and buyer-id filters if required by the final UI;
- richer Figma status model if product confirms it;
- refund workflow before paid-order cancellation can be fully supported.

### 6.5 Admin farmers

| Method | Route | Status | Current behavior |
|---|---|---|---|
| GET | `/v1/admin/farmers` | IMPLEMENTED - CORE | Search, state/LGA, operational status, verification status, sort, pagination, listing count |
| POST | `/v1/admin/farmers` | PARTIAL | Expanded profile/farm fields and farmer-code generation exist; photo/primary produce remain |
| GET | `/v1/admin/farmers/{farmer}` | PARTIAL | Core profile/stats work; primary produce/top products/activity and payload slimming remain |
| PATCH/PUT | `/v1/admin/farmers/{farmer}` | PARTIAL | Expanded fields supported; primary produce/photo/NIN remain |
| DELETE | `/v1/admin/farmers/{farmer}` | NEEDS HARDENING | Replace destructive delete with archive/soft-delete rules before production |
| PATCH | `/v1/admin/farmers/{farmer}/status` | IMPLEMENTED | Active/inactive with `suspended_at` semantics |
| PATCH | `/v1/admin/farmers/{farmer}/verification` | IMPLEMENTED | Pending/verified/rejected with `verified_at` semantics |
| GET | `/v1/admin/farmers/{farmer}/listings` | IMPLEMENTED | Search/filter/sort/pagination scoped to farmer |
| POST | `/v1/admin/farmers/{farmer}/listings` | IMPLEMENTED - CORE | Existing nested create retained |
| GET | `/v1/admin/farmers/{farmer}/orders` | IMPLEMENTED | Paginated parent-order history via `order_items.farmer_id` |
| GET | `/v1/admin/farmers/{farmer}/activities` | NOT STARTED | Activity Log tab |

Farmer financial summary rule already implemented: paid earnings are calculated from that farmer's order-item line totals on paid parent orders, not from the whole parent order total.

### 6.6 Admin listings

| Method | Route | Status | Current behavior |
|---|---|---|---|
| GET | `/v1/admin/listings` | IMPLEMENTED - CORE | Search/filter/sort/pagination across farmer, produce, category, unit, status |
| GET | `/v1/admin/listings/{listing}` | IMPLEMENTED - CORE | Existing detail retained |
| PATCH/PUT | `/v1/admin/listings/{listing}` | IMPLEMENTED - CORE | Core price/stock/unit/status edits retained |
| DELETE | `/v1/admin/listings/{listing}` | NEEDS HARDENING | Archive/soft delete required for production history safety |
| GET | `/v1/admin/farmers/{farmer}/listings` | IMPLEMENTED | Farmer-scoped searchable paginated tab |
| POST | `/v1/admin/farmers/{farmer}/listings` | IMPLEMENTED - CORE | Current create route retained |
| POST | `/v1/admin/listings` | NOT STARTED / OPTIONAL PREFERRED | Unified global create can be added when rich listing model is introduced |
| PATCH | `/v1/admin/listings/{listing}/status` | NOT STARTED | Only needed with pending/live publication workflow |
| POST/DELETE | listing image routes | NOT STARTED | Depends on storage-backed listing images |

### 6.7 Admin buyers/users

| Method | Route | Status | Current behavior |
|---|---|---|---|
| GET | `/v1/admin/buyers` | IMPLEMENTED | Search name/email/code/phone, state/LGA/status filters, sort, pagination, order count |
| GET | `/v1/admin/buyers/{buyer}` | IMPLEMENTED - CORE | Buyer resource with profile fields/status/order count; rejects admin-as-buyer |
| PATCH | `/v1/admin/buyers/{buyer}/status` | IMPLEMENTED | Activate/deactivate buyer; admin-only; cannot target admin accounts |
| GET | `/v1/admin/buyers/{buyer}/orders` | OPTIONAL | Add only if the detail UI needs a dedicated paginated order tab |
| GET | `/v1/admin/users` | KEEP / INTERNAL | Generic user list remains secondary to Figma flows |
| GET | `/v1/admin/users/{user}` | KEEP / INTERNAL | Generic user detail remains secondary |

Inactive accounts are blocked from login and from using protected routes with already-issued JWTs.

### 6.8 Categories and produce

| Method | Route | Status | Remaining concern |
|---|---|---|---|
| CRUD | `/v1/admin/categories` | IMPLEMENTED - FOUNDATION | Archive/restrict destructive delete before production |
| CRUD | `/v1/admin/produce` | IMPLEMENTED - FOUNDATION | Search/filter polish, storage-backed images, and archive-safe rules remain |

The current `produce` image is still database/base64 oriented and should eventually move to configured file/object storage.

### 6.9 Buyer/admin disputes

Current routes exist for buyer list/create/show/message and admin list/show/message/update.

Status: PARTIAL.

Remaining Figma work:

- search;
- status filters;
- pagination;
- unread/read tracking;
- last-message/unread-count list projections;
- attachments;
- optional affected `order_item_id`;
- richer transition timestamps/notes;
- read endpoints for buyer and admin.

### 6.10 Dashboard, activity, notifications

| Route | Status | Remaining work |
|---|---|---|
| `/v1/admin/dashboard` | PARTIAL | Figma summary cards, period comparisons, pending counts, recent activity, order action queue |
| `/v1/admin/dashboard/revenue` | NOT STARTED | Confirm whether chart means gross farmer revenue or actual payouts before implementing |
| `/v1/admin/activities` | NOT STARTED | Paginated event projection |
| `/v1/admin/notifications` | NOT STARTED | Database notifications, filters, unread count |
| notification read endpoints | NOT STARTED | Read one/read all |

## 7. API conventions now established

The following conventions should be reused by remaining list endpoints:

- `search` for text search;
- explicit relation filters such as `category_id`, `farmer_id`, `buyer_id`;
- `status` and domain-specific status filters such as `payment_status`;
- `sort` plus `order=asc|desc`;
- `page` and `per_page`;
- default `per_page=20` for admin lists unless a screen needs a different size;
- maximum `per_page=100`;
- Laravel Resource pagination response with `data`, `links`, and `meta`;
- `withQueryString()` so filters persist in pagination links;
- HTTP 422 for validation failures;
- HTTP 401 for missing/invalid authentication;
- HTTP 403 for authorization/account-state rejection;
- HTTP 404 for missing or wrong-domain resources.

## 8. Current state machines

### 8.1 User accounts

```text
active <-> inactive
```

Implemented rules:

- inactive users cannot log in;
- inactive users cannot continue using protected routes with an existing JWT;
- buyer status changes are admin-only;
- deactivation does not delete transactional history.

### 8.2 Farmers

```text
verification: pending -> verified
                     -> rejected

operation: active <-> inactive
```

Implemented state timestamps are idempotent: repeated suspend/verify calls keep the original applicable timestamp instead of fabricating a new event time.

### 8.3 Listings

Current implementation remains:

```text
active | inactive
```

Target richer publication flow remains open:

```text
pending -> live -> inactive
```

Do not change the enum until the richer listing workflow is implemented as a deliberate slice.

### 8.4 Orders

Current implementation:

```text
new -> in_transit -> delivered
  \
   -> cancelled
```

Terminal orders (`delivered`, `cancelled`) cannot be updated.

Paid orders cannot currently be cancelled until refund processing exists.

Target Figma timeline values remain deferred and should be introduced together with `order_status_events`, not as an isolated enum rename.

## 9. Migration and backfill status

### 9.1 Completed/additive work

- user profile/status/account-code migration;
- farmer profile/farm/verification/code migration;
- listing unit migration;
- multi-item order/order-item foundation and snapshots;
- legacy compatibility retained while new code reads from order items where ownership/reporting requires it.

### 9.2 Still required before production cleanup

- soft delete/archive migration for transactional parent entities where chosen;
- safer foreign-key delete behavior for users/farmers/produce/listings/categories;
- listing image/storage migration;
- NIN encryption fields if NIN remains in product scope;
- primary-produce pivot if required;
- order status event table;
- dispute attachment/read tables;
- notifications/activity storage;
- final legacy order column cleanup only after frontend migration is verified.

Historical data rules remain:

- do not fabricate missing personal data;
- do not label historical payments paid without evidence;
- preserve order-item snapshots even when source listings change later.

## 10. Testing and definition of done

The working implementation process has been effective and should remain the default:

```text
small slice
-> syntax check
-> targeted feature test
-> related regression tests
-> full test suite
-> commit
-> push
```

### 10.1 Already covered strongly

- admin-only route authorization;
- active/inactive account enforcement;
- buyer management filters/pagination/status;
- farmer filters/pagination/status/verification;
- farmer-scoped listing pagination;
- farmer-scoped order pagination;
- global admin listing pagination/search/filter/sort;
- global admin order pagination/search/filter/sort;
- multi-farmer order ownership semantics;
- order cancellation stock restoration;
- paid-order cancellation protection;
- mocked Paystack behavior.

### 10.2 Important remaining test themes

- buyer order list search/filter/pagination;
- order timeline/status-event append-only behavior;
- concurrency/oversell tests;
- payment webhook idempotency once webhook is implemented/confirmed;
- listing minimum quantity/discount/publication rules;
- archive/delete history safety;
- file upload validation and orphan cleanup;
- dispute attachment/read-state isolation;
- dashboard date/timezone/zero-series behavior;
- database query/index/N+1 inspection;
- staging contract tests against frontend flows.

## 11. Remaining work by priority

### Priority A - finish order experience

Estimated: 3-6 focused hours.

1. Buyer order list search/filter/pagination.
2. Formal order status events/timeline.
3. Return timeline in buyer/admin order detail.
4. Decide whether richer Figma statuses are introduced now or projected from current timestamps first.
5. Confirm webhook route/status and payment idempotency coverage without requiring live credentials.

### Priority B - richer listing parity

Estimated: 3-5 focused hours.

1. Description/label/grade.
2. Minimum quantity/available date/discount fields.
3. Pending/live/inactive publication state if required for current frontend milestone.
4. Listing images and storage-backed media only if needed now; otherwise separate as hardening.

### Priority C - farmer profile completion

Estimated: 2-4 focused hours.

1. Primary produce if UI requires it.
2. Top-products projection.
3. Farmer activities/history.
4. Stop embedding large unpaginated histories in farmer overview once dedicated tabs are fully consumed by frontend.
5. Photo/NIN/archive work according to production scope.

### Priority D - disputes

Estimated: 3-5 focused hours.

1. Search/filter/pagination.
2. Unread/read projection.
3. Attachments.
4. Transition timestamps/notes.

### Priority E - notifications and dashboard

Estimated: 5-9 focused hours combined.

1. Activity projection.
2. Database notifications and read operations.
3. Dashboard summary cards.
4. Order action queue.
5. Revenue series using paid order-item data unless product confirms actual payout semantics.

### Priority F - integration and production hardening

Estimated: variable; see Section 12.

1. Final API contract/OpenAPI collection.
2. Realistic seed/factory data for frontend.
3. Query/index review.
4. Concurrency tests.
5. Storage and destructive-delete safety.
6. Real Paystack provider E2E when organization credentials become available.
7. Staging UAT and migration rehearsal.

## 12. Revised effort estimate

The original 2026-08-11 estimate assumed production-minded implementation from the initial backend state and estimated work in developer days.

Observed implementation velocity has been substantially faster for the incremental PoC slices. Approximately 8-10 focused hours have produced the major account, farmer, listing, buyer-management, and order-management foundations now documented above.

The revised working estimates are therefore:

| Target | Additional focused time from current state | Meaning |
|---|---:|---|
| Figma-ready backend PoC | 12-20 hours | Primary buyer/admin flows available; known production hardening may remain |
| Strong MVP | 18-28 hours | Most important workflows complete and well tested, including timelines/disputes/dashboard essentials |
| Production-minded release | 30-45+ hours | Adds storage/delete safety, concurrency, documentation, indexes, staging/UAT, migration rehearsal, and real provider E2E |

These are working estimates, not guarantees.

Largest remaining schedule variables:

- how much of the richer listing model the frontend actually needs for the next milestone;
- order timeline/state-machine depth;
- dispute attachment/read-state requirements;
- whether dashboard charting means gross revenue or actual payouts;
- real Paystack credentials/webhook access;
- production delete/archive/storage requirements;
- frontend contract changes discovered during integration.

At the current observed pace, two or three additional focused sessions similar to the completed sessions should be enough to bring the primary Figma backend flows close to PoC parity.

## 13. Product decisions - current status

| ID | Decision | Current status |
|---|---|---|
| D1 | Public admin sign-up? | POC EXCEPTION: public role selection intentionally allowed. Production answer still bootstrap/invite only. |
| D2 | Multi-farmer parent order? | CONFIRMED YES and implemented |
| D3 | Independent farmer fulfillment status? | CONFIRMED NO for current MVP |
| D4 | Admin Orders rows parent order or item? | CONFIRMED parent orders |
| D5 | Multiple listings for same farmer/produce? | OPEN; revisit with richer listing model |
| D6 | Listing labels single or multiple? | OPEN; single label remains recommended MVP default |
| D7 | Dashboard chart revenue or actual payout? | OPEN; do not create payout ledger without confirmation |
| D8 | Stock reservation timing? | Current order/payment implementation handles stock around order/payment workflow; full expiry/reservation policy should be reviewed during payment hardening |
| D9 | Paid/later-stage cancellation? | Current rule: paid orders cannot be cancelled until refund processing exists |
| D10 | Depot pickup availability? | OPEN; no delivery-method reference system yet |
| D11 | Gender/DOB/NIN required for farmer creation? | CONFIRMED nullable profile direction; gender/DOB columns exist; NIN deferred |
| D12 | Resolved dispute reopen? | OPEN; no reopen workflow required for current MVP |
| D13 | Persistent carts? | CONFIRMED DEFERRED for MVP |
| D14 | Legacy payment state? | Do not fabricate paid status without evidence |
| D15 | Farmer prefix FAR or FRM? | CONFIRMED `FAR` and implemented |

## 14. Immediate next implementation slice

The recommended next slice is buyer order history parity.

Target:

```text
GET /api/v1/orders
```

Add the established list conventions:

```text
search
status
payment_status
sort
order
page
per_page
```

Search should use parent order number and item snapshot names. Results must remain scoped to the authenticated buyer.

After that, implement `order_status_events` and order-detail timeline projection before moving to richer listing fields.

Recommended sequence from the current branch state:

```text
buyer order history
-> order timeline/status events
-> richer listing fields/workflow
-> farmer profile/activity refinements
-> disputes
-> notifications/dashboard
-> integration and production hardening
```

## 15. Repository ownership and maintenance

Keep this document in the repository as:

```text
docs/backend-gap-spec.md
```

Update it whenever a meaningful slice lands.

For each completed area, change the status in this document rather than leaving completed work described as a future gap. Deliberate PoC exceptions should remain explicit so they are not mistaken for production decisions.

The repository, current migrations/controllers/requests/resources/routes/tests, and passing test suite are the source of truth. This document is a planning and contract layer over that implementation and should not override observed code behavior.
