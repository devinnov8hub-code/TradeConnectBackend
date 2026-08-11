<?php

namespace Tests\Feature\Order;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function userToken(
        ?User $user = null
    ): string {
        $user ??= User::factory()->create([
            'role' => UserRole::User,
        ]);

        return auth('api')->login($user);
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        return auth('api')->login($admin);
    }

    private function createListing(
        string $produceName = 'Rice',
        string $price = '45000.00',
        int $stock = 100,
        ListingStatus $status = ListingStatus::Active,
        string $farmerName = 'Ibrahim Musa',
        string $categoryName = 'Grains',
    ): Listing {
        $farmer = Farmer::firstOrCreate(
            [
                'name' => $farmerName,
            ],
            [
                'state' => 'Niger',
                'lga' => 'Bida',
                'status' => FarmerStatus::Active,
                'phone_number' => '08012345678',
            ]
        );

        $category = Category::firstOrCreate([
            'name' => $categoryName,
        ]);

        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => $produceName,
            'image' => base64_encode(
                strtolower(
                    str_replace(
                        ' ',
                        '-',
                        $produceName
                    )
                )
            ),
            'image_mime' => 'image/jpeg',
        ]);

        return Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => $price,
            'stock' => $stock,
            'status' => $status,
        ]);
    }

    public function test_buyer_can_create_order_with_multiple_items(): void
    {
        $rice = $this->createListing(
            produceName: 'Rice',
            price: '45000.00',
            stock: 100,
            farmerName: 'Ibrahim Musa',
            categoryName: 'Grains',
        );

        $tomatoes = $this->createListing(
            produceName: 'Tomatoes',
            price: '2500.00',
            stock: 50,
            farmerName: 'Aisha Bello',
            categoryName: 'Vegetables',
        );

        $response = $this
            ->withToken($this->userToken())
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $rice->id,
                        'quantity' => 2,
                    ],
                    [
                        'listing_id' => $tomatoes->id,
                        'quantity' => 5,
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.subtotal',
                '102500.00'
            )
            ->assertJsonPath(
                'data.delivery_fee',
                '0.00'
            )
            ->assertJsonPath(
                'data.total',
                '102500.00'
            )
            ->assertJsonPath(
                'data.payment_status',
                'pending'
            )
            ->assertJsonPath(
                'data.status',
                OrderStatus::New->value
            )
            ->assertJsonPath(
                'data.items.0.produce_name',
                'Rice'
            )
            ->assertJsonPath(
                'data.items.0.quantity',
                2
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                '45000.00'
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                '90000.00'
            )
            ->assertJsonPath(
                'data.items.1.produce_name',
                'Tomatoes'
            )
            ->assertJsonPath(
                'data.items.1.quantity',
                5
            )
            ->assertJsonPath(
                'data.items.1.unit_price',
                '2500.00'
            )
            ->assertJsonPath(
                'data.items.1.line_total',
                '12500.00'
            )
            ->assertJsonCount(
                2,
                'data.items'
            );

        $this->assertMatchesRegularExpression(
            '/^ORD-\d+$/',
            $response->json(
                'data.order_number'
            )
        );

        $orderId = $response->json('data.id');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'subtotal' => 102500,
            'total' => 102500,
            'payment_status' => 'pending',
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'listing_id' => $rice->id,
            'produce_name' => 'Rice',
            'quantity' => 2,
            'unit_price' => 45000,
            'line_total' => 90000,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'listing_id' => $tomatoes->id,
            'produce_name' => 'Tomatoes',
            'quantity' => 5,
            'unit_price' => 2500,
            'line_total' => 12500,
        ]);

        $this->assertDatabaseHas('listings', [
            'id' => $rice->id,
            'stock' => 98,
        ]);

        $this->assertDatabaseHas('listings', [
            'id' => $tomatoes->id,
            'stock' => 45,
        ]);
    }

    public function test_order_total_is_calculated_by_server(): void
    {
        $listing = $this->createListing(
            price: '1250.50'
        );

        $response = $this
            ->withToken($this->userToken())
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $listing->id,
                        'quantity' => 4,
                    ],
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.items.0.unit_price',
                '1250.50'
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                '5002.00'
            )
            ->assertJsonPath(
                'data.subtotal',
                '5002.00'
            )
            ->assertJsonPath(
                'data.total',
                '5002.00'
            );
    }

    public function test_client_cannot_submit_order_totals_or_prices(): void
    {
        $listing = $this->createListing();

        $this
            ->withToken($this->userToken())
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $listing->id,
                        'quantity' => 2,
                        'unit_price' => 1,
                    ],
                ],
                'total' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.unit_price',
                'total',
            ]);

        $this->assertDatabaseMissing('orders', [
            'total' => 1,
        ]);

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'stock' => 100,
        ]);
    }

    public function test_order_rejects_insufficient_stock_without_changing_any_stock(): void
    {
        $rice = $this->createListing(
            produceName: 'Rice',
            price: '1000.00',
            stock: 10,
            categoryName: 'Grains',
        );

        $tomatoes = $this->createListing(
            produceName: 'Tomatoes',
            price: '500.00',
            stock: 2,
            farmerName: 'Aisha Bello',
            categoryName: 'Vegetables',
        );

        $this
            ->withToken($this->userToken())
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $rice->id,
                        'quantity' => 3,
                    ],
                    [
                        'listing_id' => $tomatoes->id,
                        'quantity' => 5,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.1.quantity',
            ]);

        $this->assertDatabaseCount(
            'orders',
            0
        );

        $this->assertDatabaseCount(
            'order_items',
            0
        );

        $this->assertDatabaseHas('listings', [
            'id' => $rice->id,
            'stock' => 10,
        ]);

        $this->assertDatabaseHas('listings', [
            'id' => $tomatoes->id,
            'stock' => 2,
        ]);
    }

    public function test_buyer_cannot_order_inactive_listing(): void
    {
        $listing = $this->createListing(
            status: ListingStatus::Inactive
        );

        $this
            ->withToken($this->userToken())
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $listing->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.listing_id',
            ]);

        $this->assertDatabaseCount(
            'orders',
            0
        );

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'stock' => 100,
        ]);
    }

    public function test_duplicate_listing_cannot_be_added_twice(): void
    {
        $listing = $this->createListing();

        $this
            ->withToken($this->userToken())
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $listing->id,
                        'quantity' => 1,
                    ],
                    [
                        'listing_id' => $listing->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.1.listing_id',
            ]);

        $this->assertDatabaseCount(
            'orders',
            0
        );
    }

    public function test_buyer_can_view_and_cancel_order_and_all_stock_is_restored(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $token = $this->userToken(
            $buyer
        );

        $rice = $this->createListing(
            produceName: 'Rice',
            price: '1000.00',
            stock: 100,
            categoryName: 'Grains',
        );

        $tomatoes = $this->createListing(
            produceName: 'Tomatoes',
            price: '500.00',
            stock: 50,
            farmerName: 'Aisha Bello',
            categoryName: 'Vegetables',
        );

        $create = $this
            ->withToken($token)
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $rice->id,
                        'quantity' => 3,
                    ],
                    [
                        'listing_id' => $tomatoes->id,
                        'quantity' => 4,
                    ],
                ],
            ])
            ->assertCreated();

        $orderId = $create->json(
            'data.id'
        );

        $this
            ->withToken($token)
            ->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonCount(
                2,
                'data.0.items'
            );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/orders/{$orderId}"
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.items'
            );

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/orders/{$orderId}/cancel"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::Cancelled->value
            );

        $this->assertDatabaseHas('listings', [
            'id' => $rice->id,
            'stock' => 100,
        ]);

        $this->assertDatabaseHas('listings', [
            'id' => $tomatoes->id,
            'stock' => 50,
        ]);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $other = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $listing = $this->createListing();

        $create = $this
            ->withToken(
                $this->userToken($owner)
            )
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $listing->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertCreated();

        $orderId = $create->json(
            'data.id'
        );

        $this
            ->withToken(
                $this->userToken($other)
            )
            ->getJson(
                "/api/v1/orders/{$orderId}"
            )
            ->assertNotFound();
    }

    public function test_admin_can_list_multi_item_orders_with_buyer_and_farmers(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::User,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $rice = $this->createListing(
            produceName: 'Rice',
            farmerName: 'Ibrahim Musa',
            categoryName: 'Grains',
        );

        $tomatoes = $this->createListing(
            produceName: 'Tomatoes',
            price: '2500.00',
            farmerName: 'Aisha Bello',
            categoryName: 'Vegetables',
        );

        $this
            ->withToken(
                $this->userToken($buyer)
            )
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $rice->id,
                        'quantity' => 1,
                    ],
                    [
                        'listing_id' => $tomatoes->id,
                        'quantity' => 2,
                    ],
                ],
            ])
            ->assertCreated();

        $this
            ->withToken(
                $this->adminToken()
            )
            ->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.buyer.name',
                'Jane Doe'
            )
            ->assertJsonPath(
                'data.0.buyer.email',
                'jane@example.com'
            )
            ->assertJsonCount(
                2,
                'data.0.items'
            )
            ->assertJsonPath(
                'data.0.items.0.farmer.name',
                'Ibrahim Musa'
            )
            ->assertJsonPath(
                'data.0.items.1.farmer.name',
                'Aisha Bello'
            );
    }

    public function test_admin_can_update_multi_item_order_status(): void
    {
        $listing = $this->createListing();

        $create = $this
            ->withToken($this->userToken())
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $listing->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertCreated();

        $orderId = $create->json(
            'data.id'
        );

        $this
            ->withToken(
                $this->adminToken()
            )
            ->patchJson(
                "/api/v1/admin/orders/{$orderId}",
                [
                    'status' => OrderStatus::InTransit->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::InTransit->value
            );

        $this
            ->withToken(
                $this->adminToken()
            )
            ->patchJson(
                "/api/v1/admin/orders/{$orderId}",
                [
                    'status' => OrderStatus::Delivered->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::Delivered->value
            );
    }

    public function test_admin_cancellation_restores_stock_for_every_order_item(): void
    {
        $rice = $this->createListing(
            produceName: 'Rice',
            price: '1000.00',
            stock: 20,
            categoryName: 'Grains',
        );

        $tomatoes = $this->createListing(
            produceName: 'Tomatoes',
            price: '500.00',
            stock: 30,
            farmerName: 'Aisha Bello',
            categoryName: 'Vegetables',
        );

        $create = $this
            ->withToken($this->userToken())
            ->postJson('/api/v1/orders', [
                'items' => [
                    [
                        'listing_id' => $rice->id,
                        'quantity' => 2,
                    ],
                    [
                        'listing_id' => $tomatoes->id,
                        'quantity' => 3,
                    ],
                ],
            ])
            ->assertCreated();

        $orderId = $create->json(
            'data.id'
        );

        $this->assertDatabaseHas('listings', [
            'id' => $rice->id,
            'stock' => 18,
        ]);

        $this->assertDatabaseHas('listings', [
            'id' => $tomatoes->id,
            'stock' => 27,
        ]);

        $this
            ->withToken(
                $this->adminToken()
            )
            ->patchJson(
                "/api/v1/admin/orders/{$orderId}",
                [
                    'status' => OrderStatus::Cancelled->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                OrderStatus::Cancelled->value
            );

        $this->assertDatabaseHas('listings', [
            'id' => $rice->id,
            'stock' => 20,
        ]);

        $this->assertDatabaseHas('listings', [
            'id' => $tomatoes->id,
            'stock' => 30,
        ]);
    }
}