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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_view_expanded_dashboard_summary(): void
    {
        Carbon::setTestNow(
            '2026-08-17 09:00:00'
        );

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
         * One order placed today. It also belongs in the
         * action queue because its status is new.
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
         * Historical terminal order.
         *
         * It increases total_orders but must not appear in
         * the action queue.
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
            )
            ->assertJsonPath(
                'data.order_action_queue_count',
                1
            )
            ->assertJsonCount(
                1,
                'data.order_action_queue'
            )
            ->assertJsonPath(
                'data.order_action_queue.0.order_number',
                'ORD-DASH-000001'
            )
            ->assertJsonPath(
                'data.order_action_queue.0.status',
                'new'
            )
            ->assertJsonPath(
                'data.order_action_queue.0.action.key',
                'process_order'
            )
            ->assertJsonPath(
                'data.order_action_queue.0.action.next_status',
                'in_transit'
            )
            ->assertJsonPath(
                'data.order_action_queue.0.action.can_cancel',
                true
            );
    }

    public function test_action_queue_contains_only_non_terminal_orders_and_prioritises_new_orders(): void
    {
        Carbon::setTestNow(
            '2026-08-17 10:00:00'
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
            $this->createVerifiedFarmer();

        $listing =
            $this->createListing(
                $farmer,
                'Beans'
            );

        /*
         * Older in-transit order.
         */
        $inTransit =
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-QUEUE-000001',
                OrderStatus::InTransit,
                PaymentStatus::Paid,
                now()->subHours(3)
            );

        /*
         * Newer new order.
         *
         * New orders are intentionally presented ahead of
         * in-transit orders because they have not yet entered
         * fulfillment.
         */
        $new =
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-QUEUE-000002',
                OrderStatus::New,
                PaymentStatus::Paid,
                now()->subHour()
            );

        $this->createOrder(
            $buyer,
            $listing,
            'ORD-QUEUE-000003',
            OrderStatus::Delivered,
            PaymentStatus::Paid,
            now()->subHours(4)
        );

        $this->createOrder(
            $buyer,
            $listing,
            'ORD-QUEUE-000004',
            OrderStatus::Cancelled,
            PaymentStatus::Pending,
            now()->subHours(5)
        );

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
                'data.order_action_queue_count',
                2
            )
            ->assertJsonCount(
                2,
                'data.order_action_queue'
            )
            ->assertJsonPath(
                'data.order_action_queue.0.id',
                $new->id
            )
            ->assertJsonPath(
                'data.order_action_queue.0.action.key',
                'process_order'
            )
            ->assertJsonPath(
                'data.order_action_queue.0.action.next_status',
                'in_transit'
            )
            ->assertJsonPath(
                'data.order_action_queue.0.action.can_cancel',
                false
            )
            ->assertJsonPath(
                'data.order_action_queue.1.id',
                $inTransit->id
            )
            ->assertJsonPath(
                'data.order_action_queue.1.action.key',
                'complete_delivery'
            )
            ->assertJsonPath(
                'data.order_action_queue.1.action.next_status',
                'delivered'
            )
            ->assertJsonPath(
                'data.order_action_queue.1.action.can_cancel',
                false
            );
    }

    public function test_action_queue_is_limited_to_five_orders(): void
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
            $this->createVerifiedFarmer();

        $listing =
            $this->createListing(
                $farmer,
                'Sorghum'
            );

        foreach (
            range(1, 7)
            as $index
        ) {
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-LIMIT-'
                    .str_pad(
                        (string)
                            $index,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
                OrderStatus::New,
                PaymentStatus::Pending,
                now()->subMinutes(
                    10 - $index
                )
            );
        }

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
                'data.order_action_queue_count',
                7
            )
            ->assertJsonCount(
                5,
                'data.order_action_queue'
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
            $this->createVerifiedFarmer();

        $listing =
            $this->createListing(
                $farmer,
                'Tomato',
                'Vegetables'
            );

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

    private function createVerifiedFarmer(): Farmer
    {
        return Farmer::create([
            'name' =>
                'Verified Farmer',

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
        string $produceName,
        string $categoryName = 'Grains'
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

        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                '15000.00',

            'unit' =>
                'bag',

            'stock' =>
                50,

            'minimum_order_quantity' =>
                1,

            'publication_status' =>
                ListingPublicationStatus::Live,
        ]);
    }

    private function createOrder(
        User $buyer,
        Listing $listing,
        string $orderNumber,
        OrderStatus $status,
        PaymentStatus $paymentStatus,
        Carbon $placedAt
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
                '15000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '15000.00',

            'status' =>
                $status,

            'payment_status' =>
                $paymentStatus,

            'placed_at' =>
                $placedAt,

            'out_for_delivery_at' =>
                $status
                === OrderStatus::InTransit
                    ? $placedAt
                    : null,

            'delivered_at' =>
                $status
                === OrderStatus::Delivered
                    ? $placedAt
                    : null,

            'cancelled_at' =>
                $status
                === OrderStatus::Cancelled
                    ? $placedAt
                    : null,

            'paid_at' =>
                $paymentStatus
                === PaymentStatus::Paid
                    ? $placedAt
                    : null,
        ]);
    }
}