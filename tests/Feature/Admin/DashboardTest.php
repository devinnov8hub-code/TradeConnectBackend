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

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_dashboard_stats(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::User]);
        User::factory()->create(['role' => UserRole::User]);

        $activeFarmer = Farmer::create([
            'name' => 'Active Farmer',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => '08011111111',
        ]);
        Farmer::create([
            'name' => 'Inactive Farmer',
            'state' => 'Lagos',
            'lga' => 'Ikeja',
            'status' => FarmerStatus::Inactive,
            'phone_number' => '08022222222',
        ]);

        $category = Category::create(['name' => 'Grains']);
        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => 'Rice',
            'image' => base64_encode('rice'),
            'image_mime' => 'image/jpeg',
        ]);
        $listing = Listing::create([
            'farmer_id' => $activeFarmer->id,
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

        $token = auth('api')->login($admin);

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.total_listings', 1)
            ->assertJsonPath('data.active_farmers', 1)
            ->assertJsonPath('data.active_users', 2);
    }

    public function test_non_admin_cannot_view_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }
}
