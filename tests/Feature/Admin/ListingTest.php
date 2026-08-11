<?php

namespace Tests\Feature\Admin;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return auth('api')->login($admin);
    }

    public function test_admin_can_get_all_listings(): void
    {
        $token = $this->adminToken();
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

        Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => 45000,
            'stock' => 120,
            'status' => ListingStatus::Active,
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/listings');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.farmer_id', $farmer->id)
            ->assertJsonPath('data.0.produce_id', $produce->id)
            ->assertJsonPath('data.0.produce.name', 'Rice')
            ->assertJsonPath('data.0.produce.category.name', 'Grains')
            ->assertJsonStructure(['data' => [['produce' => ['name', 'image_url', 'category' => ['id', 'name']]]]]);
    }

    public function test_admin_can_add_listing_for_farmer(): void
    {
        $token = $this->adminToken();
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

        $response = $this->withToken($token)->postJson("/api/v1/admin/farmers/{$farmer->id}/listings", [
            'produce_id' => $produce->id,
            'price' => 45000,
            'stock' => 120,
            'status' => ListingStatus::Active->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.farmer_id', $farmer->id)
            ->assertJsonPath('data.produce_id', $produce->id)
            ->assertJsonPath('data.price', '45000.00')
            ->assertJsonPath('data.stock', 120)
            ->assertJsonPath('data.status', ListingStatus::Active->value);
    }

    public function test_admin_cannot_add_duplicate_produce_listing_for_same_farmer(): void
    {
        $token = $this->adminToken();
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

        Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => 45000,
            'stock' => 120,
            'status' => ListingStatus::Active,
        ]);

        $response = $this->withToken($token)->postJson("/api/v1/admin/farmers/{$farmer->id}/listings", [
            'produce_id' => $produce->id,
            'price' => 50000,
            'stock' => 50,
            'status' => ListingStatus::Active->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.produce_id.0', 'This farmer already has a listing for this produce.');
    }

    public function test_missing_listing_returns_not_found(): void
    {
        $token = $this->adminToken();
        $farmer = Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => '08012345678',
        ]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/listings/99999');

        $response->assertNotFound()
            ->assertJson(['message' => 'Listing not found.']);
    }
}
