<?php

namespace Tests\Feature\Order;

use App\Enums\FarmerStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingCheckoutRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_cannot_order_below_listing_minimum_quantity(): void
    {
        $buyer =
            $this->createBuyer();

        $listing =
            $this->createListing([
                'minimum_order_quantity' =>
                    3,

                'stock' =>
                    20,
            ]);

        $response = $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                '/api/v1/orders',
                $this->orderPayload(
                    $listing->id,
                    2
                )
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.quantity',
            ])
            ->assertJsonFragment([
                'Minimum order quantity for this listing is 3.',
            ]);

        $this->assertDatabaseCount(
            'orders',
            0
        );

        $this->assertDatabaseHas(
            'listings',
            [
                'id' =>
                    $listing->id,

                'stock' =>
                    20,
            ]
        );
    }

    public function test_buyer_cannot_order_listing_before_available_date(): void
    {
        $buyer =
            $this->createBuyer();

        $listing =
            $this->createListing([
                'available_from' =>
                    now()
                        ->addDays(2)
                        ->toDateString(),

                'minimum_order_quantity' =>
                    1,

                'stock' =>
                    20,
            ]);

        $response = $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                '/api/v1/orders',
                $this->orderPayload(
                    $listing->id,
                    2
                )
            );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.listing_id',
            ])
            ->assertJsonFragment([
                'This listing is not available for ordering yet.',
            ]);

        $this->assertDatabaseCount(
            'orders',
            0
        );
    }

    public function test_buyer_cannot_order_pending_listing(): void
    {
        $buyer =
            $this->createBuyer();

        $listing =
            $this->createListing([
                'publication_status' =>
                    ListingPublicationStatus::Pending,

                'minimum_order_quantity' =>
                    1,

                'stock' =>
                    20,
            ]);

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                '/api/v1/orders',
                $this->orderPayload(
                    $listing->id,
                    2
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.listing_id',
            ]);

        $this->assertDatabaseCount(
            'orders',
            0
        );
    }

    public function test_order_snapshots_listing_discount_without_trusting_client_money(): void
    {
        $buyer =
            $this->createBuyer();

        $listing =
            $this->createListing([
                /*
                 * Buyer pays 45,000 instead of
                 * the original 50,000.
                 */
                'price' =>
                    45000,

                'original_price' =>
                    50000,

                'minimum_order_quantity' =>
                    2,

                'stock' =>
                    20,

                'available_from' =>
                    now()
                        ->subDay()
                        ->toDateString(),
            ]);

        $response = $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                '/api/v1/orders',
                $this->orderPayload(
                    $listing->id,
                    2
                )
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.subtotal',
                '90000.00'
            )
            ->assertJsonPath(
                'data.total',
                '90000.00'
            )
            ->assertJsonPath(
                'data.items.0.unit_price',
                '45000.00'
            )
            ->assertJsonPath(
                'data.items.0.discount_amount',
                '10000.00'
            )
            ->assertJsonPath(
                'data.items.0.line_total',
                '90000.00'
            );

        $orderId =
            $response->json(
                'data.id'
            );

        $this->assertDatabaseHas(
            'order_items',
            [
                'order_id' =>
                    $orderId,

                'listing_id' =>
                    $listing->id,

                'quantity' =>
                    2,

                'unit_price' =>
                    '45000.00',

                'discount_amount' =>
                    '10000.00',

                'line_total' =>
                    '90000.00',
            ]
        );

        $this->assertDatabaseHas(
            'orders',
            [
                'id' =>
                    $orderId,

                'subtotal' =>
                    '90000.00',

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    '90000.00',
            ]
        );

        /*
         * Stock falls only by the purchased
         * quantity.
         */
        $this->assertDatabaseHas(
            'listings',
            [
                'id' =>
                    $listing->id,

                'stock' =>
                    18,
            ]
        );
    }

    public function test_client_cannot_override_listing_discount_amount(): void
    {
        $buyer =
            $this->createBuyer();

        $listing =
            $this->createListing([
                'price' =>
                    45000,

                'original_price' =>
                    50000,

                'stock' =>
                    20,
            ]);

        $payload =
            $this->orderPayload(
                $listing->id,
                2
            );

        $payload['items'][0][
            'discount_amount'
        ] = 90000;

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                '/api/v1/orders',
                $payload
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items.0.discount_amount',
            ]);

        $this->assertDatabaseCount(
            'orders',
            0
        );
    }

    private function createBuyer(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::User,
        ]);
    }

    private function createListing(
        array $attributes = []
    ): Listing {
        $farmer =
            Farmer::create([
                'name' =>
                    'Ibrahim Musa',

                'state' =>
                    'Niger',

                'lga' =>
                    'Bida',

                'status' =>
                    FarmerStatus::Active,

                'phone_number' =>
                    fake()
                        ->unique()
                        ->numerify(
                            '080########'
                        ),
            ]);

        $category =
            Category::create([
                'name' =>
                    'Grains',
            ]);

        $produce =
            Produce::create([
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

                    'publication_status' =>
                        ListingPublicationStatus::Live,
                ],
                $attributes
            )
        );
    }

    private function orderPayload(
        int $listingId,
        int $quantity
    ): array {
        return [
            'items' => [
                [
                    'listing_id' =>
                        $listingId,

                    'quantity' =>
                        $quantity,
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
                'Checkout rule test.',
        ];
    }
}