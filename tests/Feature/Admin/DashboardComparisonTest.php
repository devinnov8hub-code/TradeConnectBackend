<?php

namespace Tests\Feature\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_returns_month_to_date_comparison_percentages(): void
    {
        /*
         * Previous comparison period:
         * July 1 - July 17.
         */
        Carbon::setTestNow(
            '2026-07-05 09:00:00'
        );

        $previousBuyerOne =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $previousFarmerOne =
            $this->createFarmer(
                'Previous Farmer One'
            );

        $this->createFarmer(
            'Previous Farmer Two'
        );

        $category =
            Category::create([
                'name' =>
                    'Grains',
            ]);

        $rice =
            $this->createProduce(
                $category,
                'Rice'
            );

        $maize =
            $this->createProduce(
                $category,
                'Maize'
            );

        $beans =
            $this->createProduce(
                $category,
                'Beans'
            );

        $previousListing =
            $this->createListing(
                $previousFarmerOne,
                $rice
            );

        /*
         * Previous period:
         * 2 orders.
         */
        $this->createOrder(
            $previousBuyerOne,
            $previousListing,
            'ORD-COMP-PREV-001'
        );

        $this->createOrder(
            $previousBuyerOne,
            $previousListing,
            'ORD-COMP-PREV-002'
        );

        /*
         * Current comparison period:
         * August 1 - August 17.
         */
        Carbon::setTestNow(
            '2026-08-05 09:00:00'
        );

        $currentBuyerOne =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $currentFarmer =
            $this->createFarmer(
                'Current Farmer'
            );

        /*
         * Current period:
         * 2 listings versus 1 previously.
         */
        $currentListingOne =
            $this->createListing(
                $currentFarmer,
                $maize
            );

        $this->createListing(
            $currentFarmer,
            $beans
        );

        /*
         * Current period:
         * 3 orders versus 2 previously.
         */
        $this->createOrder(
            $currentBuyerOne,
            $currentListingOne,
            'ORD-COMP-CURR-001'
        );

        $this->createOrder(
            $currentBuyerOne,
            $currentListingOne,
            'ORD-COMP-CURR-002'
        );

        $this->createOrder(
            $currentBuyerOne,
            $currentListingOne,
            'ORD-COMP-CURR-003'
        );

        /*
         * Evaluate the dashboard on August 17.
         */
        Carbon::setTestNow(
            '2026-08-17 10:00:00'
        );

        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/dashboard'
            )
            ->assertOk()

            /*
             * Orders:
             * 3 vs 2 = +50%.
             */
            ->assertJsonPath(
                'data.orders_change_percent',
                50
            )

            /*
             * Listings:
             * 2 vs 1 = +100%.
             */
            ->assertJsonPath(
                'data.listings_change_percent',
                100
            )

            /*
             * Farmers:
             * 1 vs 2 = -50%.
             */
            ->assertJsonPath(
                'data.farmers_change_percent',
                -50
            )

            /*
             * Buyers:
             * 4 vs 2 = +100%.
             */
            ->assertJsonPath(
                'data.buyers_change_percent',
                100
            )
            ->assertJsonPath(
                'data.comparison.basis',
                'month_to_date_vs_previous_month_to_date'
            );

        $response =
            $this
                ->withToken($token)
                ->getJson(
                    '/api/v1/admin/dashboard'
                );

        $this->assertStringStartsWith(
            '2026-08-01',
            $response->json(
                'data.comparison.current_period.start'
            )
        );

        $this->assertStringStartsWith(
            '2026-08-17',
            $response->json(
                'data.comparison.current_period.end'
            )
        );

        $this->assertStringStartsWith(
            '2026-07-01',
            $response->json(
                'data.comparison.previous_period.start'
            )
        );

        $this->assertStringStartsWith(
            '2026-07-17',
            $response->json(
                'data.comparison.previous_period.end'
            )
        );
    }

    public function test_comparison_percentage_is_null_when_previous_period_is_zero(): void
    {
        Carbon::setTestNow(
            '2026-08-10 10:00:00'
        );

        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        /*
         * One current-period buyer but zero previous-period
         * buyers means percentage growth is undefined.
         */
        User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/dashboard'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.buyers_change_percent',
                null
            )
            ->assertJsonPath(
                'data.orders_change_percent',
                0
            )
            ->assertJsonPath(
                'data.listings_change_percent',
                0
            )
            ->assertJsonPath(
                'data.farmers_change_percent',
                0
            );
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

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'verified_at' =>
                now(),

            'phone_number' =>
                fake()
                    ->unique()
                    ->numerify(
                        '080########'
                    ),
        ]);
    }

    private function createProduce(
        Category $category,
        string $name
    ): Produce {
        return Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                $name,

            'image' =>
                base64_encode(
                    strtolower(
                        $name
                    )
                ),

            'image_mime' =>
                'image/jpeg',
        ]);
    }

    private function createListing(
        Farmer $farmer,
        Produce $produce
    ): Listing {
        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                '25000.00',

            'unit' =>
                'bag',

            'stock' =>
                100,

            'minimum_order_quantity' =>
                1,

            'publication_status' =>
                ListingPublicationStatus::Live,
        ]);
    }

    private function createOrder(
        User $buyer,
        Listing $listing,
        string $orderNumber
    ): Order {
        return Order::create([
            'user_id' =>
                $buyer->id,

            'listing_id' =>
                $listing->id,

            'quantity' =>
                1,

            'order_number' =>
                $orderNumber,

            'subtotal' =>
                '25000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '25000.00',

            'status' =>
                OrderStatus::New,

            'payment_status' =>
                PaymentStatus::Pending,

            'placed_at' =>
                now(),
        ]);
    }
}