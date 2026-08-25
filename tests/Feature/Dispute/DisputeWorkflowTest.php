<?php

namespace Tests\Feature\Dispute;

use App\Enums\DisputeStatus;
use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_order_item_must_belong_to_selected_order(): void
    {
        $buyer =
            $this->createBuyer();

        [
            $firstOrder,
        ] =
            $this->createOrder(
                $buyer,
                'ORD-DISP-100001',
                'Rice'
            );

        [
            ,
            $secondItem,
        ] =
            $this->createOrder(
                $buyer,
                'ORD-DISP-100002',
                'Maize'
            );

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                '/api/v1/disputes',
                [
                    'order_id' =>
                        $firstOrder->id,

                    /*
                     * Belongs to the second order.
                     */
                    'order_item_id' =>
                        $secondItem->id,

                    'subject' =>
                        'Wrong product',

                    'message' =>
                        'The selected item is not from this order.',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'order_item_id',
            ])
            ->assertJsonFragment([
                'The selected order item does not belong to this order.',
            ]);
    }

    public function test_order_wide_dispute_does_not_infer_a_farmer_from_multi_farmer_order(): void
    {
        $buyer =
            $this->createBuyer();

        $farmerA =
            $this->createFarmer(
                'Ibrahim Musa'
            );

        $farmerB =
            $this->createFarmer(
                'Ada Okoro'
            );

        [
            $listingA,
            $produceA,
        ] =
            $this->createListing(
                $farmerA,
                'Rice'
            );

        [
            $listingB,
            $produceB,
        ] =
            $this->createListing(
                $farmerB,
                'Tomato'
            );

        $order =
            Order::create([
                'user_id' =>
                    $buyer->id,

                /*
                 * Legacy compatibility points at A,
                 * but MUST NOT define dispute farmer.
                 */
                'listing_id' =>
                    $listingA->id,

                'quantity' =>
                    1,

                'order_number' =>
                    'ORD-MULTI-DISP-001',

                'subtotal' =>
                    '70000.00',

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    '70000.00',

                'status' =>
                    OrderStatus::Delivered,

                'payment_status' =>
                    PaymentStatus::Paid,

                'placed_at' =>
                    now(),
            ]);

        $this->createOrderItem(
            $order,
            $listingA,
            $farmerA,
            $produceA,
            '45000.00'
        );

        $this->createOrderItem(
            $order,
            $listingB,
            $farmerB,
            $produceB,
            '25000.00'
        );

        $create =
            $this
                ->withToken(
                    auth('api')->login(
                        $buyer
                    )
                )
                ->postJson(
                    '/api/v1/disputes',
                    [
                        'order_id' =>
                            $order->id,

                        /*
                         * Intentionally no order_item_id.
                         */
                        'subject' =>
                            'Delivery-wide issue',

                        'message' =>
                            'The complete delivery arrived late.',
                    ]
                );

        $create
            ->assertCreated()
            ->assertJsonPath(
                'data.order_item_id',
                null
            )
            ->assertJsonPath(
                'data.affected_item',
                null
            )
            ->assertJsonPath(
                'data.affected_farmer',
                null
            )
            ->assertJsonCount(
                2,
                'data.order.items'
            );
    }

    public function test_read_state_is_independent_for_buyer_and_admin(): void
    {
        $buyer =
            $this->createBuyer();

        $admin =
            $this->createAdmin();

        [
            $order,
            $item,
        ] =
            $this->createOrder(
                $buyer,
                'ORD-READ-000001',
                'Rice'
            );

        $create =
            $this
                ->withToken(
                    auth('api')->login(
                        $buyer
                    )
                )
                ->postJson(
                    '/api/v1/disputes',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'subject' =>
                            'Unread test',

                        'message' =>
                            'Initial buyer message.',
                    ]
                );

        $create
            ->assertCreated()
            ->assertJsonPath(
                'data.unread_count',
                0
            );

        $disputeId =
            $create->json(
                'data.id'
            );

        /*
         * Buyer wrote the only message, so buyer has
         * nothing unread.
         */
        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->getJson(
                '/api/v1/disputes?unread=1'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );

        /*
         * Admin has not read the buyer message.
         */
        $this
            ->withToken(
                auth('api')->login(
                    $admin
                )
            )
            ->getJson(
                '/api/v1/admin/disputes?unread=1'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.unread_count',
                1
            );

        /*
         * Admin marks only their own read state.
         */
        $this
            ->withToken(
                auth('api')->login(
                    $admin
                )
            )
            ->patchJson(
                "/api/v1/admin/disputes/{$disputeId}/read"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                0
            );

        /*
         * Admin replies. Their own reply is not unread
         * to themselves, but it becomes unread to buyer.
         */
        $this
            ->withToken(
                auth('api')->login(
                    $admin
                )
            )
            ->postJson(
                "/api/v1/admin/disputes/{$disputeId}/messages",
                [
                    'message' =>
                        'Admin response.',
                ]
            )
            ->assertCreated();

        $this
            ->withToken(
                auth('api')->login(
                    $admin
                )
            )
            ->getJson(
                '/api/v1/admin/disputes?unread=1'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            );

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->getJson(
                '/api/v1/disputes?unread=1'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.unread_count',
                1
            );

        /*
         * Buyer read does not alter admin state.
         */
        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->patchJson(
                "/api/v1/disputes/{$disputeId}/read"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.unread_count',
                0
            );
    }

    public function test_buyer_dispute_list_supports_search_status_and_pagination(): void
    {
        $buyer =
            $this->createBuyer();

        [
            $orderA,
            $itemA,
        ] =
            $this->createOrder(
                $buyer,
                'ORD-SEARCH-000001',
                'Rice'
            );

        [
            $orderB,
            $itemB,
        ] =
            $this->createOrder(
                $buyer,
                'ORD-SEARCH-000002',
                'Maize'
            );

        $token =
            auth('api')->login(
                $buyer
            );

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/disputes',
                [
                    'order_id' =>
                        $orderA->id,

                    'order_item_id' =>
                        $itemA->id,

                    'subject' =>
                        'Delivery problem one',

                    'message' =>
                        'First issue.',
                ]
            )
            ->assertCreated();

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/disputes',
                [
                    'order_id' =>
                        $orderB->id,

                    'order_item_id' =>
                        $itemB->id,

                    'subject' =>
                        'Delivery problem two',

                    'message' =>
                        'Second issue.',
                ]
            )
            ->assertCreated();

        $response =
            $this
                ->withToken($token)
                ->getJson(
                    '/api/v1/disputes'
                    .'?search=Delivery'
                    .'&status=under_review'
                    .'&sort=created_at'
                    .'&order=desc'
                    .'&per_page=1'
                );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'meta.total',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                1
            );

        $next =
            $response->json(
                'links.next'
            );

        $this->assertNotNull(
            $next
        );

        $this->assertStringContainsString(
            'search=Delivery',
            $next
        );

        $this->assertStringContainsString(
            'status=under_review',
            $next
        );

        $this->assertStringContainsString(
            'per_page=1',
            $next
        );
    }

    public function test_admin_dispute_list_supports_search_status_pagination_and_last_message(): void
    {
        $buyer =
            $this->createBuyer();

        $admin =
            $this->createAdmin();

        [
            $order,
            $item,
        ] =
            $this->createOrder(
                $buyer,
                'ORD-ADMIN-SEARCH-001',
                'Rice'
            );

        $create =
            $this
                ->withToken(
                    auth('api')->login(
                        $buyer
                    )
                )
                ->postJson(
                    '/api/v1/disputes',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'subject' =>
                            'Rice delivery damaged',

                        'message' =>
                            'Several bags were damaged.',
                    ]
                );

        $create->assertCreated();

        $this
            ->withToken(
                auth('api')->login(
                    $admin
                )
            )
            ->getJson(
                '/api/v1/admin/disputes'
                .'?search=ORD-ADMIN-SEARCH-001'
                .'&status=under_review'
                .'&per_page=1'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.order.order_number',
                'ORD-ADMIN-SEARCH-001'
            )
            ->assertJsonPath(
                'data.0.last_message.body',
                'Several bags were damaged.'
            )
            ->assertJsonPath(
                'data.0.unread_count',
                1
            );
    }

    public function test_dispute_transition_records_actor_timestamps_and_note(): void
    {
        $buyer =
            $this->createBuyer();

        $admin =
            $this->createAdmin();

        [
            $order,
            $item,
        ] =
            $this->createOrder(
                $buyer,
                'ORD-TRANSITION-001',
                'Rice'
            );

        $create =
            $this
                ->withToken(
                    auth('api')->login(
                        $buyer
                    )
                )
                ->postJson(
                    '/api/v1/disputes',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'subject' =>
                            'Transition test',

                        'message' =>
                            'Please review.',
                    ]
                );

        $disputeId =
            $create->json(
                'data.id'
            );

        $resolved =
            $this
                ->withToken(
                    auth('api')->login(
                        $admin
                    )
                )
                ->patchJson(
                    "/api/v1/admin/disputes/{$disputeId}",
                    [
                        'status' =>
                            DisputeStatus::Resolved
                                ->value,

                        'note' =>
                            'Replacement was accepted.',
                    ]
                );

        $resolved
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'resolved'
            )
            ->assertJsonPath(
                'data.resolved_by.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.resolution_note',
                'Replacement was accepted.'
            );

        $this->assertNotNull(
            $resolved->json(
                'data.resolved_at'
            )
        );

        $closed =
            $this
                ->withToken(
                    auth('api')->login(
                        $admin
                    )
                )
                ->patchJson(
                    "/api/v1/admin/disputes/{$disputeId}",
                    [
                        'status' =>
                            DisputeStatus::Closed
                                ->value,
                    ]
                );

        $closed
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'closed'
            )
            ->assertJsonPath(
                'data.closed_by.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.resolution_note',
                'Replacement was accepted.'
            );

        $this->assertNotNull(
            $closed->json(
                'data.closed_at'
            )
        );

        /*
         * Closed is terminal.
         */
        $this
            ->withToken(
                auth('api')->login(
                    $admin
                )
            )
            ->patchJson(
                "/api/v1/admin/disputes/{$disputeId}",
                [
                    'status' =>
                        DisputeStatus::Resolved
                            ->value,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    private function createBuyer(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::User,
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);
    }

    private function createFarmer(
        string $name
    ): Farmer {
        return Farmer::create([
            'name' =>
                $name,

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'verified_at' =>
                now(),

            'phone_number' =>
                fake()
                    ->unique()
                    ->numerify(
                        '080########'
                    ),
        ]);
    }

    private function createListing(
        Farmer $farmer,
        string $produceName
    ): array {
        $category =
            Category::firstOrCreate([
                'name' =>
                    'General Produce',
            ]);

        $produce =
            Produce::create([
                'category_id' =>
                    $category->id,

                'name' =>
                    $produceName,

                'image' =>
                    base64_encode(
                        strtolower(
                            $produceName
                        )
                    ),

                'image_mime' =>
                    'image/jpeg',
            ]);

        $listing =
            Listing::create([
                'farmer_id' =>
                    $farmer->id,

                'produce_id' =>
                    $produce->id,

                'price' =>
                    '45000.00',

                'unit' =>
                    'bag',

                'stock' =>
                    100,

                'minimum_order_quantity' =>
                    1,

                'publication_status' =>
                    ListingPublicationStatus::Live,
            ]);

        return [
            $listing,
            $produce,
        ];
    }

    private function createOrder(
        User $buyer,
        string $orderNumber,
        string $produceName
    ): array {
        $farmer =
            $this->createFarmer(
                'Farmer '.$produceName
            );

        [
            $listing,
            $produce,
        ] =
            $this->createListing(
                $farmer,
                $produceName
            );

        $order =
            Order::create([
                'user_id' =>
                    $buyer->id,

                'listing_id' =>
                    $listing->id,

                'quantity' =>
                    1,

                'order_number' =>
                    $orderNumber,

                'subtotal' =>
                    '45000.00',

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    '45000.00',

                'status' =>
                    OrderStatus::Delivered,

                'payment_status' =>
                    PaymentStatus::Paid,

                'placed_at' =>
                    now(),
            ]);

        $item =
            $this->createOrderItem(
                $order,
                $listing,
                $farmer,
                $produce,
                '45000.00'
            );

        return [
            $order,
            $item,
            $farmer,
        ];
    }

    private function createOrderItem(
        Order $order,
        Listing $listing,
        Farmer $farmer,
        Produce $produce,
        string $lineTotal
    ): OrderItem {
        return OrderItem::create([
            'order_id' =>
                $order->id,

            'listing_id' =>
                $listing->id,

            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'produce_name' =>
                $produce->name,

            'category_name' =>
                $produce
                    ->category
                    ->name,

            'unit' =>
                $listing->unit,

            'quantity' =>
                1,

            'unit_price' =>
                $lineTotal,

            'discount_amount' =>
                '0.00',

            'line_total' =>
                $lineTotal,
        ]);
    }
}