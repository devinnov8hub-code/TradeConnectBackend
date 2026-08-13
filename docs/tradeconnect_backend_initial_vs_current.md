# Trade Connect Backend — Initial vs Current File & Endpoint Comparison

**Purpose:** provide a vis-à-vis record of the Laravel backend originally received versus the current backend after the missing-endpoint work completed so far.

- **Original baseline commit recorded in the supplied project:** `9e6f74f013564ebfb1e4c821e4bd3524bfaf8c14`
- **Current project HEAD from the comparison export:** `25ca52a9e1fdff0ef149c25840b02d2faa8ea2f0`
- **Baseline relevant application files:** 92
- **Current relevant application files:** 162
- **Baseline files still present:** 92
- **New relevant files added:** 70
- **Baseline relevant files removed:** 0

> **Comparison note:** the `git diff` and commit-list sections in the supplied comparison export were empty, so a byte-for-byte modified/unchanged classification cannot be proven from that export alone. The **new-file list and no-removal result are exact** from the original ZIP versus the current inventory. The **enhanced-file list** below is based on the implementation work performed and the endpoint slices that were tested during this task. Files not in that list are described as “retained / no recorded feature-level change” rather than claimed to be byte-identical.

## 1. Executive comparison

The backend was **extended, not rebuilt**. The original Laravel/JWT/API foundation was retained, and missing frontend-facing capabilities were added incrementally around it. The biggest changes were multi-item orders, richer listing/farmer/buyer management, payment endpoints, order timelines, dispute workflow/read-state/attachments, and the search/filter/pagination contracts required by the Figma screens.

| Measure | Result |
|---|---:|
| Original relevant files still present | 92 / 92 |
| New relevant files | 70 |
| Baseline relevant files removed | 0 |
| Known baseline files intentionally enhanced | 35 |
| Baseline files retained without a recorded feature-level redesign | 57 |

## 2. Existing files intentionally enhanced

| File | Why it was enhanced |
|---|---|
| `app/Enums/DisputeStatus.php` | Expanded the dispute workflow from the original open/resolved/closed concept to the under-review/resolved/closed flow used by the current API. |
| `app/Http/Controllers/Api/V1/Admin/BuyerController.php` | Enhanced buyer list/detail management with search, filters, pagination and active/inactive status actions. |
| `app/Http/Controllers/Api/V1/Admin/DisputeController.php` | Enhanced admin dispute list/detail/reply/status flows with filtering, read state, attribution, workflow transitions and private attachment downloads. |
| `app/Http/Controllers/Api/V1/Admin/FarmerController.php` | Enhanced farmer administration with richer profile data, status/verification actions, publication eligibility side-effects and activity summaries. |
| `app/Http/Controllers/Api/V1/Admin/ListingController.php` | Enhanced admin listing endpoints with global/farmer-scoped search, filtering, sorting, pagination, rich listing fields and publication rules. |
| `app/Http/Controllers/Api/V1/Admin/OrderController.php` | Enhanced admin order endpoints for multi-item/multi-farmer orders, filtering/pagination, status progression and farmer-scoped history. |
| `app/Http/Controllers/Api/V1/AuthController.php` | Extended authentication responses/behavior to work with richer account state and inactive-account enforcement while retaining the PoC registration contract. |
| `app/Http/Controllers/Api/V1/DisputeController.php` | Enhanced buyer dispute creation/list/detail/reply/read flows with safe order-item attribution, workflow state and private attachments. |
| `app/Http/Controllers/Api/V1/ListingController.php` | Enhanced public marketplace browse/detail with pagination, search, filters, sorting, availability rules and publication visibility. |
| `app/Http/Controllers/Api/V1/OrderController.php` | Redesigned buyer order creation/history around parent orders plus order items, server-side checkout validation, pricing snapshots and cancellation safety. |
| `app/Http/Requests/Admin/StoreFarmerRequest.php` | Extended validation for the richer farmer personal/farm profile required by the admin UI. |
| `app/Http/Requests/Admin/StoreListingRequest.php` | Extended listing creation validation for unit, metadata, publication state and farmer publication eligibility. |
| `app/Http/Requests/Admin/UpdateDisputeStatusRequest.php` | Extended dispute status-change validation to support the richer transition workflow and resolution notes. |
| `app/Http/Requests/Admin/UpdateFarmerRequest.php` | Extended partial-update validation for richer farmer profile fields and protected workflow fields. |
| `app/Http/Requests/Admin/UpdateListingRequest.php` | Extended listing update validation for rich metadata, publication transitions and eligibility checks. |
| `app/Http/Requests/Dispute/StoreDisputeMessageRequest.php` | Extended replies to accept validated private message attachments. |
| `app/Http/Requests/Dispute/StoreDisputeRequest.php` | Extended dispute creation with optional affected order item plus attachments while preserving order-wide disputes. |
| `app/Http/Requests/Listing/IndexListingRequest.php` | Expanded marketplace query validation for search/filter/sort/pagination, price, label and availability filters. |
| `app/Http/Requests/Order/StoreOrderRequest.php` | Expanded checkout validation from a single listing to validated multi-item order input and listing availability rules. |
| `app/Http/Resources/DisputeMessageResource.php` | Expanded dispute message responses with sender metadata and protected attachment download metadata. |
| `app/Http/Resources/DisputeResource.php` | Expanded dispute responses with affected item/farmer context, workflow/read state, last-message data and audit timestamps. |
| `app/Http/Resources/ListingResource.php` | Expanded listing responses with unit, rich metadata, publication/availability state and ordered images. |
| `app/Http/Resources/OrderResource.php` | Expanded order responses with parent-order totals, item snapshots, payment/delivery state and status timeline. |
| `app/Models/Dispute.php` | Extended the dispute domain with affected order item, richer state transitions, independent read tracking and workflow audit relationships. |
| `app/Models/DisputeMessage.php` | Added attachment relationship and deletion cleanup behavior to the existing dispute message model. |
| `app/Models/Farmer.php` | Extended the farmer model with richer profile/verification state, order-item ownership and listing publication eligibility logic. |
| `app/Models/Listing.php` | Extended the listing model with rich metadata, publication state, availability calculation and multiple images. |
| `app/Models/Order.php` | Extended the order model with order items, payment/delivery metadata, timeline events, stock restoration and guarded status transitions. |
| `app/Models/User.php` | Extended user accounts with profile/status/account-code data and relationships used by buyer management and dispute read state. |
| `config/services.php` | Added Paystack service configuration while retaining the existing third-party service configuration structure. |
| `routes/api.php` | Expanded the /api/v1 contract with missing buyer/admin endpoints for payments, farmer actions, scoped lists, listing images, dispute read state and protected attachment downloads. |
| `tests/Feature/Admin/ListingTest.php` | Updated existing listing coverage/fixtures to remain valid under publication eligibility and richer listing rules. |
| `tests/Feature/Dispute/DisputeTest.php` | Updated the original dispute coverage for the richer dispute workflow while preserving the original ownership and one-dispute-per-order behavior. |
| `tests/Feature/Listing/UserListingTest.php` | Updated public listing coverage/fixtures to align with publication eligibility and the richer marketplace visibility rules. |
| `tests/Feature/Order/OrderTest.php` | Updated original buyer order coverage for the parent-order/order-item architecture and newer checkout/order rules. |

## 3. New files created

These files did not exist in the original supplied backend and are present in the current application inventory.

| File | Why it was created |
|---|---|
| `app/Enums/DeliveryMethod.php` | Introduced explicit delivery-method semantics for the expanded checkout/order model. |
| `app/Enums/FarmerVerificationStatus.php` | Added pending/verified/rejected farmer verification states required by admin workflows. |
| `app/Enums/ListingPublicationStatus.php` | Added pending/live/inactive publication state separate from the legacy active/inactive flag. |
| `app/Enums/PaymentStatus.php` | Added payment lifecycle state independent of fulfillment status. |
| `app/Enums/UserStatus.php` | Added active/inactive account state for buyer/user administration. |
| `app/Http/Controllers/Api/V1/Admin/ListingImageController.php` | Added endpoints for uploading, reordering and deleting multiple listing images. |
| `app/Http/Controllers/Api/V1/PaymentController.php` | Added buyer payment initialize/verify endpoints for Paystack checkout. |
| `app/Http/Middleware/EnsureUserIsActive.php` | Prevents inactive accounts from continuing to use protected routes with an existing JWT. |
| `app/Http/Requests/Admin/IndexBuyerRequest.php` | Added a dedicated Form Request for admin search/filter/sort/pagination validation for this endpoint. |
| `app/Http/Requests/Admin/IndexFarmerListingRequest.php` | Added a dedicated Form Request for admin search/filter/sort/pagination validation for this endpoint. |
| `app/Http/Requests/Admin/IndexFarmerOrderRequest.php` | Added a dedicated Form Request for admin search/filter/sort/pagination validation for this endpoint. |
| `app/Http/Requests/Admin/IndexFarmerRequest.php` | Added a dedicated Form Request for admin search/filter/sort/pagination validation for this endpoint. |
| `app/Http/Requests/Admin/IndexListingRequest.php` | Added a dedicated Form Request for admin search/filter/sort/pagination validation for this endpoint. |
| `app/Http/Requests/Admin/IndexOrderRequest.php` | Added a dedicated Form Request for admin search/filter/sort/pagination validation for this endpoint. |
| `app/Http/Requests/Admin/ReorderListingImagesRequest.php` | Validates exact image-order payloads for the listing image reorder endpoint. |
| `app/Http/Requests/Admin/StoreListingImagesRequest.php` | Validates listing image upload count, size and MIME rules. |
| `app/Http/Requests/Admin/UpdateBuyerStatusRequest.php` | Validates the admin buyer activate/deactivate endpoint. |
| `app/Http/Requests/Admin/UpdateFarmerStatusRequest.php` | Validates the farmer operational active/inactive endpoint. |
| `app/Http/Requests/Admin/UpdateFarmerVerificationRequest.php` | Validates the farmer verification workflow endpoint. |
| `app/Http/Requests/Dispute/IndexDisputeRequest.php` | Validates dispute list search/status/unread/pagination query parameters. |
| `app/Http/Requests/Order/IndexOrderRequest.php` | Validates buyer order-history search/filter/sort/pagination query parameters. |
| `app/Http/Requests/Payment/InitializePaymentRequest.php` | Validates payment initialization input for the checkout endpoint. |
| `app/Http/Resources/BuyerResource.php` | Added a buyer-focused response shape for admin buyer list/detail endpoints. |
| `app/Http/Resources/FarmerOrderSummaryResource.php` | Added compact farmer-attributed order previews without misreporting multi-farmer parent totals. |
| `app/Http/Resources/FarmerResource.php` | Added the richer farmer profile/summary projection required by admin profile screens. |
| `app/Http/Resources/ListingImageResource.php` | Added ordered listing-image response metadata. |
| `app/Http/Resources/OrderItemResource.php` | Added immutable per-item purchase snapshot projection for multi-item orders. |
| `app/Http/Resources/OrderStatusEventResource.php` | Added append-only order timeline event projection. |
| `app/Models/DisputeMessageAttachment.php` | Persists metadata for private dispute evidence files. |
| `app/Models/DisputeRead.php` | Stores independent per-user dispute read boundaries. |
| `app/Models/ListingImage.php` | Persists ordered storage-backed listing images. |
| `app/Models/OrderItem.php` | Introduced the line-item entity required for multi-item and multi-farmer parent orders. |
| `app/Models/OrderStatusEvent.php` | Introduced append-only order status history with actor/timestamp attribution. |
| `app/Services/DisputeAttachmentService.php` | Centralizes private dispute attachment storage and orphan cleanup. |
| `app/Services/PaystackService.php` | Encapsulates Paystack API calls and provider-error handling. |
| `database/migrations/2026_08_11_141246_add_order_architecture.php` | Adds parent-order/order-item architecture, order numbers, delivery snapshots and supporting order fields. |
| `database/migrations/2026_08_11_151125_add_payment_metadata_to_orders_table.php` | Adds payment status/reference/timestamps required by payment lifecycle handling. |
| `database/migrations/2026_08_11_152738_add_paystack_checkout_fields_to_orders_table.php` | Adds Paystack checkout access-code/authorization URL persistence. |
| `database/migrations/2026_08_13_090841_add_unit_to_listings_table.php` | Adds explicit listing unit so order-item snapshots can preserve unit semantics. |
| `database/migrations/2026_08_13_092212_add_profile_fields_to_farmers_table.php` | Adds richer personal/farm profile, farmer code and verification metadata. |
| `database/migrations/2026_08_13_102016_add_profile_and_status_fields_to_users_table.php` | Adds buyer/user profile fields, account code and active/inactive status. |
| `database/migrations/2026_08_13_111824_create_order_status_events_table.php` | Creates append-only order timeline/status event storage. |
| `database/migrations/2026_08_13_114107_add_rich_fields_to_listings_table.php` | Adds description/label/grade/minimum quantity/availability/discount/publication fields. |
| `database/migrations/2026_08_13_132529_create_listing_images_table.php` | Creates ordered storage-backed multiple listing images. |
| `database/migrations/2026_08_13_142506_add_workflow_fields_to_disputes_table.php` | Adds affected order item plus dispute workflow timestamps, actors and resolution note. |
| `database/migrations/2026_08_13_142507_create_dispute_reads_table.php` | Creates independent per-user dispute read-state persistence. |
| `database/migrations/2026_08_13_150212_create_dispute_message_attachments_table.php` | Creates metadata storage for private dispute message attachments. |
| `tests/Feature/Admin/BuyerIndexTest.php` | Covers buyer list search/filter/pagination. |
| `tests/Feature/Admin/FarmerActivitySummaryTest.php` | Covers farmer profile summary counts, earnings and recent activity previews. |
| `tests/Feature/Admin/FarmerIndexTest.php` | Covers farmer search/filter/pagination. |
| `tests/Feature/Admin/FarmerListingIndexTest.php` | Covers farmer-scoped listing list behavior. |
| `tests/Feature/Admin/FarmerOrderIndexTest.php` | Covers farmer-scoped order history based on order_items.farmer_id. |
| `tests/Feature/Admin/FarmerProfileTest.php` | Covers richer farmer profile fields and validation. |
| `tests/Feature/Admin/FarmerStateTest.php` | Covers farmer status/verification state changes. |
| `tests/Feature/Admin/ListingIndexTest.php` | Covers global admin listing search/filter/sort/pagination. |
| `tests/Feature/Admin/OrderIndexTest.php` | Covers list/search/filter/pagination behavior for the corresponding admin/buyer order endpoint. |
| `tests/Feature/Auth/InactiveAccountTest.php` | Covers login/session blocking for inactive accounts. |
| `tests/Feature/Dispute/DisputeAttachmentTest.php` | Covers private upload/download authorization, validation and cleanup for dispute attachments. |
| `tests/Feature/Dispute/DisputeWorkflowTest.php` | Covers attribution, read state, list filters/pagination and status transition auditing. |
| `tests/Feature/Listing/ListingImageTest.php` | Covers listing image upload/reorder/delete/storage behavior. |
| `tests/Feature/Listing/ListingPublicationEligibilityTest.php` | Covers active+verified farmer eligibility and forced unpublishing rules. |
| `tests/Feature/Listing/ListingRichnessTest.php` | Covers rich listing metadata and publication-state behavior. |
| `tests/Feature/Listing/ListingUnitTest.php` | Covers listing unit persistence and order snapshot behavior. |
| `tests/Feature/Listing/MarketplaceFilterTest.php` | Covers public marketplace availability, label, price and other filters. |
| `tests/Feature/Order/ListingCheckoutRulesTest.php` | Covers server-side checkout revalidation, minimum quantity, availability, stock and price snapshots. |
| `tests/Feature/Order/OrderIndexTest.php` | Covers list/search/filter/pagination behavior for the corresponding admin/buyer order endpoint. |
| `tests/Feature/Order/OrderItemTest.php` | Covers multi-item parent order and item snapshot behavior. |
| `tests/Feature/Order/OrderTimelineTest.php` | Covers append-only status events, actor attribution and timeline responses. |
| `tests/Feature/Order/PaymentStateTest.php` | Covers payment state separation and related order behavior. |
| `tests/Feature/Order/PaymentTest.php` | Covers mocked Paystack initialize/verify flows and provider handling. |

## 4. Original files retained / no recorded feature-level redesign

These files existed in the supplied backend and still exist now. They were not part of a separately recorded feature-level redesign during the missing-endpoint work. This does **not** assert byte-for-byte identity because the uploaded Git comparison did not include a usable diff.

| File | Why it was retained |
|---|---|
| `app/Enums/FarmerStatus.php` | Existing enum already matched the required core state vocabulary and was retained. |
| `app/Enums/ListingStatus.php` | Existing enum already matched the required core state vocabulary and was retained. |
| `app/Enums/OrderStatus.php` | Existing enum already matched the required core state vocabulary and was retained. |
| `app/Enums/UserRole.php` | Existing enum already matched the required core state vocabulary and was retained. |
| `app/Http/Controllers/Api/V1/Admin/CategoryController.php` | Existing catalog CRUD controller was already adequate for the current missing-endpoint task. |
| `app/Http/Controllers/Api/V1/Admin/DashboardController.php` | Existing basic dashboard endpoint was retained; richer dashboard metrics are the next planned enhancement. |
| `app/Http/Controllers/Api/V1/Admin/ProduceController.php` | Existing catalog CRUD controller was already adequate for the current missing-endpoint task. |
| `app/Http/Controllers/Api/V1/Admin/UserController.php` | Existing general admin user endpoint was retained while buyer-specific management was extended separately. |
| `app/Http/Controllers/Controller.php` | Framework base controller; no product-specific change required. |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Existing admin authorization middleware remained suitable and was retained. |
| `app/Http/Requests/Admin/StoreCategoryRequest.php` | Existing catalog validation remained suitable for this scope. |
| `app/Http/Requests/Admin/StoreProduceRequest.php` | Existing catalog validation remained suitable for this scope. |
| `app/Http/Requests/Admin/UpdateCategoryRequest.php` | Existing catalog validation remained suitable for this scope. |
| `app/Http/Requests/Admin/UpdateOrderStatusRequest.php` | Existing status request remained usable; transition safety was implemented in order/controller logic. |
| `app/Http/Requests/Admin/UpdateProduceRequest.php` | Existing catalog validation remained suitable for this scope. |
| `app/Http/Requests/ApiFormRequest.php` | Existing common JSON validation behavior was retained. |
| `app/Http/Requests/Auth/LoginRequest.php` | Existing credential validation was retained; inactive-account enforcement was added outside this request. |
| `app/Http/Requests/Auth/RegisterRequest.php` | Current PoC deliberately retains public role selection; the temporary production-safe restriction was reverted by design. |
| `app/Models/Category.php` | Existing catalog model remained the canonical catalog identity and required no endpoint-driven redesign. |
| `app/Models/Produce.php` | Existing catalog model remained the canonical catalog identity and required no endpoint-driven redesign. |
| `app/Providers/AppServiceProvider.php` | Existing application provider required no feature-level change for the completed endpoint work. |
| `config/app.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/auth.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/cache.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/cors.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/database.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/filesystems.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/jwt.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/logging.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/mail.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/queue.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `config/session.php` | Existing Laravel/JWT/infrastructure configuration was retained; no endpoint-specific change was required. |
| `database/migrations/0001_01_01_000000_create_users_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/0001_01_01_000001_create_cache_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/0001_01_01_000002_create_jobs_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_09_160000_add_role_to_users_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_10_100000_create_categories_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_10_100001_create_produce_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_10_100002_create_farmers_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_10_110000_add_state_to_farmers_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_10_120000_create_listings_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_10_130000_add_image_to_produce_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_10_140000_store_produce_image_as_base64.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_06_10_150000_create_orders_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_07_30_150000_create_disputes_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `database/migrations/2026_07_30_150001_create_dispute_messages_table.php` | Original baseline migration retained unchanged for historical schema continuity; later requirements were added through additive migrations. |
| `docs/API.md` | Existing API document retained; final contract refresh remains part of integration/hardening. |
| `routes/console.php` | Non-API route file; no change required for the missing REST endpoint task. |
| `routes/web.php` | Non-API route file; no change required for the missing REST endpoint task. |
| `tests/Feature/Admin/AdminCatalogTest.php` | Existing catalog regression coverage remained valid and was retained. |
| `tests/Feature/Admin/ApiValidationTest.php` | Existing common API validation regression coverage remained valid. |
| `tests/Feature/Admin/BuyerTest.php` | Original buyer detail coverage retained alongside new buyer list/status tests. |
| `tests/Feature/Admin/DashboardTest.php` | Existing basic dashboard test retained; richer dashboard tests are planned with the next endpoint slice. |
| `tests/Feature/Admin/FarmerShowTest.php` | Original farmer detail regression coverage retained alongside new richer farmer profile/activity tests. |
| `tests/Feature/Admin/UserTest.php` | Existing admin user coverage retained while buyer-specific tests were added separately. |
| `tests/Feature/Auth/AuthenticationTest.php` | Existing authentication regression coverage retained; inactive-account behavior received separate tests. |
| `tests/Feature/ExampleTest.php` | Framework/example test retained; unrelated to endpoint parity work. |

## 5. Files removed

**None of the 92 relevant baseline files were removed.** The work has been additive/extension-oriented rather than a rewrite.

## 6. Endpoint capability: initial vs current

| Area | Initially received | Current position |
|---|---|---|
| **Public listings** | Basic browse/detail. | Search/filter/sort/pagination, unit, richer metadata, publication/availability rules, multiple images. |
| **Admin listings** | CRUD foundation plus farmer listing routes. | Global and farmer-scoped list/query endpoints, rich listing management, publication eligibility, image upload/reorder/delete. |
| **Orders** | Single listing per order. | Parent order + multiple order items, multi-farmer attribution, buyer/admin/farmer histories, checkout rules and timeline. |
| **Payments** | Not implemented. | Initialize/verify endpoints, payment state/metadata, Paystack service and mocked tests; real provider E2E still credential-blocked. |
| **Buyers** | Basic admin list/detail. | Search/filter/pagination, richer buyer resource, activate/deactivate and inactive-session enforcement. |
| **Farmers** | Basic CRUD with limited profile. | Richer profile, list filters/pagination, status/verification endpoints, publication eligibility and activity summary. |
| **Disputes** | Basic buyer/admin thread and replies. | Search/filter/pagination, safe item/farmer attribution, unread/read state, workflow transitions/audit data and private attachments. |
| **Dashboard** | Basic totals only. | Existing endpoint retained; richer summary cards/activity/action queue/revenue series are the next missing-endpoint block. |
| **Notifications** | No endpoint. | Still remaining; database notifications plus list/unread/mark-read operations planned. |

## 7. Why the new files were necessary — by capability

- **Order architecture:** `OrderItem`, order-index request/tests, order status events/resources and additive migrations were required because one buyer order now needs to contain multiple items, potentially from multiple farmers, while preserving historical snapshots.
- **Payment endpoints:** `PaymentController`, `PaystackService`, payment request/status enum and payment migrations were required because the initial backend did not contain checkout/payment lifecycle support.
- **Buyer and farmer management:** new index/status requests, richer resources and tests were required to expose the search/filter/pagination/status operations shown by the admin UI.
- **Listing parity:** publication state, rich listing fields, unit, marketplace filters, listing images and eligibility tests were required because the initial listing model was too small for the frontend contract.
- **Order timeline:** the status-event model/resource/migration/test were required to preserve an append-only, actor-aware history rather than deriving history only from the current status.
- **Disputes:** read-state, attachment models/service/migrations and workflow tests were required because the initial dispute thread had no safe multi-farmer attribution, unread/read projection, transition audit data or evidence files.

## 8. Missing-endpoint work completed so far

- [x] Public listing query/pagination/filter/sort endpoints enhanced
- [x] Admin global listing query endpoint enhanced
- [x] Farmer-scoped listing endpoint enhanced
- [x] Buyer order history query endpoint enhanced
- [x] Admin global order query endpoint enhanced
- [x] Farmer-scoped order history endpoint added/enhanced
- [x] Multi-item checkout/order creation behavior implemented
- [x] Payment initialize/verify endpoints implemented
- [x] Admin buyer search/filter/pagination and status action implemented
- [x] Admin farmer search/filter/pagination implemented
- [x] Farmer status and verification actions implemented
- [x] Farmer profile/activity summary implemented
- [x] Listing publication eligibility implemented
- [x] Multiple listing image upload/reorder/delete endpoints implemented
- [x] Order timeline/status event projection implemented
- [x] Buyer/admin dispute list search/filter/pagination implemented
- [x] Dispute item/farmer attribution made safe for multi-farmer orders
- [x] Dispute read/unread endpoints/state implemented
- [x] Dispute workflow transition/audit fields implemented
- [x] Private dispute message attachment upload/download implemented

## 9. Remaining missing-endpoint work

- [ ] Enhance `GET /api/v1/admin/dashboard` with the full Figma summary metrics
- [ ] Add/complete recent activity projection for the admin dashboard
- [ ] Add the admin order action queue projection
- [ ] Add dashboard revenue time-series output using paid order-item data unless payout semantics are separately confirmed
- [ ] Add notification list/unread-count/mark-read/mark-all-read endpoints
- [ ] Final API contract/documentation refresh and realistic integration seed data
- [ ] Production hardening pass: query/index review, concurrency/oversell coverage, archive/delete safety and staging migration rehearsal
- [ ] Real Paystack provider E2E when organization credentials become available

## 10. Short team-facing summary

> I inherited a working Laravel API foundation rather than an empty backend. My task has therefore been to **fill the missing endpoint and response-contract gaps without rewriting the existing system**. So far, I have kept the original core structure, enhanced the existing buyer/farmer/listing/order/dispute endpoints, and added the new supporting files needed for multi-item orders, payments, publication workflows, timelines, read state and attachments. The main functional endpoint work still outstanding is the richer admin dashboard and notifications, after which the backend moves primarily into integration and hardening rather than new core feature development.

## Appendix A — Complete current relevant file inventory with classification

| File | Classification |
|---|---|
| `app/Enums/DeliveryMethod.php` | **NEW** |
| `app/Enums/DisputeStatus.php` | **ENHANCED** |
| `app/Enums/FarmerStatus.php` | **RETAINED** |
| `app/Enums/FarmerVerificationStatus.php` | **NEW** |
| `app/Enums/ListingPublicationStatus.php` | **NEW** |
| `app/Enums/ListingStatus.php` | **RETAINED** |
| `app/Enums/OrderStatus.php` | **RETAINED** |
| `app/Enums/PaymentStatus.php` | **NEW** |
| `app/Enums/UserRole.php` | **RETAINED** |
| `app/Enums/UserStatus.php` | **NEW** |
| `app/Http/Controllers/Api/V1/Admin/BuyerController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/Admin/CategoryController.php` | **RETAINED** |
| `app/Http/Controllers/Api/V1/Admin/DashboardController.php` | **RETAINED** |
| `app/Http/Controllers/Api/V1/Admin/DisputeController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/Admin/FarmerController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/Admin/ListingController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/Admin/ListingImageController.php` | **NEW** |
| `app/Http/Controllers/Api/V1/Admin/OrderController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/Admin/ProduceController.php` | **RETAINED** |
| `app/Http/Controllers/Api/V1/Admin/UserController.php` | **RETAINED** |
| `app/Http/Controllers/Api/V1/AuthController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/DisputeController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/ListingController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/OrderController.php` | **ENHANCED** |
| `app/Http/Controllers/Api/V1/PaymentController.php` | **NEW** |
| `app/Http/Controllers/Controller.php` | **RETAINED** |
| `app/Http/Middleware/EnsureUserIsActive.php` | **NEW** |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | **RETAINED** |
| `app/Http/Requests/Admin/IndexBuyerRequest.php` | **NEW** |
| `app/Http/Requests/Admin/IndexFarmerListingRequest.php` | **NEW** |
| `app/Http/Requests/Admin/IndexFarmerOrderRequest.php` | **NEW** |
| `app/Http/Requests/Admin/IndexFarmerRequest.php` | **NEW** |
| `app/Http/Requests/Admin/IndexListingRequest.php` | **NEW** |
| `app/Http/Requests/Admin/IndexOrderRequest.php` | **NEW** |
| `app/Http/Requests/Admin/ReorderListingImagesRequest.php` | **NEW** |
| `app/Http/Requests/Admin/StoreCategoryRequest.php` | **RETAINED** |
| `app/Http/Requests/Admin/StoreFarmerRequest.php` | **ENHANCED** |
| `app/Http/Requests/Admin/StoreListingImagesRequest.php` | **NEW** |
| `app/Http/Requests/Admin/StoreListingRequest.php` | **ENHANCED** |
| `app/Http/Requests/Admin/StoreProduceRequest.php` | **RETAINED** |
| `app/Http/Requests/Admin/UpdateBuyerStatusRequest.php` | **NEW** |
| `app/Http/Requests/Admin/UpdateCategoryRequest.php` | **RETAINED** |
| `app/Http/Requests/Admin/UpdateDisputeStatusRequest.php` | **ENHANCED** |
| `app/Http/Requests/Admin/UpdateFarmerRequest.php` | **ENHANCED** |
| `app/Http/Requests/Admin/UpdateFarmerStatusRequest.php` | **NEW** |
| `app/Http/Requests/Admin/UpdateFarmerVerificationRequest.php` | **NEW** |
| `app/Http/Requests/Admin/UpdateListingRequest.php` | **ENHANCED** |
| `app/Http/Requests/Admin/UpdateOrderStatusRequest.php` | **RETAINED** |
| `app/Http/Requests/Admin/UpdateProduceRequest.php` | **RETAINED** |
| `app/Http/Requests/ApiFormRequest.php` | **RETAINED** |
| `app/Http/Requests/Auth/LoginRequest.php` | **RETAINED** |
| `app/Http/Requests/Auth/RegisterRequest.php` | **RETAINED** |
| `app/Http/Requests/Dispute/IndexDisputeRequest.php` | **NEW** |
| `app/Http/Requests/Dispute/StoreDisputeMessageRequest.php` | **ENHANCED** |
| `app/Http/Requests/Dispute/StoreDisputeRequest.php` | **ENHANCED** |
| `app/Http/Requests/Listing/IndexListingRequest.php` | **ENHANCED** |
| `app/Http/Requests/Order/IndexOrderRequest.php` | **NEW** |
| `app/Http/Requests/Order/StoreOrderRequest.php` | **ENHANCED** |
| `app/Http/Requests/Payment/InitializePaymentRequest.php` | **NEW** |
| `app/Http/Resources/BuyerResource.php` | **NEW** |
| `app/Http/Resources/DisputeMessageResource.php` | **ENHANCED** |
| `app/Http/Resources/DisputeResource.php` | **ENHANCED** |
| `app/Http/Resources/FarmerOrderSummaryResource.php` | **NEW** |
| `app/Http/Resources/FarmerResource.php` | **NEW** |
| `app/Http/Resources/ListingImageResource.php` | **NEW** |
| `app/Http/Resources/ListingResource.php` | **ENHANCED** |
| `app/Http/Resources/OrderItemResource.php` | **NEW** |
| `app/Http/Resources/OrderResource.php` | **ENHANCED** |
| `app/Http/Resources/OrderStatusEventResource.php` | **NEW** |
| `app/Models/Category.php` | **RETAINED** |
| `app/Models/Dispute.php` | **ENHANCED** |
| `app/Models/DisputeMessage.php` | **ENHANCED** |
| `app/Models/DisputeMessageAttachment.php` | **NEW** |
| `app/Models/DisputeRead.php` | **NEW** |
| `app/Models/Farmer.php` | **ENHANCED** |
| `app/Models/Listing.php` | **ENHANCED** |
| `app/Models/ListingImage.php` | **NEW** |
| `app/Models/Order.php` | **ENHANCED** |
| `app/Models/OrderItem.php` | **NEW** |
| `app/Models/OrderStatusEvent.php` | **NEW** |
| `app/Models/Produce.php` | **RETAINED** |
| `app/Models/User.php` | **ENHANCED** |
| `app/Providers/AppServiceProvider.php` | **RETAINED** |
| `app/Services/DisputeAttachmentService.php` | **NEW** |
| `app/Services/PaystackService.php` | **NEW** |
| `config/app.php` | **RETAINED** |
| `config/auth.php` | **RETAINED** |
| `config/cache.php` | **RETAINED** |
| `config/cors.php` | **RETAINED** |
| `config/database.php` | **RETAINED** |
| `config/filesystems.php` | **RETAINED** |
| `config/jwt.php` | **RETAINED** |
| `config/logging.php` | **RETAINED** |
| `config/mail.php` | **RETAINED** |
| `config/queue.php` | **RETAINED** |
| `config/services.php` | **ENHANCED** |
| `config/session.php` | **RETAINED** |
| `database/migrations/0001_01_01_000000_create_users_table.php` | **RETAINED** |
| `database/migrations/0001_01_01_000001_create_cache_table.php` | **RETAINED** |
| `database/migrations/0001_01_01_000002_create_jobs_table.php` | **RETAINED** |
| `database/migrations/2026_06_09_160000_add_role_to_users_table.php` | **RETAINED** |
| `database/migrations/2026_06_10_100000_create_categories_table.php` | **RETAINED** |
| `database/migrations/2026_06_10_100001_create_produce_table.php` | **RETAINED** |
| `database/migrations/2026_06_10_100002_create_farmers_table.php` | **RETAINED** |
| `database/migrations/2026_06_10_110000_add_state_to_farmers_table.php` | **RETAINED** |
| `database/migrations/2026_06_10_120000_create_listings_table.php` | **RETAINED** |
| `database/migrations/2026_06_10_130000_add_image_to_produce_table.php` | **RETAINED** |
| `database/migrations/2026_06_10_140000_store_produce_image_as_base64.php` | **RETAINED** |
| `database/migrations/2026_06_10_150000_create_orders_table.php` | **RETAINED** |
| `database/migrations/2026_07_30_150000_create_disputes_table.php` | **RETAINED** |
| `database/migrations/2026_07_30_150001_create_dispute_messages_table.php` | **RETAINED** |
| `database/migrations/2026_08_11_141246_add_order_architecture.php` | **NEW** |
| `database/migrations/2026_08_11_151125_add_payment_metadata_to_orders_table.php` | **NEW** |
| `database/migrations/2026_08_11_152738_add_paystack_checkout_fields_to_orders_table.php` | **NEW** |
| `database/migrations/2026_08_13_090841_add_unit_to_listings_table.php` | **NEW** |
| `database/migrations/2026_08_13_092212_add_profile_fields_to_farmers_table.php` | **NEW** |
| `database/migrations/2026_08_13_102016_add_profile_and_status_fields_to_users_table.php` | **NEW** |
| `database/migrations/2026_08_13_111824_create_order_status_events_table.php` | **NEW** |
| `database/migrations/2026_08_13_114107_add_rich_fields_to_listings_table.php` | **NEW** |
| `database/migrations/2026_08_13_132529_create_listing_images_table.php` | **NEW** |
| `database/migrations/2026_08_13_142506_add_workflow_fields_to_disputes_table.php` | **NEW** |
| `database/migrations/2026_08_13_142507_create_dispute_reads_table.php` | **NEW** |
| `database/migrations/2026_08_13_150212_create_dispute_message_attachments_table.php` | **NEW** |
| `docs/API.md` | **RETAINED** |
| `routes/api.php` | **ENHANCED** |
| `routes/console.php` | **RETAINED** |
| `routes/web.php` | **RETAINED** |
| `tests/Feature/Admin/AdminCatalogTest.php` | **RETAINED** |
| `tests/Feature/Admin/ApiValidationTest.php` | **RETAINED** |
| `tests/Feature/Admin/BuyerIndexTest.php` | **NEW** |
| `tests/Feature/Admin/BuyerTest.php` | **RETAINED** |
| `tests/Feature/Admin/DashboardTest.php` | **RETAINED** |
| `tests/Feature/Admin/FarmerActivitySummaryTest.php` | **NEW** |
| `tests/Feature/Admin/FarmerIndexTest.php` | **NEW** |
| `tests/Feature/Admin/FarmerListingIndexTest.php` | **NEW** |
| `tests/Feature/Admin/FarmerOrderIndexTest.php` | **NEW** |
| `tests/Feature/Admin/FarmerProfileTest.php` | **NEW** |
| `tests/Feature/Admin/FarmerShowTest.php` | **RETAINED** |
| `tests/Feature/Admin/FarmerStateTest.php` | **NEW** |
| `tests/Feature/Admin/ListingIndexTest.php` | **NEW** |
| `tests/Feature/Admin/ListingTest.php` | **ENHANCED** |
| `tests/Feature/Admin/OrderIndexTest.php` | **NEW** |
| `tests/Feature/Admin/UserTest.php` | **RETAINED** |
| `tests/Feature/Auth/AuthenticationTest.php` | **RETAINED** |
| `tests/Feature/Auth/InactiveAccountTest.php` | **NEW** |
| `tests/Feature/Dispute/DisputeAttachmentTest.php` | **NEW** |
| `tests/Feature/Dispute/DisputeTest.php` | **ENHANCED** |
| `tests/Feature/Dispute/DisputeWorkflowTest.php` | **NEW** |
| `tests/Feature/ExampleTest.php` | **RETAINED** |
| `tests/Feature/Listing/ListingImageTest.php` | **NEW** |
| `tests/Feature/Listing/ListingPublicationEligibilityTest.php` | **NEW** |
| `tests/Feature/Listing/ListingRichnessTest.php` | **NEW** |
| `tests/Feature/Listing/ListingUnitTest.php` | **NEW** |
| `tests/Feature/Listing/MarketplaceFilterTest.php` | **NEW** |
| `tests/Feature/Listing/UserListingTest.php` | **ENHANCED** |
| `tests/Feature/Order/ListingCheckoutRulesTest.php` | **NEW** |
| `tests/Feature/Order/OrderIndexTest.php` | **NEW** |
| `tests/Feature/Order/OrderItemTest.php` | **NEW** |
| `tests/Feature/Order/OrderTest.php` | **ENHANCED** |
| `tests/Feature/Order/OrderTimelineTest.php` | **NEW** |
| `tests/Feature/Order/PaymentStateTest.php` | **NEW** |
| `tests/Feature/Order/PaymentTest.php` | **NEW** |
