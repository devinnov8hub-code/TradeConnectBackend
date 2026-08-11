<?php

namespace Tests\Feature\Order;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private function userToken(): string
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        return auth('api')->login($user);
    }

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return auth('api')->login($admin);
    }

    private function createListing(int $stock = 100): Listing
    {
        $farmer = Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => '08012345678',
        ]);
        $category = Category::create(['name' => 'Grains']);
        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => 'Rice',
            'image' => base64_encode('rice'),
            'image_mime' => 'image/jpeg',
        ]);

        return Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => 45000,
            'stock' => $stock,
            'status' => ListingStatus::Active,
        ]);
    }

    public function test_user_can_create_order(): void
    {
        $listing = $this->createListing();

        $response = $this->withToken($this->userToken())->postJson('/api/v1/orders', [
            'listing_id' => $listing->id,
            'quantity' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.listing_id', $listing->id)
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.total', '90000.00')
            ->assertJsonPath('data.status', OrderStatus::New->value)
            ->assertJsonPath('data.produce.name', 'Rice')
            ->assertJsonPath('data.produce.category.name', 'Grains')
            ->assertJsonStructure(['data' => ['produce' => ['name', 'image_url', 'category' => ['id', 'name']]]]);

        $this->assertDatabaseHas('listings', ['id' => $listing->id, 'stock' => 98]);
    }

    public function test_user_can_view_and_cancel_their_orders(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $token = auth('api')->login($user);
        $listing = $this->createListing();

        $create = $this->withToken($token)->postJson('/api/v1/orders', [
            'listing_id' => $listing->id,
            'quantity' => 3,
        ]);

        $orderId = $create->json('data.id');

        $this->withToken($token)->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->patchJson("/api/v1/orders/{$orderId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Cancelled->value);

        $this->assertDatabaseHas('listings', ['id' => $listing->id, 'stock' => 100]);
    }

    public function test_user_cannot_view_another_users_order(): void
    {
        $owner = User::factory()->create(['role' => UserRole::User]);
        $other = User::factory()->create(['role' => UserRole::User]);
        $listing = $this->createListing();

        $order = Order::create([
            'user_id' => $owner->id,
            'listing_id' => $listing->id,
            'quantity' => 1,
            'total' => 45000,
            'status' => OrderStatus::New,
        ]);

        $this->withToken(auth('api')->login($other))
            ->getJson("/api/v1/orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_admin_can_list_orders_with_buyer_and_farmer(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::User,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
        $listing = $this->createListing();

        Order::create([
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
            'quantity' => 2,
            'total' => 90000,
            'status' => OrderStatus::New,
        ]);

        $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.buyer.name', 'Jane Doe')
            ->assertJsonPath('data.0.buyer_name', 'Jane Doe')
            ->assertJsonPath('data.0.buyer.email', 'jane@example.com')
            ->assertJsonPath('data.0.farmer.name', 'Ibrahim Musa')
            ->assertJsonPath('data.0.produce.name', 'Rice');
    }

    public function test_admin_can_update_order_status(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $listing = $this->createListing();

        $order = Order::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'quantity' => 1,
            'total' => 45000,
            'status' => OrderStatus::New,
        ]);

        $this->withToken($this->adminToken())
            ->patchJson("/api/v1/admin/orders/{$order->id}", [
                'status' => OrderStatus::InTransit->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::InTransit->value);

        $this->withToken($this->adminToken())
            ->patchJson("/api/v1/admin/orders/{$order->id}", [
                'status' => OrderStatus::Delivered->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', OrderStatus::Delivered->value);
    }
}
