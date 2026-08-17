<?php

namespace Tests\Feature\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_expanded_dashboard_summary(): void
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        /*
         * Active buyer registered this week.
         */
        $currentBuyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        /*
         * Active buyer registered before the current week.
         */
        $olderBuyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $olderBuyer
            ->forceFill([
                'created_at' =>
                    now()->subWeeks(2),
            ])
            ->saveQuietly();

        /*
         * A newly-registered but inactive buyer still counts
         * toward new registrations, but not active buyers.
         */
        $inactiveBuyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $inactiveBuyer
            ->forceFill([
                'status' =>
                    UserStatus::Inactive,
            ])
            ->saveQuietly();

        /*
         * One active farmer awaiting verification.
         */
        $pendingFarmer =
            Farmer::create([
                'name' =>
                    'Pending Farmer',

                'state' =>
                    'Niger',

                'lga' =>
                    'Bida',

                'status' =>
                    FarmerStatus::Active,

                'verification_status' =>
                    FarmerVerificationStatus::Pending,

                'phone_number' =>
                    '08011111111',
            ]);

        /*
         * This farmer is verified but inactive.
         */
        Farmer::create([
            'name' =>
                'Inactive Farmer',

            'state' =>
                'Lagos',

            'lga' =>
                'Ikeja',

            'status' =>
                FarmerStatus::Inactive,

            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'verified_at' =>
                now(),

            'phone_number' =>
                '08022222222',
        ]);

        $category =
            Category::create([
                'name' =>
                    'Grains',
            ]);

        $rice =
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

        $maize =
            Produce::create([
                'category_id' =>
                    $category->id,

                'name' =>
                    'Maize',

                'image' =>
                    base64_encode(
                        'maize'
                    ),

                'image_mime' =>
                    'image/jpeg',
            ]);

        /*
         * One pending listing.
         */
        $pendingListing =
            Listing::create([
                'farmer_id' =>
                    $pendingFarmer->id,

                'produce_id' =>
                    $rice->id,

                'price' =>
                    '45000.00',

                'unit' =>
                    'bag',

                'stock' =>
                    20,

                'minimum_order_quantity' =>
                    1,

                'publication_status' =>
                    ListingPublicationStatus::Pending,
            ]);

        /*
         * One live listing.
         *
         * Direct model creation is intentional here: the
         * dashboard test is exercising aggregation rather
         * than the publication eligibility endpoint.
         */
        $liveListing =
            Listing::create([
                'farmer_id' =>
                    $pendingFarmer->id,

                'produce_id' =>
                    $maize->id,

                'price' =>
                    '30000.00',

                'unit' =>
                    'bag',

                'stock' =>
                    30,

                'minimum_order_quantity' =>
                    1,

                'publication_status' =>
                    ListingPublicationStatus::Live,
            ]);

        /*
         * One order placed today.
         */
        Order::create([
            'user_id' =>
                $currentBuyer->id,

            'listing_id' =>
                $liveListing->id,

            'quantity' =>
                1,

            'order_number' =>
                'ORD-DASH-000001',

            'subtotal' =>
                '30000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '30000.00',

            'status' =>
                OrderStatus::New,

            'payment_status' =>
                PaymentStatus::Pending,

            'placed_at' =>
                now(),
        ]);

        /*
         * One historical order that must increase total
         * orders without increasing orders_today.
         */
        Order::create([
            'user_id' =>
                $olderBuyer->id,

            'listing_id' =>
                $pendingListing->id,

            'quantity' =>
                1,

            'order_number' =>
                'ORD-DASH-000002',

            'subtotal' =>
                '45000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '45000.00',

            'status' =>
                OrderStatus::Delivered,

            'payment_status' =>
                PaymentStatus::Paid,

            'placed_at' =>
                now()->subDays(2),

            'paid_at' =>
                now()->subDays(2),

            'delivered_at' =>
                now()->subDay(),
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
                'data.total_orders',
                2
            )
            ->assertJsonPath(
                'data.orders_today',
                1
            )
            ->assertJsonPath(
                'data.total_listings',
                2
            )
            ->assertJsonPath(
                'data.pending_listings',
                1
            )
            ->assertJsonPath(
                'data.active_farmers',
                1
            )
            ->assertJsonPath(
                'data.pending_farmer_verifications',
                1
            )
            ->assertJsonPath(
                'data.active_buyers',
                2
            )
            ->assertJsonPath(
                'data.active_users',
                2
            )
            ->assertJsonPath(
                'data.new_buyers_this_week',
                2
            );
    }

    public function test_orders_today_uses_created_at_for_legacy_order_without_placed_at(): void
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $farmer =
            Farmer::create([
                'name' =>
                    'Legacy Farmer',

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
                    '08033333333',
            ]);

        $category =
            Category::create([
                'name' =>
                    'Vegetables',
            ]);

        $produce =
            Produce::create([
                'category_id' =>
                    $category->id,

                'name' =>
                    'Tomato',

                'image' =>
                    base64_encode(
                        'tomato'
                    ),

                'image_mime' =>
                    'image/jpeg',
            ]);

        $listing =
            Listing::create([
                'farmer_id' =>
                    $farmer->id,

                'produce_id' =>
                    $produce->id,

                'price' =>
                    '15000.00',

                'unit' =>
                    'basket',

                'stock' =>
                    15,

                'minimum_order_quantity' =>
                    1,

                'publication_status' =>
                    ListingPublicationStatus::Live,
            ]);

        /*
         * Simulates a legacy record created before
         * placed_at became part of the order contract.
         */
        Order::create([
            'user_id' =>
                $buyer->id,

            'listing_id' =>
                $listing->id,

            'quantity' =>
                1,

            'order_number' =>
                'ORD-DASH-LEGACY-001',

            'subtotal' =>
                '15000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '15000.00',

            'status' =>
                OrderStatus::New,

            'payment_status' =>
                PaymentStatus::Pending,

            'placed_at' =>
                null,
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
                'data.total_orders',
                1
            )
            ->assertJsonPath(
                'data.orders_today',
                1
            );
    }

    public function test_non_admin_cannot_view_dashboard(): void
    {
        $user =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $token =
            auth('api')->login(
                $user
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/dashboard'
            )
            ->assertForbidden();
    }
}