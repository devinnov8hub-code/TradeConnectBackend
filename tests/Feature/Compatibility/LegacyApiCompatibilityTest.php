<?php

namespace Tests\Feature\Compatibility;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyApiCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_single_item_order_payload_is_still_accepted(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $listing = $this->createLegacyListing(
            farmer: $this->createLegacyFarmer(),
            produceName: 'Rice',
            price: '45000.00',
            stock: 100,
        );

        $token = auth('api')->login($buyer);

        $this
            ->withToken($token)
            ->postJson('/api/v1/orders', [
                'listing_id' => $listing->id,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.listing_id',
                $listing->id
            )
            ->assertJsonPath(
                'data.quantity',
                2
            )
            ->assertJsonPath(
                'data.total',
                '90000.00'
            )
            ->assertJsonPath(
                'data.status',
                OrderStatus::New->value
            )
            ->assertJsonPath(
                'data.produce.name',
                'Rice'
            )
            ->assertJsonPath(
                'data.produce.category.name',
                'Grains'
            );

        $this->assertDatabaseHas(
            'listings',
            [
                'id' => $listing->id,
                'stock' => 98,
            ]
        );

        $this->assertDatabaseHas(
            'order_items',
            [
                'listing_id' => $listing->id,
                'quantity' => 2,
                'line_total' => '90000.00',
            ]
        );
    }

    public function test_legacy_admin_listing_payload_without_unit_is_still_accepted(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        /*
         * Deliberately create the farmer exactly as the old
         * tests/client did. The old contract had no separate
         * verification state.
         */
        $farmer = $this->createLegacyFarmer();

        $category = Category::create([
            'name' => 'Grains',
        ]);

        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => 'Rice',
            'image' => base64_encode('rice'),
            'image_mime' => 'image/jpeg',
        ]);

        $token = auth('api')->login($admin);

        $this
            ->withToken($token)
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                [
                    'produce_id' => $produce->id,
                    'price' => 45000,
                    'stock' => 120,
                    'status' => ListingStatus::Active->value,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.farmer_id',
                $farmer->id
            )
            ->assertJsonPath(
                'data.produce_id',
                $produce->id
            )
            ->assertJsonPath(
                'data.price',
                '45000.00'
            )
            ->assertJsonPath(
                'data.stock',
                120
            )
            ->assertJsonPath(
                'data.status',
                ListingStatus::Active->value
            );
    }

    public function test_legacy_dispute_open_status_and_filter_remain_supported(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $listing = $this->createLegacyListing(
            farmer: $this->createLegacyFarmer(),
            produceName: 'Rice',
        );

        $order = $this->createLegacyOrder(
            buyer: $buyer,
            listing: $listing,
            orderNumber: 'ORD-LEGACY-DISPUTE',
            status: OrderStatus::Delivered,
            total: '90000.00',
            quantity: 2,
        );

        $buyerToken = auth('api')->login($buyer);

        $create = $this
            ->withToken($buyerToken)
            ->postJson('/api/v1/disputes', [
                'order_id' => $order->id,
                'subject' => 'Wrong quantity delivered',
                'message' => 'I ordered 5 bags but only received 3.',
            ]);

        $create
            ->assertCreated()
            ->assertJsonPath(
                'data.subject',
                'Wrong quantity delivered'
            )
            ->assertJsonPath(
                'data.status',
                'open'
            )
            ->assertJsonCount(
                1,
                'data.messages'
            );

        $disputeId = $create->json('data.id');

        $this
            ->withToken($buyerToken)
            ->getJson('/api/v1/disputes?status=open')
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $disputeId
            )
            ->assertJsonPath(
                'data.0.status',
                'open'
            );

        $adminToken = auth('api')->login($admin);

        $this
            ->withToken($adminToken)
            ->postJson(
                "/api/v1/admin/disputes/{$disputeId}/messages",
                [
                    'message' => 'Please share a delivery photo.',
                ]
            )
            ->assertCreated();

        $this
            ->withToken($adminToken)
            ->patchJson(
                "/api/v1/admin/disputes/{$disputeId}",
                [
                    'status' => 'resolved',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'resolved'
            );
    }

    public function test_legacy_dispute_order_produce_is_kept_after_single_item_backfill(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $farmer = $this->createLegacyFarmer();

        $listing = $this->createLegacyListing(
            farmer: $farmer,
            produceName: 'Rice',
            price: '45000.00',
        );

        $order = $this->createLegacyOrder(
            buyer: $buyer,
            listing: $listing,
            orderNumber: 'ORD-LEGACY-BACKFILL',
            status: OrderStatus::Delivered,
            total: '45000.00',
        );

        /*
         * Simulate what the additive order migration did to an
         * existing single-item order: keep orders.listing_id and
         * orders.quantity, but also create one order_items row.
         */
        OrderItem::create([
            'order_id' => $order->id,
            'listing_id' => $listing->id,
            'farmer_id' => $farmer->id,
            'produce_id' => $listing->produce_id,
            'produce_name' => 'Rice',
            'category_name' => 'Grains',
            'unit' => null,
            'quantity' => 1,
            'unit_price' => '45000.00',
            'discount_amount' => '0.00',
            'line_total' => '45000.00',
        ]);

        $token = auth('api')->login($buyer);

        $create = $this
            ->withToken($token)
            ->postJson('/api/v1/disputes', [
                'order_id' => $order->id,
                'subject' => 'Produce quality issue',
                'message' => 'The delivered rice has a quality issue.',
            ])
            ->assertCreated();

        $disputeId = $create->json('data.id');

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/disputes/{$disputeId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.order.produce.name',
                'Rice'
            );
    }

    public function test_legacy_farmer_detail_keeps_complete_embedded_collections_and_earnings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $buyer = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $farmer = $this->createLegacyFarmer();

        $category = Category::create([
            'name' => 'Grains',
        ]);

        $expectedEarned = 0;

        foreach (range(1, 6) as $index) {
            $produce = Produce::create([
                'category_id' => $category->id,
                'name' => "Produce {$index}",
                'image' => base64_encode(
                    "produce-{$index}"
                ),
                'image_mime' => 'image/jpeg',
            ]);

            $listing = Listing::create([
                'farmer_id' => $farmer->id,
                'produce_id' => $produce->id,
                'price' => 10000 * $index,
                'stock' => 50,
                'status' => ListingStatus::Active,
            ]);

            $status = $index === 6
                ? OrderStatus::Cancelled
                : OrderStatus::Delivered;

            $total = 10000 * $index;

            $this->createLegacyOrder(
                buyer: $buyer,
                listing: $listing,
                orderNumber: "ORD-LEGACY-FARMER-{$index}",
                status: $status,
                total: number_format(
                    $total,
                    2,
                    '.',
                    ''
                ),
            );

            if ($status !== OrderStatus::Cancelled) {
                $expectedEarned += $total;
            }
        }

        $token = auth('api')->login($admin);

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.listings_count',
                6
            )
            ->assertJsonPath(
                'data.orders_count',
                6
            )
            ->assertJsonPath(
                'data.total_earned',
                number_format(
                    $expectedEarned,
                    2,
                    '.',
                    ''
                )
            )
            ->assertJsonCount(
                6,
                'data.listings'
            )
            ->assertJsonCount(
                6,
                'data.orders'
            );
    }

    public function test_legacy_dashboard_active_users_keeps_original_meaning(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'role' => UserRole::User,
            'status' => UserStatus::Active,
        ]);

        User::factory()->create([
            'role' => UserRole::User,
            'status' => UserStatus::Inactive,
        ]);

        $token = auth('api')->login($admin);

        $this
            ->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()

            /*
             * Original key: all buyer-role users.
             */
            ->assertJsonPath(
                'data.active_users',
                2
            )

            /*
             * New preferred key: currently active buyers only.
             */
            ->assertJsonPath(
                'data.active_buyers',
                1
            );
    }

    private function createLegacyFarmer(): Farmer
    {
        return Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => fake()
                ->unique()
                ->numerify('080########'),
        ]);
    }

    private function createLegacyListing(
        Farmer $farmer,
        string $produceName,
        string $price = '45000.00',
        int $stock = 50,
    ): Listing {
        $category = Category::firstOrCreate([
            'name' => 'Grains',
        ]);

        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => $produceName,
            'image' => base64_encode(
                strtolower($produceName)
            ),
            'image_mime' => 'image/jpeg',
        ]);

        return Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => $price,
            'stock' => $stock,
            'status' => ListingStatus::Active,
        ]);
    }

    private function createLegacyOrder(
        User $buyer,
        Listing $listing,
        string $orderNumber,
        OrderStatus $status,
        string $total,
        int $quantity = 1,
    ): Order {
        return Order::create([
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
            'quantity' => $quantity,
            'order_number' => $orderNumber,
            'subtotal' => $total,
            'delivery_fee' => '0.00',
            'total' => $total,
            'status' => $status,
            'payment_status' => PaymentStatus::Pending,
        ]);
    }
}