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

class ListingIndexTest extends TestCase
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

    public function test_admin_can_get_paginated_global_listing_list(): void
    {
        $token = $this->adminToken();

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

        $this->createListing(
            $farmerOne,
            $rice,
            45000,
            'bag',
            ListingStatus::Active
        );

        $this->createListing(
            $farmerOne,
            $maize,
            30000,
            'bag',
            ListingStatus::Active
        );

        $this->createListing(
            $farmerTwo,
            $tomato,
            12000,
            'basket',
            ListingStatus::Inactive
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/listings'
                .'?per_page=2'
                .'&page=1'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                2
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                3
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

    public function test_admin_can_search_global_listings(): void
    {
        $token = $this->adminToken();

        $ibrahim = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $ada = $this->createFarmer(
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
            $ibrahim,
            $rice,
            45000,
            'bag',
            ListingStatus::Active
        );

        $this->createListing(
            $ada,
            $tomato,
            12000,
            'basket',
            ListingStatus::Active
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/listings'
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
                '/api/v1/admin/listings'
                .'?search=Ibrahim'
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
                '/api/v1/admin/listings'
                .'?search='
                .$ibrahim->farmer_code
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

    public function test_admin_can_filter_global_listings(): void
    {
        $token = $this->adminToken();

        $ibrahim = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $ada = $this->createFarmer(
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
            $ibrahim,
            $rice,
            45000,
            'bag',
            ListingStatus::Active
        );

        $this->createListing(
            $ada,
            $tomato,
            12000,
            'basket',
            ListingStatus::Inactive
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/listings'
                .'?farmer_id='
                .$ibrahim->id
                .'&category_id='
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
                'data.0.farmer_id',
                $ibrahim->id
            )
            ->assertJsonPath(
                'data.0.status',
                'active'
            );
    }

    public function test_admin_can_sort_global_listings_by_price(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
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
                '/api/v1/admin/listings'
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

    public function test_admin_can_sort_global_listings_by_farmer(): void
    {
        $token = $this->adminToken();

        $ibrahim = $this->createFarmer(
            'Ibrahim Musa',
            '08011111111'
        );

        $ada = $this->createFarmer(
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

        $ibrahimListing = $this->createListing(
            $ibrahim,
            $rice,
            45000,
            'bag',
            ListingStatus::Active
        );

        $adaListing = $this->createListing(
            $ada,
            $tomato,
            12000,
            'basket',
            ListingStatus::Active
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/listings'
                .'?sort=farmer'
                .'&order=asc'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $adaListing->id
            )
            ->assertJsonPath(
                'data.1.id',
                $ibrahimListing->id
            );
    }

    public function test_global_listing_pagination_preserves_filters(): void
    {
        $token = $this->adminToken();

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
                '/api/v1/admin/listings'
                .'?farmer_id='
                .$farmer->id
                .'&status=active'
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
            'farmer_id='.$farmer->id,
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

    public function test_invalid_global_listing_filters_are_rejected(): void
    {
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/listings'
                .'?farmer_id=99999'
                .'&category_id=99999'
                .'&status=pending'
                .'&sort=unknown'
                .'&per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'farmer_id',
                'category_id',
                'status',
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