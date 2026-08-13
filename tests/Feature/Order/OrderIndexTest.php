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
use App\Models\OrderItem;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_get_only_their_paginated_orders(): void
    {
        $buyer = $this->createBuyer(
            'buyer@example.com'
        );

        $otherBuyer = $this->createBuyer(
            'other@example.com'
        );

        [$farmer, $produce, $listing] =
            $this->createCatalog();

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $produce,
            'ORD-BUYER-000001',
            45000
        );

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $produce,
            'ORD-BUYER-000002',
            90000
        );

        $this->createOrder(
            $otherBuyer,
            $listing,
            $farmer,
            $produce,
            'ORD-OTHER-000001',
            120000
        );

        $response = $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->getJson(
                '/api/v1/orders'
                .'?per_page=1'
                .'&page=1'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                1
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                2
            );
    }

    public function test_buyer_can_search_orders_by_order_number_and_item_name(): void
    {
        $buyer = $this->createBuyer(
            'buyer@example.com'
        );

        [$farmer, $rice, $riceListing] =
            $this->createCatalog(
                'Rice',
                'Grains'
            );

        [, $tomato, $tomatoListing] =
            $this->createCatalog(
                'Tomato',
                'Vegetables',
                $farmer
            );

        $riceOrder = $this->createOrder(
            $buyer,
            $riceListing,
            $farmer,
            $rice,
            'ORD-RICE-000001',
            45000
        );

        $this->createOrder(
            $buyer,
            $tomatoListing,
            $farmer,
            $tomato,
            'ORD-TOMATO-000001',
            12000
        );

        $token = auth('api')->login(
            $buyer
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/orders'
                .'?search=ORD-RICE'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $riceOrder->id
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/orders'
                .'?search=Rice'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $riceOrder->id
            );
    }

    public function test_search_does_not_return_another_buyers_order(): void
    {
        $buyer = $this->createBuyer(
            'buyer@example.com'
        );

        $otherBuyer = $this->createBuyer(
            'other@example.com'
        );

        [$farmer, $produce, $listing] =
            $this->createCatalog();

        $this->createOrder(
            $otherBuyer,
            $listing,
            $farmer,
            $produce,
            'ORD-SECRET-000001',
            45000
        );

        $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->getJson(
                '/api/v1/orders'
                .'?search=ORD-SECRET'
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data'
            )
            ->assertJsonPath(
                'meta.total',
                0
            );
    }

    public function test_buyer_can_filter_orders_by_status_and_payment_status(): void
    {
        $buyer = $this->createBuyer(
            'buyer@example.com'
        );

        [$farmer, $produce, $listing] =
            $this->createCatalog();

        $delivered = $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $produce,
            'ORD-DELIVERED-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $produce,
            'ORD-NEW-000001',
            90000,
            OrderStatus::New,
            PaymentStatus::Pending
        );

        $response = $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->getJson(
                '/api/v1/orders'
                .'?status=delivered'
                .'&payment_status=paid'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $delivered->id
            )
            ->assertJsonPath(
                'data.0.status',
                'delivered'
            )
            ->assertJsonPath(
                'data.0.payment_status',
                'paid'
            );
    }

    public function test_buyer_can_sort_orders_by_total(): void
    {
        $buyer = $this->createBuyer(
            'buyer@example.com'
        );

        [$farmer, $produce, $listing] =
            $this->createCatalog();

        $expensive = $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $produce,
            'ORD-EXPENSIVE-000001',
            90000
        );

        $cheap = $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $produce,
            'ORD-CHEAP-000001',
            12000
        );

        $response = $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->getJson(
                '/api/v1/orders'
                .'?sort=total'
                .'&order=asc'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $cheap->id
            )
            ->assertJsonPath(
                'data.1.id',
                $expensive->id
            );
    }

    public function test_buyer_order_pagination_preserves_filters(): void
    {
        $buyer = $this->createBuyer(
            'buyer@example.com'
        );

        [$farmer, $produce, $listing] =
            $this->createCatalog();

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $produce,
            'ORD-PAGE-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $produce,
            'ORD-PAGE-000002',
            90000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $response = $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->getJson(
                '/api/v1/orders'
                .'?status=delivered'
                .'&payment_status=paid'
                .'&sort=total'
                .'&order=asc'
                .'&per_page=1'
            );

        $response->assertOk();

        $next = $response->json(
            'links.next'
        );

        $this->assertNotNull(
            $next
        );

        $this->assertStringContainsString(
            'status=delivered',
            $next
        );

        $this->assertStringContainsString(
            'payment_status=paid',
            $next
        );

        $this->assertStringContainsString(
            'sort=total',
            $next
        );

        $this->assertStringContainsString(
            'order=asc',
            $next
        );

        $this->assertStringContainsString(
            'per_page=1',
            $next
        );
    }

    public function test_invalid_buyer_order_filters_are_rejected(): void
    {
        $buyer = $this->createBuyer(
            'buyer@example.com'
        );

        $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->getJson(
                '/api/v1/orders'
                .'?status=processing'
                .'&payment_status=authorized'
                .'&sort=unknown'
                .'&per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'payment_status',
                'sort',
                'per_page',
            ]);
    }

    private function createBuyer(
        string $email
    ): User {
        return User::factory()->create([
            'email' =>
                $email,

            'role' =>
                UserRole::User,
        ]);
    }

    /**
     * @return array{
     *     0: Farmer,
     *     1: Produce,
     *     2: Listing
     * }
     */
    private function createCatalog(
        string $produceName = 'Rice',
        string $categoryName = 'Grains',
        ?Farmer $farmer = null
    ): array {
        $farmer ??= Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'phone_number' =>
                fake()->unique()->numerify(
                    '080########'
                ),
        ]);

        $category = Category::firstOrCreate([
            'name' =>
                $categoryName,
        ]);

        $produce = Produce::create([
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

        $listing = Listing::create([
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

        return [
            $farmer,
            $produce,
            $listing,
        ];
    }

    private function createOrder(
        User $buyer,
        Listing $listing,
        Farmer $farmer,
        Produce $produce,
        string $orderNumber,
        int $total,
        OrderStatus $status = OrderStatus::New,
        PaymentStatus $paymentStatus = PaymentStatus::Pending
    ): Order {
        $order = Order::create([
            'user_id' =>
                $buyer->id,

            /*
             * Legacy compatibility columns.
             */
            'listing_id' =>
                $listing->id,

            'quantity' =>
                1,

            'order_number' =>
                $orderNumber,

            'subtotal' =>
                number_format(
                    $total,
                    2,
                    '.',
                    ''
                ),

            'delivery_fee' =>
                '0.00',

            'total' =>
                number_format(
                    $total,
                    2,
                    '.',
                    ''
                ),

            'status' =>
                $status,

            'payment_status' =>
                $paymentStatus,
        ]);

        $this->createOrderItem(
            $order,
            $listing,
            $farmer,
            $produce,
            $total
        );

        return $order;
    }

    private function createOrderItem(
        Order $order,
        Listing $listing,
        Farmer $farmer,
        Produce $produce,
        int $lineTotal
    ): OrderItem {
        $categoryName =
            $produce
                ->category()
                ->value('name');

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
                $categoryName,

            'unit' =>
                $listing->unit,

            'quantity' =>
                1,

            'unit_price' =>
                number_format(
                    $lineTotal,
                    2,
                    '.',
                    ''
                ),

            'discount_amount' =>
                '0.00',

            'line_total' =>
                number_format(
                    $lineTotal,
                    2,
                    '.',
                    ''
                ),
        ]);
    }
}