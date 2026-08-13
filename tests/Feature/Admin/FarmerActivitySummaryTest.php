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
use App\Models\OrderItem;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerActivitySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_detail_exposes_profile_and_performance_summary(): void
    {
        $farmer =
            $this->createFarmer(
                '08011111111',
                true
            );

        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $liveListing =
            $this->createListing(
                $farmer,
                'Rice',
                ListingPublicationStatus::Live
            );

        $this->createListing(
            $farmer,
            'Maize',
            ListingPublicationStatus::Pending
        );

        $this->createListing(
            $farmer,
            'Beans',
            ListingPublicationStatus::Inactive
        );

        $this->createOrder(
            $buyer,
            $liveListing,
            'ORD-SUM-000001',
            OrderStatus::New,
            PaymentStatus::Pending,
            '10000.00'
        );

        $this->createOrder(
            $buyer,
            $liveListing,
            'ORD-SUM-000002',
            OrderStatus::InTransit,
            PaymentStatus::Pending,
            '20000.00'
        );

        $this->createOrder(
            $buyer,
            $liveListing,
            'ORD-SUM-000003',
            OrderStatus::Delivered,
            PaymentStatus::Paid,
            '30000.00'
        );

        $this->createOrder(
            $buyer,
            $liveListing,
            'ORD-SUM-000004',
            OrderStatus::Cancelled,
            PaymentStatus::Pending,
            '40000.00'
        );

        $response =
            $this
                ->withToken(
                    $this->adminToken()
                )
                ->getJson(
                    "/api/v1/admin/farmers/{$farmer->id}"
                );

        $response
            ->assertOk()

            ->assertJsonPath(
                'data.can_publish_listings',
                true
            )

            ->assertJsonPath(
                'data.profile_completeness.percentage',
                100
            )

            ->assertJsonPath(
                'data.profile_completeness.completed_fields',
                13
            )

            ->assertJsonPath(
                'data.profile_completeness.total_fields',
                13
            )

            ->assertJsonCount(
                0,
                'data.profile_completeness.missing_fields'
            )

            ->assertJsonPath(
                'data.summary.listings.total',
                3
            )

            ->assertJsonPath(
                'data.summary.listings.live',
                1
            )

            ->assertJsonPath(
                'data.summary.listings.pending',
                1
            )

            ->assertJsonPath(
                'data.summary.listings.inactive',
                1
            )

            ->assertJsonPath(
                'data.summary.orders.total',
                4
            )

            ->assertJsonPath(
                'data.summary.orders.new',
                1
            )

            ->assertJsonPath(
                'data.summary.orders.in_transit',
                1
            )

            ->assertJsonPath(
                'data.summary.orders.delivered',
                1
            )

            ->assertJsonPath(
                'data.summary.orders.cancelled',
                1
            )

            ->assertJsonPath(
                'data.summary.sales.paid_orders_count',
                1
            )

            ->assertJsonPath(
                'data.summary.sales.total_earned',
                '30000.00'
            )

            /*
             * Legacy overview fields remain available.
             */
            ->assertJsonPath(
                'data.orders_count',
                4
            )

            ->assertJsonPath(
                'data.completed_orders_count',
                1
            )

            ->assertJsonPath(
                'data.total_earned',
                '30000.00'
            )

            ->assertJsonPath(
                'data.preview_limit',
                5
            )

            ->assertJsonCount(
                3,
                'data.listings'
            )

            ->assertJsonCount(
                4,
                'data.orders'
            );
    }

    public function test_farmer_previews_are_limited_and_multi_farmer_amount_is_scoped(): void
    {
        $farmer =
            $this->createFarmer(
                '08011111111'
            );

        $otherFarmer =
            $this->createFarmer(
                '08022222222'
            );

        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $farmerListings =
            collect();

        foreach (
            range(1, 6)
            as $index
        ) {
            $farmerListings->push(
                $this->createListing(
                    $farmer,
                    "Produce {$index}",
                    ListingPublicationStatus::Live
                )
            );
        }

        $otherListing =
            $this->createListing(
                $otherFarmer,
                'Other Farmer Produce',
                ListingPublicationStatus::Live
            );

        /*
         * Five ordinary farmer orders.
         */
        foreach (
            range(1, 5)
            as $index
        ) {
            $this->createOrder(
                $buyer,
                $farmerListings->first(),
                sprintf(
                    'ORD-PREVIEW-%06d',
                    $index
                ),
                OrderStatus::New,
                PaymentStatus::Pending,
                '10000.00'
            );
        }

        /*
         * Sixth and most recent order contains items
         * from two farmers.
         */
        $multiFarmerOrder =
            Order::create([
                'user_id' =>
                    $buyer->id,

                'listing_id' =>
                    $farmerListings
                        ->first()
                        ->id,

                'quantity' =>
                    1,

                'order_number' =>
                    'ORD-PREVIEW-000006',

                'subtotal' =>
                    '100000.00',

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    '100000.00',

                'status' =>
                    OrderStatus::New,

                'payment_status' =>
                    PaymentStatus::Pending,
            ]);

        $this->createOrderItem(
            $multiFarmerOrder,
            $farmerListings->first(),
            $farmer,
            '60000.00'
        );

        $this->createOrderItem(
            $multiFarmerOrder,
            $otherListing,
            $otherFarmer,
            '40000.00'
        );

        $response =
            $this
                ->withToken(
                    $this->adminToken()
                )
                ->getJson(
                    "/api/v1/admin/farmers/{$farmer->id}"
                );

        $response
            ->assertOk()

            /*
             * Six exist, but only five are embedded
             * in the profile preview.
             */
            ->assertJsonPath(
                'data.summary.listings.total',
                6
            )

            ->assertJsonCount(
                5,
                'data.listings'
            )

            ->assertJsonPath(
                'data.summary.orders.total',
                6
            )

            ->assertJsonCount(
                5,
                'data.orders'
            )

            /*
             * Most recent order is the multi-farmer
             * checkout.
             */
            ->assertJsonPath(
                'data.orders.0.id',
                $multiFarmerOrder->id
            )

            ->assertJsonPath(
                'data.orders.0.parent_order_total',
                '100000.00'
            )

            /*
             * The farmer view must show only their
             * own 60,000 line.
             */
            ->assertJsonPath(
                'data.orders.0.farmer_total',
                '60000.00'
            )

            ->assertJsonPath(
                'data.orders.0.farmer_items_count',
                1
            )

            ->assertJsonCount(
                1,
                'data.orders.0.items'
            );
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

    private function createFarmer(
        string $phoneNumber,
        bool $completeProfile = false
    ): Farmer {
        $attributes = [
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

            'verified_at' =>
                now(),

            'phone_number' =>
                $phoneNumber,
        ];

        if (
            $completeProfile
        ) {
            $attributes = [
                ...$attributes,

                'email' =>
                    'ibrahim@example.com',

                'address' =>
                    '12 Market Road, Bida',

                'gender' =>
                    'Male',

                'date_of_birth' =>
                    '1985-04-12',

                'farm_name' =>
                    'Musa Farms',

                'farm_size_hectares' =>
                    12.5,

                'farming_method' =>
                    'Mixed farming',

                'years_experience' =>
                    14,

                'farm_address' =>
                    'Bida Agricultural Zone',
            ];
        }

        return Farmer::create(
            $attributes
        );
    }

    private function createListing(
        Farmer $farmer,
        string $produceName,
        ListingPublicationStatus $publicationStatus
    ): Listing {
        $category =
            Category::firstOrCreate([
                'name' =>
                    'General Produce',
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

        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                '10000.00',

            'unit' =>
                'bag',

            'stock' =>
                100,

            'minimum_order_quantity' =>
                1,

            'publication_status' =>
                $publicationStatus,
        ]);
    }

    private function createOrder(
        User $buyer,
        Listing $listing,
        string $orderNumber,
        OrderStatus $status,
        PaymentStatus $paymentStatus,
        string $lineTotal
    ): Order {
        $order =
            Order::create([
                'user_id' =>
                    $buyer->id,

                'listing_id' =>
                    $listing->id,

                'quantity' =>
                    1,

                'order_number' =>
                    $orderNumber,

                'subtotal' =>
                    $lineTotal,

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    $lineTotal,

                'status' =>
                    $status,

                'payment_status' =>
                    $paymentStatus,
            ]);

        $this->createOrderItem(
            $order,
            $listing,
            $listing->farmer,
            $lineTotal
        );

        return $order;
    }

    private function createOrderItem(
        Order $order,
        Listing $listing,
        Farmer $farmer,
        string $lineTotal
    ): OrderItem {
        $listing->loadMissing(
            'produce.category'
        );

        return OrderItem::create([
            'order_id' =>
                $order->id,

            'listing_id' =>
                $listing->id,

            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $listing->produce_id,

            'produce_name' =>
                $listing
                    ->produce
                    ->name,

            'category_name' =>
                $listing
                    ->produce
                    ->category
                    ->name,

            'unit' =>
                $listing->unit,

            'quantity' =>
                1,

            'unit_price' =>
                $lineTotal,

            'discount_amount' =>
                '0.00',

            'line_total' =>
                $lineTotal,
        ]);
    }
}