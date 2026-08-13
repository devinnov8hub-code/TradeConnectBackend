<?php

namespace Tests\Feature\Listing;

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

class ListingUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_listing_exposes_unit(): void
    {
        $listing = $this->createListing(
            'bag'
        );

        $this
            ->getJson(
                "/api/v1/listings/{$listing->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.unit',
                'bag'
            )
            ->assertJsonPath(
                'data.price',
                '45000.00'
            );
    }

    public function test_order_snapshots_listing_unit(): void
    {
        $listing = $this->createListing(
            'bag'
        );

        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $token = auth('api')->login(
            $buyer
        );

        $response = $this
            ->withToken($token)
            ->postJson(
                '/api/v1/orders',
                [
                    'items' => [
                        [
                            'listing_id' =>
                                $listing->id,

                            'quantity' =>
                                2,
                        ],
                    ],

                    'delivery_method' =>
                        'standard',

                    'delivery_name' =>
                        'Test Buyer',

                    'delivery_phone' =>
                        '08012345678',

                    'delivery_state' =>
                        'Lagos',

                    'delivery_lga' =>
                        'Ikeja',

                    'delivery_address' =>
                        '12 Allen Avenue, Ikeja',

                    'delivery_notes' =>
                        'Unit snapshot test',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.items.0.unit',
                'bag'
            );

        $orderId = $response->json(
            'data.id'
        );

        $this->assertDatabaseHas(
            'order_items',
            [
                'order_id' =>
                    $orderId,

                'listing_id' =>
                    $listing->id,

                'unit' =>
                    'bag',

                'quantity' =>
                    2,
            ]
        );

        /*
         * Change the current marketplace listing.
         */
        $listing->update([
            'unit' =>
                'kg',
        ]);

        /*
         * Historical order remains "bag".
         */
        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/orders/{$orderId}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.unit',
                'bag'
            );

        $this->assertDatabaseHas(
            'order_items',
            [
                'order_id' =>
                    $orderId,

                'unit' =>
                    'bag',
            ]
        );
    }

    private function createListing(
        string $unit
    ): Listing {
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

        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                45000,

            'unit' =>
                $unit,

            'stock' =>
                100,

            'status' =>
                ListingStatus::Active,
        ]);
    }
}