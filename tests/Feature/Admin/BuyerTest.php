<?php

namespace Tests\Feature\Admin;

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

class BuyerTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return auth('api')->login($admin);
    }

    public function test_admin_can_list_buyers_with_orders_count(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::User,
            'email' => 'buyer@example.com',
        ]);
        User::factory()->create(['role' => UserRole::Admin, 'email' => 'other-admin@example.com']);

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
        $listing = Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => 45000,
            'stock' => 20,
            'status' => ListingStatus::Active,
        ]);
        Order::create([
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
            'quantity' => 1,
            'total' => 45000,
            'status' => OrderStatus::New,
        ]);
        Order::create([
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
            'quantity' => 2,
            'total' => 90000,
            'status' => OrderStatus::Delivered,
        ]);

        $response = $this->withToken($this->adminToken())->getJson('/api/v1/admin/buyers');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'buyer@example.com')
            ->assertJsonPath('data.0.orders_count', 2)
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_admin_can_show_buyer(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::User]);

        $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/buyers/{$buyer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $buyer->id)
            ->assertJsonPath('data.orders_count', 0);
    }

    public function test_admin_cannot_show_admin_as_buyer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->withToken($this->adminToken())
            ->getJson("/api/v1/admin/buyers/{$admin->id}")
            ->assertNotFound()
            ->assertJson(['message' => 'Buyer not found.']);
    }
}
