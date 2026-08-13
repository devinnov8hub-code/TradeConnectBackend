<?php

namespace Tests\Feature\Listing;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_fetch_active_listings(): void
    {
        $farmer = Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

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
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                45000,

            'stock' =>
                50,

            'status' =>
                ListingStatus::Active,
        ]);

        $beans = Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                'Beans',

            'image' =>
                base64_encode('beans'),

            'image_mime' =>
                'image/jpeg',
        ]);

        Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $beans->id,

            'price' =>
                40000,

            'stock' =>
                0,

            'status' =>
                ListingStatus::Inactive,
        ]);

        $response = $this->getJson(
            '/api/v1/listings'
        );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.produce.name',
                'Rice'
            )
            ->assertJsonPath(
                'data.0.farmer.name',
                'Ibrahim Musa'
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                20
            )
            ->assertJsonPath(
                'meta.total',
                1
            )
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'price',
                        'stock',
                        'status',

                        'produce' => [
                            'id',
                            'name',
                            'image_url',

                            'category' => [
                                'id',
                                'name',
                            ],
                        ],

                        'farmer' => [
                            'id',
                            'name',
                            'state',
                            'lga',
                        ],
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

    public function test_public_can_get_one_active_listing(): void
    {
        [$listing] =
            $this->seedListings();

        $this
            ->getJson(
                "/api/v1/listings/{$listing->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.produce.name',
                'Rice'
            );
    }

    public function test_inactive_listing_returns_not_found(): void
    {
        $farmer = Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

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

        $listing = Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                45000,

            'stock' =>
                50,

            'status' =>
                ListingStatus::Inactive,
        ]);

        $this
            ->getJson(
                "/api/v1/listings/{$listing->id}"
            )
            ->assertNotFound()
            ->assertJson([
                'message' =>
                    'Listing not found.',
            ]);
    }

    public function test_public_can_search_and_filter_listings(): void
    {
        [
            $riceListing,
            $tomatoListing,
        ] = $this->seedListings();

        $this
            ->getJson(
                '/api/v1/listings?search=Rice'
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
            ->getJson(
                '/api/v1/listings?category_id='
                .$tomatoListing
                    ->produce
                    ->category_id
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $tomatoListing->id
            );

        $this
            ->getJson(
                '/api/v1/listings?farmer_id='
                .$riceListing->farmer_id
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

    public function test_public_can_sort_listings_by_price_and_category(): void
    {
        $this->seedListings();

        $byPrice = $this->getJson(
            '/api/v1/listings?sort=price&order=asc'
        );

        $byPrice->assertOk();

        $this->assertTrue(
            $byPrice->json(
                'data.0.price'
            )
            <=
            $byPrice->json(
                'data.1.price'
            )
        );

        $byCategory = $this->getJson(
            '/api/v1/listings?sort=category&order=asc'
        );

        $byCategory
            ->assertOk()
            ->assertJsonPath(
                'data.0.produce.category.name',
                'Grains'
            )
            ->assertJsonPath(
                'data.1.produce.category.name',
                'Vegetables'
            );
    }

    public function test_public_listings_are_paginated(): void
    {
        [
            $riceListing,
            $tomatoListing,
        ] = $this->seedListings();

        /*
         * Tomato costs less than rice, so with price ASC
         * it should appear on page 1 when per_page = 1.
         */
        $pageOne = $this->getJson(
            '/api/v1/listings'
            .'?sort=price'
            .'&order=asc'
            .'&per_page=1'
            .'&page=1'
        );

        $pageOne
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $tomatoListing->id
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            )
            ->assertJsonPath(
                'meta.per_page',
                1
            )
            ->assertJsonPath(
                'meta.total',
                2
            );

        /*
         * Page 2 should contain the second listing.
         */
        $pageTwo = $this->getJson(
            '/api/v1/listings'
            .'?sort=price'
            .'&order=asc'
            .'&per_page=1'
            .'&page=2'
        );

        $pageTwo
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
                'meta.current_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                2
            );
    }

    public function test_pagination_preserves_listing_query_parameters(): void
    {
        $this->seedListings();

        $response = $this->getJson(
            '/api/v1/listings'
            .'?sort=price'
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

    public function test_per_page_cannot_exceed_one_hundred(): void
    {
        $this
            ->getJson(
                '/api/v1/listings?per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'per_page',
            ]);
    }

    public function test_page_must_be_at_least_one(): void
    {
        $this
            ->getJson(
                '/api/v1/listings?page=0'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'page',
            ]);
    }

    /**
     * @return array{0: Listing, 1: Listing}
     */
    private function seedListings(): array
    {
        $grainFarmer = Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'phone_number' =>
                '08012345678',
        ]);

        $vegFarmer = Farmer::create([
            'name' =>
                'Ada Okoro',

            'state' =>
                'Lagos',

            'lga' =>
                'Ikeja',

            'status' =>
                FarmerStatus::Active,

            'phone_number' =>
                '08087654321',
        ]);

        $grains = Category::create([
            'name' =>
                'Grains',
        ]);

        $vegetables = Category::create([
            'name' =>
                'Vegetables',
        ]);

        $rice = Produce::create([
            'category_id' =>
                $grains->id,

            'name' =>
                'Rice',

            'image' =>
                base64_encode('rice'),

            'image_mime' =>
                'image/jpeg',
        ]);

        $tomato = Produce::create([
            'category_id' =>
                $vegetables->id,

            'name' =>
                'Tomato',

            'image' =>
                base64_encode('tomato'),

            'image_mime' =>
                'image/jpeg',
        ]);

        $riceListing = Listing::create([
            'farmer_id' =>
                $grainFarmer->id,

            'produce_id' =>
                $rice->id,

            'price' =>
                45000,

            'stock' =>
                50,

            'status' =>
                ListingStatus::Active,
        ]);

        $tomatoListing = Listing::create([
            'farmer_id' =>
                $vegFarmer->id,

            'produce_id' =>
                $tomato->id,

            'price' =>
                12000,

            'stock' =>
                80,

            'status' =>
                ListingStatus::Active,
        ]);

        $tomatoListing->load(
            'produce.category'
        );

        return [
            $riceListing,
            $tomatoListing,
        ];
    }
}