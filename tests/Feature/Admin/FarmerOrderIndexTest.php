<?php

namespace Tests\Feature\Admin;

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

class FarmerOrderIndexTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);

        return auth('api')->login(
            $admin
        );
    }

    public function test_admin_can_get_paginated_orders_for_farmer(): void
    {
        $token = $this->adminToken();

        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $farmer = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $otherFarmer = $this->createFarmer(
            'Ada Okoro',
            '08022222222'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $tomato = $this->createProduce(
            'Tomato',
            'Vegetables'
        );

        $riceListing = $this->createListing(
            $farmer,
            $rice,
            45000
        );

        $tomatoListing = $this->createListing(
            $otherFarmer,
            $tomato,
            12000
        );

        $this->createOrder(
            $buyer,
            $riceListing,
            $farmer,
            $rice,
            'ORD-FARM-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyer,
            $riceListing,
            $farmer,
            $rice,
            'ORD-FARM-000002',
            45000,
            OrderStatus::New,
            PaymentStatus::Pending
        );

        /*
         * Must not appear for Ibrahim.
         */
        $this->createOrder(
            $buyer,
            $tomatoListing,
            $otherFarmer,
            $tomato,
            'ORD-FARM-000003',
            12000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/orders"
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
            )
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'order_number',
                        'status',
                        'payment_status',
                        'total',
                        'items',
                    ],
                ],

                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],

                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'links',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);
    }

    public function test_multi_farmer_order_appears_once_in_farmer_history(): void
    {
        $token = $this->adminToken();

        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $farmerOne = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $farmerTwo = $this->createFarmer(
            'Ada Okoro',
            '08022222222'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $maize = $this->createProduce(
            'Maize',
            'Grains'
        );

        $tomato = $this->createProduce(
            'Tomato',
            'Vegetables'
        );

        $riceListing = $this->createListing(
            $farmerOne,
            $rice,
            45000
        );

        $maizeListing = $this->createListing(
            $farmerOne,
            $maize,
            30000
        );

        $tomatoListing = $this->createListing(
            $farmerTwo,
            $tomato,
            12000
        );

        $order = Order::create([
            'user_id' =>
                $buyer->id,

            'listing_id' =>
                $riceListing->id,

            'quantity' =>
                1,

            'order_number' =>
                'ORD-MULTI-000001',

            'subtotal' =>
                '87000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '87000.00',

            'status' =>
                OrderStatus::New,

            'payment_status' =>
                PaymentStatus::Pending,
        ]);

        $this->createOrderItem(
            $order,
            $riceListing,
            $farmerOne,
            $rice,
            45000
        );

        /*
         * Same farmer, second line item.
         */
        $this->createOrderItem(
            $order,
            $maizeListing,
            $farmerOne,
            $maize,
            30000
        );

        /*
         * Another farmer in the same parent order.
         */
        $this->createOrderItem(
            $order,
            $tomatoListing,
            $farmerTwo,
            $tomato,
            12000
        );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmerOne->id}/orders"
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'meta.total',
                1
            )
            ->assertJsonPath(
                'data.0.id',
                $order->id
            );
    }

    public function test_admin_can_search_farmer_orders(): void
    {
        $token = $this->adminToken();

        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $farmer = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $maize = $this->createProduce(
            'Maize',
            'Grains'
        );

        $riceListing = $this->createListing(
            $farmer,
            $rice,
            45000
        );

        $maizeListing = $this->createListing(
            $farmer,
            $maize,
            30000
        );

        $riceOrder = $this->createOrder(
            $buyer,
            $riceListing,
            $farmer,
            $rice,
            'ORD-RICE-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyer,
            $maizeListing,
            $farmer,
            $maize,
            'ORD-MAIZE-000001',
            30000,
            OrderStatus::New,
            PaymentStatus::Pending
        );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/orders"
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

        /*
         * Snapshot produce names are searchable.
         */
        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/orders"
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

    public function test_admin_can_filter_farmer_orders_by_status_and_payment_status(): void
    {
        $token = $this->adminToken();

        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $farmer = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $listing = $this->createListing(
            $farmer,
            $rice,
            45000
        );

        $paidOrder = $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $rice,
            'ORD-FILTER-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $rice,
            'ORD-FILTER-000002',
            45000,
            OrderStatus::New,
            PaymentStatus::Pending
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/orders"
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
                $paidOrder->id
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

    public function test_admin_can_sort_farmer_orders_by_total(): void
    {
        $token = $this->adminToken();

        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $farmer = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $listing = $this->createListing(
            $farmer,
            $rice,
            45000
        );

        $expensive = $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $rice,
            'ORD-SORT-000001',
            90000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $cheap = $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $rice,
            'ORD-SORT-000002',
            45000,
            OrderStatus::New,
            PaymentStatus::Pending
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/orders"
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

    public function test_farmer_order_pagination_preserves_filters(): void
    {
        $token = $this->adminToken();

        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $farmer = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $listing = $this->createListing(
            $farmer,
            $rice,
            45000
        );

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $rice,
            'ORD-PAGE-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $rice,
            'ORD-PAGE-000002',
            90000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/orders"
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

    public function test_invalid_farmer_order_filters_are_rejected(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/orders"
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

    private function createFarmer(
        string $name,
        string $phoneNumber
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

            'phone_number' =>
                $phoneNumber,
        ]);
    }

    private function createProduce(
        string $name,
        string $categoryName
    ): Produce {
        $category = Category::firstOrCreate([
            'name' =>
                $categoryName,
        ]);

        return Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                $name,

            'image' =>
                base64_encode(
                    strtolower($name)
                ),

            'image_mime' =>
                'image/jpeg',
        ]);
    }

    private function createListing(
        Farmer $farmer,
        Produce $produce,
        int $price
    ): Listing {
        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                $price,

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
        Listing $listing,
        Farmer $farmer,
        Produce $produce,
        string $orderNumber,
        int $total,
        OrderStatus $status,
        PaymentStatus $paymentStatus
    ): Order {
        $order = Order::create([
            'user_id' =>
                $buyer->id,

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