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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRevenueTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_view_monthly_paid_order_item_revenue(): void
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
            $this->createFarmer(
                'Ibrahim Musa'
            );

        $listing =
            $this->createListing(
                $farmer,
                'Rice'
            );

        /*
         * Current-month paid order:
         * two order items totalling 50,000.
         */
        $currentOrder =
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-REV-000001',
                PaymentStatus::Paid,
                Carbon::parse(
                    '2026-08-10 12:00:00'
                )
            );

        $this->createItem(
            $currentOrder,
            $listing,
            $farmer,
            'Rice',
            '30000.00'
        );

        $this->createItem(
            $currentOrder,
            $listing,
            $farmer,
            'Rice',
            '20000.00'
        );

        /*
         * Previous-month paid revenue: 40,000.
         */
        $previousOrder =
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-REV-000002',
                PaymentStatus::Paid,
                Carbon::parse(
                    '2026-07-10 12:00:00'
                )
            );

        $this->createItem(
            $previousOrder,
            $listing,
            $farmer,
            'Rice',
            '40000.00'
        );

        /*
         * Pending payment must not contribute.
         */
        $pendingOrder =
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-REV-000003',
                PaymentStatus::Pending,
                null
            );

        $this->createItem(
            $pendingOrder,
            $listing,
            $farmer,
            'Rice',
            '90000.00'
        );

        $token =
            auth('api')->login(
                $admin
            );

        $response =
            $this
                ->withToken($token)
                ->getJson(
                    '/api/v1/admin/dashboard/revenue'
                );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.metric',
                'gross_paid_order_item_revenue'
            )
            ->assertJsonPath(
                'data.period',
                'month'
            )
            ->assertJsonPath(
                'data.range.start',
                '2026-08-01'
            )
            ->assertJsonPath(
                'data.range.end',
                '2026-08-31'
            )
            ->assertJsonPath(
                'data.summary.revenue',
                '50000.00'
            )
            ->assertJsonPath(
                'data.summary.previous_period_revenue',
                '40000.00'
            )
            ->assertJsonPath(
                'data.summary.change_percent',
                25
            )
            ->assertJsonPath(
                'data.summary.paid_orders',
                1
            )
            ->assertJsonPath(
                'data.summary.paid_order_items',
                2
            )
            ->assertJsonPath(
                'data.summary.unallocated_paid_revenue',
                '0.00'
            )
            ->assertJsonCount(
                31,
                'data.series'
            );

        $series =
            collect(
                $response->json(
                    'data.series'
                )
            );

        $augustTenth =
            $series
                ->firstWhere(
                    'key',
                    '2026-08-10'
                );

        $this->assertSame(
            '50000.00',
            $augustTenth[
                'revenue'
            ]
        );
    }

    public function test_revenue_endpoint_supports_farmer_filter(): void
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

        $firstFarmer =
            $this->createFarmer(
                'Ibrahim Musa'
            );

        $secondFarmer =
            $this->createFarmer(
                'Ada Okoro'
            );

        $firstListing =
            $this->createListing(
                $firstFarmer,
                'Rice'
            );

        $secondListing =
            $this->createListing(
                $secondFarmer,
                'Maize'
            );

        /*
         * One parent order containing revenue attributable
         * to two separate farmers.
         */
        $order =
            $this->createOrder(
                $buyer,
                $firstListing,
                'ORD-REV-000004',
                PaymentStatus::Paid,
                Carbon::parse(
                    '2026-08-12 09:30:00'
                )
            );

        $this->createItem(
            $order,
            $firstListing,
            $firstFarmer,
            'Rice',
            '30000.00'
        );

        $this->createItem(
            $order,
            $secondListing,
            $secondFarmer,
            'Maize',
            '15000.00'
        );

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/dashboard/revenue'
                .'?period=month'
                .'&farmer_id='
                .$firstFarmer->id
            )
            ->assertOk()
            ->assertJsonPath(
                'data.farmer_id',
                $firstFarmer->id
            )
            ->assertJsonPath(
                'data.summary.revenue',
                '30000.00'
            )
            ->assertJsonPath(
                'data.summary.paid_orders',
                1
            )
            ->assertJsonPath(
                'data.summary.paid_order_items',
                1
            );
    }

    public function test_week_revenue_returns_seven_daily_points(): void
    {
        Carbon::setTestNow(
            '2026-08-17 12:00:00'
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
                '/api/v1/admin/dashboard/revenue'
                .'?period=week'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.period',
                'week'
            )
            ->assertJsonCount(
                7,
                'data.series'
            )
            ->assertJsonPath(
                'data.summary.revenue',
                '0.00'
            )
            ->assertJsonPath(
                'data.summary.previous_period_revenue',
                '0.00'
            )
            ->assertJsonPath(
                'data.summary.change_percent',
                0
            );
    }

    public function test_year_revenue_returns_twelve_monthly_points(): void
    {
        Carbon::setTestNow(
            '2026-08-17 12:00:00'
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
                '/api/v1/admin/dashboard/revenue'
                .'?period=year'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.period',
                'year'
            )
            ->assertJsonCount(
                12,
                'data.series'
            );
    }

    public function test_paid_revenue_without_paid_at_is_reported_as_unallocated(): void
    {
        Carbon::setTestNow(
            '2026-08-17 13:00:00'
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
            $this->createFarmer(
                'Legacy Farmer'
            );

        $listing =
            $this->createListing(
                $farmer,
                'Beans'
            );

        $order =
            $this->createOrder(
                $buyer,
                $listing,
                'ORD-REV-LEGACY',
                PaymentStatus::Paid,
                null
            );

        $this->createItem(
            $order,
            $listing,
            $farmer,
            'Beans',
            '12500.00'
        );

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/dashboard/revenue'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.summary.revenue',
                '0.00'
            )
            ->assertJsonPath(
                'data.summary.unallocated_paid_revenue',
                '12500.00'
            )
            ->assertJsonPath(
                'data.summary.unallocated_paid_order_items',
                1
            );
    }

    public function test_invalid_revenue_filters_are_rejected(): void
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
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/dashboard/revenue'
                .'?period=quarter'
                .'&farmer_id=999999'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'period',
                'farmer_id',
            ]);
    }

    public function test_non_admin_cannot_view_dashboard_revenue(): void
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
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/dashboard/revenue'
            )
            ->assertForbidden();
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
                '15000.00',

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
        string $orderNumber,
        PaymentStatus $paymentStatus,
        ?Carbon $paidAt
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
                '0.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '0.00',

            'status' =>
                OrderStatus::New,

            'payment_status' =>
                $paymentStatus,

            'placed_at' =>
                $paidAt
                ?? now(),

            'paid_at' =>
                $paidAt,
        ]);
    }

    private function createItem(
        Order $order,
        Listing $listing,
        Farmer $farmer,
        string $produceName,
        string $lineTotal
    ): OrderItem {
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
                $produceName,

            'category_name' =>
                'Grains',

            'unit' =>
                'bag',

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