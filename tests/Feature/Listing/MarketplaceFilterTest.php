<?php

namespace Tests\Feature\Listing;

use App\Enums\FarmerStatus;
use App\Enums\ListingPublicationStatus;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_only_returns_live_listings_from_active_farmers(): void
    {
        $activeFarmer =
            $this->createFarmer(
                'Ibrahim Musa',
                'Niger',
                'Bida',
                FarmerStatus::Active
            );

        $inactiveFarmer =
            $this->createFarmer(
                'Inactive Farmer',
                'Kaduna',
                'Zaria',
                FarmerStatus::Inactive
            );

        $liveListing =
            $this->createListing(
                $activeFarmer,
                'Rice',
                'Grains',
                [
                    'publication_status' =>
                        ListingPublicationStatus::Live,
                ]
            );

        $pendingListing =
            $this->createListing(
                $activeFarmer,
                'Maize',
                'Grains',
                [
                    'publication_status' =>
                        ListingPublicationStatus::Pending,
                ]
            );

        $inactiveFarmerListing =
            $this->createListing(
                $inactiveFarmer,
                'Tomato',
                'Vegetables',
                [
                    'publication_status' =>
                        ListingPublicationStatus::Live,
                ]
            );

        $this
            ->getJson(
                '/api/v1/listings'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $liveListing->id
            );

        $this
            ->getJson(
                "/api/v1/listings/{$pendingListing->id}"
            )
            ->assertNotFound();

        $this
            ->getJson(
                "/api/v1/listings/{$inactiveFarmerListing->id}"
            )
            ->assertNotFound();
    }

    public function test_marketplace_can_filter_by_state_and_lga(): void
    {
        $nigerFarmer =
            $this->createFarmer(
                'Ibrahim Musa',
                'Niger',
                'Bida'
            );

        $lagosFarmer =
            $this->createFarmer(
                'Ada Okoro',
                'Lagos',
                'Ikeja'
            );

        $nigerListing =
            $this->createListing(
                $nigerFarmer,
                'Rice',
                'Grains'
            );

        $this->createListing(
            $lagosFarmer,
            'Tomato',
            'Vegetables'
        );

        $this
            ->getJson(
                '/api/v1/listings'
                .'?state=Niger'
                .'&lga=Bida'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $nigerListing->id
            )
            ->assertJsonPath(
                'data.0.farmer.state',
                'Niger'
            )
            ->assertJsonPath(
                'data.0.farmer.lga',
                'Bida'
            );
    }

    public function test_marketplace_can_filter_by_label(): void
    {
        $farmer =
            $this->createFarmer(
                'Ibrahim Musa',
                'Niger',
                'Bida'
            );

        $organic =
            $this->createListing(
                $farmer,
                'Rice',
                'Grains',
                [
                    'label' =>
                        'organic',
                ]
            );

        $this->createListing(
            $farmer,
            'Maize',
            'Grains',
            [
                'label' =>
                    'seasonal',
            ]
        );

        $this
            ->getJson(
                '/api/v1/listings'
                .'?label=organic'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $organic->id
            )
            ->assertJsonPath(
                'data.0.label',
                'organic'
            );
    }

    public function test_marketplace_can_filter_by_availability(): void
    {
        $farmer =
            $this->createFarmer(
                'Ibrahim Musa',
                'Niger',
                'Bida'
            );

        $available =
            $this->createListing(
                $farmer,
                'Rice',
                'Grains',
                [
                    'available_from' =>
                        now()
                            ->subDay()
                            ->toDateString(),

                    'minimum_order_quantity' =>
                        2,

                    'stock' =>
                        10,
                ]
            );

        $upcoming =
            $this->createListing(
                $farmer,
                'Maize',
                'Grains',
                [
                    'available_from' =>
                        now()
                            ->addDays(5)
                            ->toDateString(),

                    'minimum_order_quantity' =>
                        2,

                    'stock' =>
                        10,
                ]
            );

        $outOfStock =
            $this->createListing(
                $farmer,
                'Beans',
                'Grains',
                [
                    'available_from' =>
                        now()
                            ->subDay()
                            ->toDateString(),

                    'minimum_order_quantity' =>
                        3,

                    'stock' =>
                        1,
                ]
            );

        $this
            ->getJson(
                '/api/v1/listings'
                .'?availability=available'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $available->id
            )
            ->assertJsonPath(
                'data.0.is_available',
                true
            );

        $this
            ->getJson(
                '/api/v1/listings'
                .'?availability=upcoming'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $upcoming->id
            )
            ->assertJsonPath(
                'data.0.is_available',
                false
            );

        $this
            ->getJson(
                '/api/v1/listings'
                .'?availability=out_of_stock'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $outOfStock->id
            )
            ->assertJsonPath(
                'data.0.is_available',
                false
            );
    }

    public function test_marketplace_can_filter_by_price_range(): void
    {
        $farmer =
            $this->createFarmer(
                'Ibrahim Musa',
                'Niger',
                'Bida'
            );

        $cheap =
            $this->createListing(
                $farmer,
                'Tomato',
                'Vegetables',
                [
                    'price' =>
                        12000,
                ]
            );

        $middle =
            $this->createListing(
                $farmer,
                'Rice',
                'Grains',
                [
                    'price' =>
                        45000,
                ]
            );

        $expensive =
            $this->createListing(
                $farmer,
                'Beans',
                'Grains',
                [
                    'price' =>
                        80000,
                ]
            );

        $response = $this
            ->getJson(
                '/api/v1/listings'
                .'?min_price=20000'
                .'&max_price=50000'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $middle->id
            );

        $ids = collect(
            $response->json(
                'data'
            )
        )->pluck('id');

        $this->assertFalse(
            $ids->contains(
                $cheap->id
            )
        );

        $this->assertFalse(
            $ids->contains(
                $expensive->id
            )
        );
    }

    public function test_marketplace_searches_rich_listing_fields(): void
    {
        $farmer =
            $this->createFarmer(
                'Ibrahim Musa',
                'Niger',
                'Bida'
            );

        $rice =
            $this->createListing(
                $farmer,
                'Rice',
                'Grains',
                [
                    'description' =>
                        'Chemical-free locally grown grain.',

                    'grade' =>
                        'Grade A Premium',

                    'label' =>
                        'organic',
                ]
            );

        $this->createListing(
            $farmer,
            'Tomato',
            'Vegetables',
            [
                'description' =>
                    'Fresh red tomatoes.',

                'grade' =>
                    'Grade B',

                'label' =>
                    'fresh',
            ]
        );

        $this
            ->getJson(
                '/api/v1/listings'
                .'?search=Chemical-free'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $rice->id
            );

        $this
            ->getJson(
                '/api/v1/listings'
                .'?search=Premium'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $rice->id
            );

        $this
            ->getJson(
                '/api/v1/listings'
                .'?search=organic'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $rice->id
            );
    }

    public function test_marketplace_pagination_preserves_rich_filters(): void
    {
        $farmer =
            $this->createFarmer(
                'Ibrahim Musa',
                'Niger',
                'Bida'
            );

        $this->createListing(
            $farmer,
            'Rice',
            'Grains',
            [
                'label' =>
                    'organic',

                'price' =>
                    30000,
            ]
        );

        $this->createListing(
            $farmer,
            'Maize',
            'Grains',
            [
                'label' =>
                    'organic',

                'price' =>
                    40000,
            ]
        );

        $response = $this
            ->getJson(
                '/api/v1/listings'
                .'?state=Niger'
                .'&label=organic'
                .'&min_price=20000'
                .'&max_price=50000'
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
            'state=Niger',
            $next
        );

        $this->assertStringContainsString(
            'label=organic',
            $next
        );

        $this->assertStringContainsString(
            'min_price=20000',
            $next
        );

        $this->assertStringContainsString(
            'max_price=50000',
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

    public function test_invalid_marketplace_filters_are_rejected(): void
    {
        $this
            ->getJson(
                '/api/v1/listings'
                .'?label=premium'
                .'&availability=soon'
                .'&min_price=50000'
                .'&max_price=10000'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'label',
                'availability',
                'max_price',
            ]);
    }

    private function createFarmer(
        string $name,
        string $state,
        string $lga,
        FarmerStatus $status =
            FarmerStatus::Active
    ): Farmer {
        return Farmer::create([
            'name' =>
                $name,

            'state' =>
                $state,

            'lga' =>
                $lga,

            'status' =>
                $status,

            'phone_number' =>
                fake()
                    ->unique()
                    ->numerify(
                        '080########'
                    ),
        ]);
    }

    private function createListing(
        Farmer $farmer,
        string $produceName,
        string $categoryName,
        array $attributes = []
    ): Listing {
        $category =
            Category::firstOrCreate([
                'name' =>
                    $categoryName,
            ]);

        $produce =
            Produce::create([
                'category_id' =>
                    $category->id,

                'name' =>
                    $produceName,

                'image' =>
                    base64_encode(
                        strtolower(
                            $produceName
                        )
                    ),

                'image_mime' =>
                    'image/jpeg',
            ]);

        return Listing::create(
            array_merge(
                [
                    'farmer_id' =>
                        $farmer->id,

                    'produce_id' =>
                        $produce->id,

                    'price' =>
                        45000,

                    'unit' =>
                        'bag',

                    'stock' =>
                        100,

                    'minimum_order_quantity' =>
                        1,

                    'available_from' =>
                        now()
                            ->subDay()
                            ->toDateString(),

                    'publication_status' =>
                        ListingPublicationStatus::Live,
                ],
                $attributes
            )
        );
    }
}