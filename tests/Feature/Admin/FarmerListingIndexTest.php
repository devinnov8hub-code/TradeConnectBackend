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

class FarmerListingIndexTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        return auth('api')->login($admin);
    }

    public function test_admin_can_get_paginated_listings_for_one_farmer(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa'
        );

        $otherFarmer = $this->createFarmer(
            'Ada Okoro'
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

        $this->createListing(
            $farmer,
            $rice,
            45000,
            'bag',
            ListingStatus::Active
        );

        $this->createListing(
            $farmer,
            $maize,
            30000,
            'bag',
            ListingStatus::Active
        );

        /*
         * Must not appear because this belongs
         * to another farmer.
         */
        $this->createListing(
            $otherFarmer,
            $tomato,
            12000,
            'basket',
            ListingStatus::Active
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings"
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
            ->assertJsonPath(
                'data.0.farmer_id',
                $farmer->id
            )
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'farmer_id',
                        'produce_id',
                        'price',
                        'unit',
                        'stock',
                        'status',
                        'produce',
                        'farmer',
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

    public function test_admin_can_search_farmer_listings(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa'
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
            45000,
            'bag',
            ListingStatus::Active
        );

        $this->createListing(
            $farmer,
            $tomato,
            12000,
            'basket',
            ListingStatus::Active
        );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings"
                .'?search=Rice'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $riceListing->id
            );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings"
                .'?search=Grains'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $riceListing->id
            );
    }

    public function test_admin_can_filter_farmer_listings_by_category_and_status(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa'
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
            45000,
            'bag',
            ListingStatus::Active
        );

        $this->createListing(
            $farmer,
            $tomato,
            12000,
            'basket',
            ListingStatus::Inactive
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings"
                .'?category_id='
                .$rice->category_id
                .'&status=active'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $riceListing->id
            )
            ->assertJsonPath(
                'data.0.status',
                'active'
            );
    }

    public function test_admin_can_sort_farmer_listings_by_price(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $tomato = $this->createProduce(
            'Tomato',
            'Vegetables'
        );

        $expensive = $this->createListing(
            $farmer,
            $rice,
            45000,
            'bag',
            ListingStatus::Active
        );

        $cheap = $this->createListing(
            $farmer,
            $tomato,
            12000,
            'basket',
            ListingStatus::Active
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings"
                .'?sort=price'
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

    public function test_admin_can_sort_farmer_listings_by_category(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $tomato = $this->createProduce(
            'Tomato',
            'Vegetables'
        );

        $grainListing = $this->createListing(
            $farmer,
            $rice,
            45000,
            'bag',
            ListingStatus::Active
        );

        $vegetableListing = $this->createListing(
            $farmer,
            $tomato,
            12000,
            'basket',
            ListingStatus::Active
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings"
                .'?sort=category'
                .'&order=asc'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $grainListing->id
            )
            ->assertJsonPath(
                'data.1.id',
                $vegetableListing->id
            );
    }

    public function test_farmer_listing_pagination_preserves_filters(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa'
        );

        $rice = $this->createProduce(
            'Rice',
            'Grains'
        );

        $maize = $this->createProduce(
            'Maize',
            'Grains'
        );

        $this->createListing(
            $farmer,
            $rice,
            45000,
            'bag',
            ListingStatus::Active
        );

        $this->createListing(
            $farmer,
            $maize,
            30000,
            'bag',
            ListingStatus::Active
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings"
                .'?status=active'
                .'&sort=price'
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
            'status=active',
            $next
        );

        $this->assertStringContainsString(
            'sort=price',
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

    public function test_invalid_farmer_listing_filters_are_rejected(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa'
        );

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings"
                .'?status=pending'
                .'&sort=unknown'
                .'&per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'sort',
                'per_page',
            ]);
    }

    private function createFarmer(
        string $name
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
                '08012345678',
        ]);
    }

    private function createProduce(
        string $name,
        string $categoryName
    ): Produce {
        /*
         * More than one produce can belong to the same
         * category. Reuse it instead of trying to insert
         * another category with the same unique name.
         */
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
        int $price,
        string $unit,
        ListingStatus $status
    ): Listing {
        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                $price,

            'unit' =>
                $unit,

            'stock' =>
                50,

            'status' =>
                $status,
        ]);
    }
}