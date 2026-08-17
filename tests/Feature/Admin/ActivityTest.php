<?php

namespace Tests\Feature\Admin;

use App\Enums\DisputeStatus;
use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_view_recent_activity_across_core_domains(): void
    {
        Carbon::setTestNow(
            '2026-08-17 09:00:00'
        );

        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        Carbon::setTestNow(
            '2026-08-17 09:01:00'
        );

        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        Carbon::setTestNow(
            '2026-08-17 09:02:00'
        );

        $farmer =
            $this->createFarmer();

        Carbon::setTestNow(
            '2026-08-17 09:03:00'
        );

        $listing =
            $this->createListing(
                $farmer,
                'Rice'
            );

        Carbon::setTestNow(
            '2026-08-17 09:04:00'
        );

        $order =
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-ACT-000001'
            );

        Carbon::setTestNow(
            '2026-08-17 09:05:00'
        );

        Dispute::create([
            'order_id' =>
                $order->id,

            'order_item_id' =>
                null,

            'user_id' =>
                $buyer->id,

            'subject' =>
                'Damaged delivery',

            'status' =>
                DisputeStatus::UnderReview,
        ]);

        Carbon::setTestNow(
            '2026-08-17 09:06:00'
        );

        $token =
            auth('api')->login(
                $admin
            );

        $response =
            $this
                ->withToken(
                    $token
                )
                ->getJson(
                    '/api/v1/admin/activities'
                );

        $response
            ->assertOk()
            ->assertJsonPath(
                'meta.type',
                'all'
            )
            ->assertJsonPath(
                'meta.limit',
                10
            )
            ->assertJsonCount(
                5,
                'data'
            )
            ->assertJsonPath(
                'data.0.type',
                'dispute'
            )
            ->assertJsonPath(
                'data.0.action',
                'opened'
            )
            ->assertJsonPath(
                'data.1.type',
                'order'
            )
            ->assertJsonPath(
                'data.1.action',
                'created'
            )
            ->assertJsonPath(
                'data.1.entity.code',
                'ORD-ACT-000001'
            )
            ->assertJsonPath(
                'data.2.type',
                'listing'
            )
            ->assertJsonPath(
                'data.3.type',
                'farmer'
            )
            ->assertJsonPath(
                'data.4.type',
                'buyer'
            );
    }

    public function test_activity_feed_supports_type_filter_and_limit(): void
    {
        Carbon::setTestNow(
            '2026-08-17 10:00:00'
        );

        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        Carbon::setTestNow(
            '2026-08-17 10:01:00'
        );

        User::factory()->create([
            'role' =>
                UserRole::User,
            'name' =>
                'Older Buyer',
        ]);

        Carbon::setTestNow(
            '2026-08-17 10:02:00'
        );

        $latestBuyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
                'name' =>
                    'Latest Buyer',
            ]);

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/admin/activities'
                .'?type=buyer'
                .'&limit=1'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'meta.type',
                'buyer'
            )
            ->assertJsonPath(
                'meta.limit',
                1
            )
            ->assertJsonPath(
                'data.0.type',
                'buyer'
            )
            ->assertJsonPath(
                'data.0.entity.id',
                $latestBuyer->id
            )
            ->assertJsonPath(
                'data.0.meta.name',
                'Latest Buyer'
            );
    }

    public function test_resolved_dispute_appears_as_latest_dispute_activity(): void
    {
        Carbon::setTestNow(
            '2026-08-17 11:00:00'
        );

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
            $this->createFarmer();

        $listing =
            $this->createListing(
                $farmer,
                'Maize'
            );

        $order =
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-ACT-000002'
            );

        $dispute =
            Dispute::create([
                'order_id' =>
                    $order->id,

                'order_item_id' =>
                    null,

                'user_id' =>
                    $buyer->id,

                'subject' =>
                    'Wrong quantity',

                'status' =>
                    DisputeStatus::UnderReview,
            ]);

        Carbon::setTestNow(
            '2026-08-17 11:15:00'
        );

        $dispute
            ->forceFill([
                'status' =>
                    DisputeStatus::Resolved,

                'resolved_at' =>
                    now(),

                'resolved_by_user_id' =>
                    $admin->id,

                'resolution_note' =>
                    'Issue confirmed and resolved.',
            ])
            ->save();

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/admin/activities'
                .'?type=dispute'
                .'&limit=1'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.type',
                'dispute'
            )
            ->assertJsonPath(
                'data.0.action',
                'resolved'
            )
            ->assertJsonPath(
                'data.0.status',
                'resolved'
            )
            ->assertJsonPath(
                'data.0.actor.id',
                $admin->id
            )
            ->assertJsonPath(
                'data.0.entity.id',
                $dispute->id
            );
    }

    public function test_invalid_activity_filters_are_rejected(): void
    {
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
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/admin/activities'
                .'?type=unknown'
                .'&limit=51'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'type',
                'limit',
            ]);
    }

    public function test_non_admin_cannot_view_activity_feed(): void
    {
        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $token =
            auth('api')->login(
                $buyer
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/admin/activities'
            )
            ->assertForbidden();
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

    private function createListing(
        Farmer $farmer,
        string $produceName
    ): Listing {
        $category =
            Category::firstOrCreate([
                'name' =>
                    'Grains',
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
                '45000.00',

            'unit' =>
                'bag',

            'stock' =>
                50,

            'minimum_order_quantity' =>
                1,

            'publication_status' =>
                ListingPublicationStatus::Pending,
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
                '45000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '45000.00',

            'status' =>
                OrderStatus::New,

            'payment_status' =>
                PaymentStatus::Pending,

            'placed_at' =>
                now(),
        ]);
    }
}