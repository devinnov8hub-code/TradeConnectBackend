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
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        return auth('api')->login($admin);
    }

    public function test_admin_can_get_all_listings(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $produce = $this->createProduce(
            'Rice',
            'Grains'
        );

        Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => 45000,
            'unit' => 'bag',
            'stock' => 120,
            'status' => ListingStatus::Active,
        ]);

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/listings'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.farmer_id',
                $farmer->id
            )
            ->assertJsonPath(
                'data.0.produce_id',
                $produce->id
            )
            ->assertJsonPath(
                'data.0.produce.name',
                'Rice'
            )
            ->assertJsonPath(
                'data.0.produce.category.name',
                'Grains'
            )
            ->assertJsonPath(
                'data.0.unit',
                'bag'
            );
    }

    public function test_admin_can_add_listing_for_farmer(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $produce = $this->createProduce(
            'Rice',
            'Grains'
        );

        $response = $this
            ->withToken($token)
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                [
                    'produce_id' =>
                        $produce->id,

                    'price' =>
                        45000,

                    'unit' =>
                        'bag',

                    'stock' =>
                        120,

                    'status' =>
                        ListingStatus::Active->value,
                ]
            );

        $response
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
                'data.unit',
                'bag'
            )
            ->assertJsonPath(
                'data.stock',
                120
            )
            ->assertJsonPath(
                'data.status',
                ListingStatus::Active->value
            );

        $this->assertDatabaseHas(
            'listings',
            [
                'farmer_id' =>
                    $farmer->id,

                'produce_id' =>
                    $produce->id,

                'unit' =>
                    'bag',
            ]
        );
    }

    public function test_admin_can_update_listing_unit(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $produce = $this->createProduce(
            'Rice',
            'Grains'
        );

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
                120,

            'status' =>
                ListingStatus::Active,
        ]);

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/listings/{$listing->id}",
                [
                    'produce_id' =>
                        $produce->id,

                    'price' =>
                        5000,

                    'unit' =>
                        'kg',

                    'stock' =>
                        80,

                    'status' =>
                        ListingStatus::Active->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.price',
                '5000.00'
            )
            ->assertJsonPath(
                'data.unit',
                'kg'
            )
            ->assertJsonPath(
                'data.stock',
                80
            );

        $this->assertDatabaseHas(
            'listings',
            [
                'id' =>
                    $listing->id,

                'unit' =>
                    'kg',

                'stock' =>
                    80,
            ]
        );
    }

    public function test_unit_cannot_exceed_fifty_characters(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $produce = $this->createProduce(
            'Rice',
            'Grains'
        );

        $this
            ->withToken($token)
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                [
                    'produce_id' =>
                        $produce->id,

                    'price' =>
                        45000,

                    'unit' =>
                        str_repeat(
                            'x',
                            51
                        ),

                    'stock' =>
                        120,

                    'status' =>
                        ListingStatus::Active->value,
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'unit',
            ]);
    }

    public function test_admin_cannot_add_duplicate_produce_listing_for_same_farmer(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $produce = $this->createProduce(
            'Rice',
            'Grains'
        );

        Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                45000,

            'unit' =>
                'bag',

            'stock' =>
                120,

            'status' =>
                ListingStatus::Active,
        ]);

        $response = $this
            ->withToken($token)
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                [
                    'produce_id' =>
                        $produce->id,

                    'price' =>
                        50000,

                    'unit' =>
                        'bag',

                    'stock' =>
                        50,

                    'status' =>
                        ListingStatus::Active->value,
                ]
            );

        $response
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.produce_id.0',
                'This farmer already has a listing for this produce.'
            );
    }

    public function test_missing_listing_returns_not_found(): void
    {
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/listings/99999'
            )
            ->assertNotFound()
            ->assertJson([
                'message' =>
                    'Listing not found.',
            ]);
    }

    private function createFarmer(): Farmer
    {
        return Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                'verified',

            'phone_number' =>
                '08012345678',
        ]);
    }

    private function createProduce(
        string $name,
        string $categoryName
    ): Produce {
        $category = Category::create([
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
}