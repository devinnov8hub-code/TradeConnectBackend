<?php

namespace Tests\Feature\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerIndexTest extends TestCase
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

    public function test_admin_can_get_paginated_farmer_list(): void
    {
        $token = $this->adminToken();

        Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'phone_number' =>
                '08012345678',
        ]);

        Farmer::create([
            'name' =>
                'Ada Okoro',

            'state' =>
                'Lagos',

            'lga' =>
                'Ikeja',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Pending,

            'phone_number' =>
                '08087654321',
        ]);

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/farmers'
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
                        'farmer_code',
                        'name',
                        'state',
                        'lga',
                        'status',
                        'verification_status',
                        'listings_count',
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

    public function test_admin_can_search_farmers(): void
    {
        $token = $this->adminToken();

        $ibrahim = Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'email' =>
                'ibrahim@example.com',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'farm_name' =>
                'Musa Farms',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'phone_number' =>
                '08012345678',
        ]);

        Farmer::create([
            'name' =>
                'Ada Okoro',

            'email' =>
                'ada@example.com',

            'state' =>
                'Lagos',

            'lga' =>
                'Ikeja',

            'farm_name' =>
                'Ada Green Farms',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Pending,

            'phone_number' =>
                '08087654321',
        ]);

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/farmers'
                .'?search=Ibrahim'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $ibrahim->id
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/farmers'
                .'?search=Musa%20Farms'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $ibrahim->id
            );
    }

    public function test_admin_can_filter_farmers_by_location_and_status(): void
    {
        $token = $this->adminToken();

        $expected = Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'phone_number' =>
                '08012345678',
        ]);

        Farmer::create([
            'name' =>
                'Sani Bello',

            'state' =>
                'Niger',

            'lga' =>
                'Minna',

            'status' =>
                FarmerStatus::Inactive,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'phone_number' =>
                '08011111111',
        ]);

        Farmer::create([
            'name' =>
                'Ada Okoro',

            'state' =>
                'Lagos',

            'lga' =>
                'Ikeja',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Pending,

            'phone_number' =>
                '08087654321',
        ]);

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/farmers'
                .'?state=Niger'
                .'&lga=Bida'
                .'&status=active'
                .'&verification_status=verified'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $expected->id
            );
    }

    public function test_farmer_list_includes_listing_count(): void
    {
        $token = $this->adminToken();

        $farmer = Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'phone_number' =>
                '08012345678',
        ]);

        $category = Category::create([
            'name' =>
                'Grains',
        ]);

        $rice = Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                'Rice',

            'image' =>
                base64_encode('rice'),

            'image_mime' =>
                'image/jpeg',
        ]);

        $maize = Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                'Maize',

            'image' =>
                base64_encode('maize'),

            'image_mime' =>
                'image/jpeg',
        ]);

        Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $rice->id,

            'price' =>
                45000,

            'unit' =>
                'bag',

            'stock' =>
                50,

            'status' =>
                ListingStatus::Active,
        ]);

        Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $maize->id,

            'price' =>
                30000,

            'unit' =>
                'bag',

            'stock' =>
                30,

            'status' =>
                ListingStatus::Active,
        ]);

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/farmers'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $farmer->id
            )
            ->assertJsonPath(
                'data.0.listings_count',
                2
            );
    }

    public function test_admin_can_sort_farmers_by_listing_count(): void
    {
        $token = $this->adminToken();

        $farmerWithNoListings =
            Farmer::create([
                'name' =>
                    'Ada Okoro',

                'state' =>
                    'Lagos',

                'lga' =>
                    'Ikeja',

                'status' =>
                    FarmerStatus::Active,

                'verification_status' =>
                    FarmerVerificationStatus::Pending,

                'phone_number' =>
                    '08087654321',
            ]);

        $farmerWithListing =
            Farmer::create([
                'name' =>
                    'Ibrahim Musa',

                'state' =>
                    'Niger',

                'lga' =>
                    'Bida',

                'status' =>
                    FarmerStatus::Active,

                'verification_status' =>
                    FarmerVerificationStatus::Verified,

                'phone_number' =>
                    '08012345678',
            ]);

        $category = Category::create([
            'name' =>
                'Grains',
        ]);

        $produce = Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                'Rice',

            'image' =>
                base64_encode('rice'),

            'image_mime' =>
                'image/jpeg',
        ]);

        Listing::create([
            'farmer_id' =>
                $farmerWithListing->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                45000,

            'unit' =>
                'bag',

            'stock' =>
                50,

            'status' =>
                ListingStatus::Active,
        ]);

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/farmers'
                .'?sort=listings_count'
                .'&order=desc'
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $farmerWithListing->id
            )
            ->assertJsonPath(
                'data.0.listings_count',
                1
            )
            ->assertJsonPath(
                'data.1.id',
                $farmerWithNoListings->id
            )
            ->assertJsonPath(
                'data.1.listings_count',
                0
            );
    }

    public function test_farmer_pagination_preserves_filters(): void
    {
        $token = $this->adminToken();

        Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'phone_number' =>
                '08012345678',
        ]);

        Farmer::create([
            'name' =>
                'Sani Bello',

            'state' =>
                'Niger',

            'lga' =>
                'Minna',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'phone_number' =>
                '08011111111',
        ]);

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/farmers'
                .'?state=Niger'
                .'&status=active'
                .'&per_page=1'
            );

        $response->assertOk();

        $next = $response->json(
            'links.next'
        );

        $this->assertNotNull($next);

        $this->assertStringContainsString(
            'state=Niger',
            $next
        );

        $this->assertStringContainsString(
            'status=active',
            $next
        );

        $this->assertStringContainsString(
            'per_page=1',
            $next
        );
    }

    public function test_invalid_farmer_filters_are_rejected(): void
    {
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/farmers'
                .'?status=blocked'
                .'&verification_status=unknown'
                .'&per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'verification_status',
                'per_page',
            ]);
    }
}