<?php

namespace Tests\Feature\Order;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_records_initial_timeline_event(): void
    {
        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $listing =
            $this->createListing();

        $token = auth('api')->login(
            $buyer
        );

        $response = $this
            ->withToken($token)
            ->postJson(
                '/api/v1/orders',
                [
                    'items' => [
                        [
                            'listing_id' =>
                                $listing->id,

                            'quantity' =>
                                2,
                        ],
                    ],

                    'delivery_method' =>
                        'standard',

                    'delivery_name' =>
                        'Ada Okoro',

                    'delivery_phone' =>
                        '08012345678',

                    'delivery_state' =>
                        'Lagos',

                    'delivery_lga' =>
                        'Ikeja',

                    'delivery_address' =>
                        '14 Market Road',

                    'delivery_notes' =>
                        'Call before delivery.',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                OrderStatus::New->value
            );

        $orderId =
            $response->json(
                'data.id'
            );

        $this->assertDatabaseHas(
            'order_status_events',
            [
                'order_id' =>
                    $orderId,

                'from_status' =>
                    null,

                'to_status' =>
                    OrderStatus::New
                        ->value,

                'changed_by_user_id' =>
                    $buyer->id,
            ]
        );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/orders/{$orderId}"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.timeline'
            )
            ->assertJsonPath(
                'data.timeline.0.from_status',
                null
            )
            ->assertJsonPath(
                'data.timeline.0.to_status',
                'new'
            )
            ->assertJsonPath(
                'data.timeline.0.changed_by.id',
                $buyer->id
            )
            ->assertJsonPath(
                'data.timeline.0.changed_by.role',
                UserRole::User->value
            );
    }

    public function test_admin_status_changes_append_to_timeline(): void
    {
        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $listing =
            $this->createListing();

        /*
         * No authenticated actor exists yet, so
         * the initial event is system/baseline.
         */
        $order = $this->createOrder(
            $buyer,
            $listing
        );

        $admin = User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);

        $token = auth('api')->login(
            $admin
        );

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/orders/{$order->id}",
                [
                    'status' =>
                        OrderStatus::InTransit
                            ->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'in_transit'
            );

        $order->refresh();

        $this->assertSame(
            OrderStatus::InTransit,
            $order->status
        );

        $this->assertNotNull(
            $order->out_for_delivery_at
        );

        $this->assertDatabaseHas(
            'order_status_events',
            [
                'order_id' =>
                    $order->id,

                'from_status' =>
                    OrderStatus::New
                        ->value,

                'to_status' =>
                    OrderStatus::InTransit
                        ->value,

                'changed_by_user_id' =>
                    $admin->id,
            ]
        );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/orders/{$order->id}"
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.timeline'
            )
            ->assertJsonPath(
                'data.timeline.0.to_status',
                'new'
            )
            ->assertJsonPath(
                'data.timeline.1.from_status',
                'new'
            )
            ->assertJsonPath(
                'data.timeline.1.to_status',
                'in_transit'
            )
            ->assertJsonPath(
                'data.timeline.1.changed_by.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.timeline.1.changed_by.role',
                UserRole::Admin->value
            );
    }

    public function test_repeating_same_status_does_not_create_duplicate_timeline_event(): void
    {
        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $listing =
            $this->createListing();

        $order = $this->createOrder(
            $buyer,
            $listing
        );

        $admin = User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);

        $token = auth('api')->login(
            $admin
        );

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/orders/{$order->id}",
                [
                    'status' =>
                        OrderStatus::InTransit
                            ->value,
                ]
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'order_status_events',
            2
        );

        /*
         * Sending the already-current status should
         * not fabricate another historical event.
         */
        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/orders/{$order->id}",
                [
                    'status' =>
                        OrderStatus::InTransit
                            ->value,
                ]
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'order_status_events',
            2
        );

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/orders/{$order->id}",
                [
                    'status' =>
                        OrderStatus::Delivered
                            ->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'delivered'
            );

        $this->assertDatabaseCount(
            'order_status_events',
            3
        );

        $this->assertDatabaseHas(
            'order_status_events',
            [
                'order_id' =>
                    $order->id,

                'from_status' =>
                    OrderStatus::InTransit
                        ->value,

                'to_status' =>
                    OrderStatus::Delivered
                        ->value,

                'changed_by_user_id' =>
                    $admin->id,
            ]
        );
    }

    private function createListing(): Listing
    {
        $farmer = Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'phone_number' =>
                '08012345678',
        ]);

        $category = Category::create([
            'name' =>
                'Grains',
        ]);

        $produce = Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                'Rice',

            'image' =>
                base64_encode(
                    'rice'
                ),

            'image_mime' =>
                'image/jpeg',
        ]);

        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                45000,

            'unit' =>
                'bag',

            'stock' =>
                100,

            'status' =>
                ListingStatus::Active,
        ]);
    }

    private function createOrder(
        User $buyer,
        Listing $listing
    ): Order {
        return Order::create([
            'user_id' =>
                $buyer->id,

            'listing_id' =>
                $listing->id,

            'quantity' =>
                1,

            'order_number' =>
                'ORD-TIMELINE-'
                .fake()
                    ->unique()
                    ->numerify(
                        '######'
                    ),

            'subtotal' =>
                '45000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '45000.00',

            'status' =>
                OrderStatus::New,

            'payment_status' =>
                PaymentStatus::Pending,

            'placed_at' =>
                now(),
        ]);
    }
}