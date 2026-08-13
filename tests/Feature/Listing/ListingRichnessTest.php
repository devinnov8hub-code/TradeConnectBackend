<?php

namespace Tests\Feature\Listing;

use App\Enums\FarmerStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingRichnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_rich_pending_listing(): void
    {
        $token =
            $this->adminToken();

        $farmer =
            $this->createFarmer();

        $produce =
            $this->createProduce();

        $availableFrom =
            now()
                ->subDay()
                ->toDateString();

        $response = $this
            ->withToken($token)
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                [
                    'produce_id' =>
                        $produce->id,

                    'price' =>
                        45000,

                    'original_price' =>
                        50000,

                    'unit' =>
                        'bag',

                    'stock' =>
                        100,

                    'minimum_order_quantity' =>
                        2,

                    'description' =>
                        'Premium locally grown rice from Niger State.',

                    'label' =>
                        'organic',

                    'grade' =>
                        'Grade A - Premium',

                    'available_from' =>
                        $availableFrom,

                    'publication_status' =>
                        ListingPublicationStatus::Pending
                            ->value,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.price',
                '45000.00'
            )
            ->assertJsonPath(
                'data.original_price',
                '50000.00'
            )
            ->assertJsonPath(
                'data.discount_percent',
                '10.00'
            )
            ->assertJsonPath(
                'data.discount_amount',
                '5000.00'
            )
            ->assertJsonPath(
                'data.minimum_order_quantity',
                '2.00'
            )
            ->assertJsonPath(
                'data.description',
                'Premium locally grown rice from Niger State.'
            )
            ->assertJsonPath(
                'data.label',
                'organic'
            )
            ->assertJsonPath(
                'data.grade',
                'Grade A - Premium'
            )
            ->assertJsonPath(
                'data.available_from',
                $availableFrom
            )
            ->assertJsonPath(
                'data.publication_status',
                'pending'
            )
            ->assertJsonPath(
                'data.status',
                'inactive'
            )
            ->assertJsonPath(
                'data.is_available',
                false
            );

        $listingId =
            $response->json(
                'data.id'
            );

        $this->assertDatabaseHas(
            'listings',
            [
                'id' =>
                    $listingId,

                'publication_status' =>
                    'pending',

                'status' =>
                    'inactive',

                'label' =>
                    'organic',

                'grade' =>
                    'Grade A - Premium',

                'minimum_order_quantity' =>
                    '2.00',

                'original_price' =>
                    '50000.00',

                'discount_percent' =>
                    '10.00',
            ]
        );

        /*
         * Pending listings must not appear through
         * the existing public detail endpoint.
         */
        $this
            ->getJson(
                "/api/v1/listings/{$listingId}"
            )
            ->assertNotFound();
    }

    public function test_admin_can_publish_listing_with_partial_patch(): void
    {
        $token =
            $this->adminToken();

        $farmer =
            $this->createFarmer();

        $produce =
            $this->createProduce();

        $listing = Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                45000,

            'original_price' =>
                50000,

            'unit' =>
                'bag',

            'stock' =>
                100,

            'minimum_order_quantity' =>
                2,

            'description' =>
                'Premium rice.',

            'label' =>
                'fresh',

            'grade' =>
                'Grade A',

            'available_from' =>
                now()
                    ->subDay()
                    ->toDateString(),

            'publication_status' =>
                ListingPublicationStatus::Pending,
        ]);

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/listings/{$listing->id}",
                [
                    'publication_status' =>
                        ListingPublicationStatus::Live
                            ->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.publication_status',
                'live'
            )
            ->assertJsonPath(
                'data.status',
                'active'
            )
            ->assertJsonPath(
                'data.is_available',
                true
            );

        $listing->refresh();

        $this->assertSame(
            ListingPublicationStatus::Live,
            $listing->publication_status
        );

        $this->assertSame(
            ListingStatus::Active,
            $listing->status
        );

        $this->assertNotNull(
            $listing->published_at
        );

        /*
         * Publishing automatically makes the listing
         * visible through the existing marketplace.
         */
        $this
            ->getJson(
                "/api/v1/listings/{$listing->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.publication_status',
                'live'
            )
            ->assertJsonPath(
                'data.price',
                '45000.00'
            )
            ->assertJsonPath(
                'data.original_price',
                '50000.00'
            )
            ->assertJsonPath(
                'data.discount_percent',
                '10.00'
            )
            ->assertJsonPath(
                'data.label',
                'fresh'
            )
            ->assertJsonPath(
                'data.grade',
                'Grade A'
            );
    }

    public function test_invalid_listing_pricing_and_label_are_rejected(): void
    {
        $token =
            $this->adminToken();

        $farmer =
            $this->createFarmer();

        $produce =
            $this->createProduce();

        $this
            ->withToken($token)
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                [
                    'produce_id' =>
                        $produce->id,

                    'price' =>
                        45000,

                    /*
                     * Cannot be less than
                     * current selling price.
                     */
                    'original_price' =>
                        40000,

                    /*
                     * Also inconsistent with
                     * supplied prices.
                     */
                    'discount_percent' =>
                        25,

                    'unit' =>
                        'bag',

                    'stock' =>
                        100,

                    'minimum_order_quantity' =>
                        1,

                    'label' =>
                        'premium',

                    'publication_status' =>
                        'live',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'original_price',
                'label',
            ]);
    }

    public function test_discount_percent_must_match_prices_when_supplied(): void
    {
        $token =
            $this->adminToken();

        $farmer =
            $this->createFarmer();

        $produce =
            $this->createProduce();

        $this
            ->withToken($token)
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                [
                    'produce_id' =>
                        $produce->id,

                    'price' =>
                        45000,

                    'original_price' =>
                        50000,

                    /*
                     * Correct value would be 10%.
                     */
                    'discount_percent' =>
                        20,

                    'unit' =>
                        'bag',

                    'stock' =>
                        100,

                    'publication_status' =>
                        'live',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'discount_percent',
            ]);
    }

    public function test_legacy_active_status_maps_to_live_publication(): void
    {
        $token =
            $this->adminToken();

        $farmer =
            $this->createFarmer();

        $produce =
            $this->createProduce();

        /*
         * Existing frontend/API clients do not yet
         * know about publication_status.
         */
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
                        100,

                    'status' =>
                        ListingStatus::Active
                            ->value,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.status',
                'active'
            )
            ->assertJsonPath(
                'data.publication_status',
                'live'
            );

        $listingId =
            $response->json(
                'data.id'
            );

        $this
            ->getJson(
                "/api/v1/listings/{$listingId}"
            )
            ->assertOk();
    }

    private function adminToken(): string
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        return auth('api')->login(
            $admin
        );
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

            'phone_number' =>
                '08012345678',
        ]);
    }

    private function createProduce(): Produce
    {
        $category =
            Category::create([
                'name' =>
                    'Grains',
            ]);

        return Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                'Rice',

            'image' =>
                base64_encode(
                    'rice'
                ),

            'image_mime' =>
                'image/jpeg',
        ]);
    }
}