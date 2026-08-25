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

class OrderIndexTest extends TestCase
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

    public function test_admin_can_get_paginated_orders(): void
    {
        $token = $this->adminToken();

        $buyer = $this->createBuyer(
            'Buyer One',
            'buyer1@example.com'
        );

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
            'ORD-ADMIN-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyer,
            $listing,
            $farmer,
            $rice,
            'ORD-ADMIN-000002',
            90000,
            OrderStatus::New,
            PaymentStatus::Pending
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/orders'
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

    public function test_admin_can_search_orders(): void
    {
        $token = $this->adminToken();

        $buyerOne = $this->createBuyer(
            'Samuel Buyer',
            'samuel@example.com'
        );

        $buyerTwo = $this->createBuyer(
            'Ada Buyer',
            'ada@example.com'
        );

        $farmerOne = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $farmerTwo = $this->createFarmer(
            'Bello Farms',
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
            $farmerOne,
            $rice,
            45000
        );

        $tomatoListing = $this->createListing(
            $farmerTwo,
            $tomato,
            12000
        );

        $riceOrder = $this->createOrder(
            $buyerOne,
            $riceListing,
            $farmerOne,
            $rice,
            'ORD-RICE-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyerTwo,
            $tomatoListing,
            $farmerTwo,
            $tomato,
            'ORD-TOMATO-000001',
            12000,
            OrderStatus::New,
            PaymentStatus::Pending
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/orders'
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
                '/api/v1/admin/orders'
                .'?search=Samuel'
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
                '/api/v1/admin/orders'
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

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/orders'
                .'?search=Ibrahim'
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

    public function test_admin_can_filter_orders_by_status_and_payment_status(): void
    {
        $token = $this->adminToken();

        $buyer = $this->createBuyer(
            'Buyer One',
            'buyer@example.com'
        );

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
                '/api/v1/admin/orders'
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

    public function test_admin_can_filter_orders_by_farmer(): void
    {
        $token = $this->adminToken();

        $buyer = $this->createBuyer(
            'Buyer One',
            'buyer@example.com'
        );

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

        $tomato = $this->createProduce(
            'Tomato',
            'Vegetables'
        );

        $riceListing = $this->createListing(
            $farmerOne,
            $rice,
            45000
        );

        $tomatoListing = $this->createListing(
            $farmerTwo,
            $tomato,
            12000
        );

        $farmerOneOrder = $this->createOrder(
            $buyer,
            $riceListing,
            $farmerOne,
            $rice,
            'ORD-FARMER-000001',
            45000,
            OrderStatus::Delivered,
            PaymentStatus::Paid
        );

        $this->createOrder(
            $buyer,
            $tomatoListing,
            $farmerTwo,
            $tomato,
            'ORD-FARMER-000002',
            12000,
            OrderStatus::New,
            PaymentStatus::Pending
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/orders'
                .'?farmer_id='
                .$farmerOne->id
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $farmerOneOrder->id
            );
    }

    public function test_multi_farmer_order_matches_each_farmer_filter_once(): void
    {
        $token = $this->adminToken();

        $buyer = $this->createBuyer(
            'Buyer One',
            'buyer@example.com'
        );

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

        $tomato = $this->createProduce(
            'Tomato',
            'Vegetables'
        );

        $riceListing = $this->createListing(
            $farmerOne,
            $rice,
            45000
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
                'ORD-MULTI-ADMIN-000001',

            'subtotal' =>
                '57000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '57000.00',

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
                '/api/v1/admin/orders'
                .'?farmer_id='
                .$farmerOne->id
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

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/orders'
                .'?farmer_id='
                .$farmerTwo->id
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

    public function test_admin_can_sort_orders_by_total(): void
    {
        $token = $this->adminToken();

        $buyer = $this->createBuyer(
            'Buyer One',
            'buyer@example.com'
        );

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
                '/api/v1/admin/orders'
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

    public function test_order_pagination_preserves_filters(): void
    {
        $token = $this->adminToken();

        $buyer = $this->createBuyer(
            'Buyer One',
            'buyer@example.com'
        );

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
                '/api/v1/admin/orders'
                .'?farmer_id='
                .$farmer->id
                .'&status=delivered'
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
            'farmer_id='.$farmer->id,
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

    public function test_invalid_order_filters_are_rejected(): void
    {
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/orders'
                .'?farmer_id=99999'
                .'&status=processing'
                .'&payment_status=authorized'
                .'&sort=unknown'
                .'&per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'farmer_id',
                'status',
                'payment_status',
                'sort',
                'per_page',
            ]);
    }

    private function createBuyer(
        string $name,
        string $email
    ): User {
        return User::factory()->create([
            'name' =>
                $name,

            'email' =>
                $email,

            'role' =>
                UserRole::User,
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