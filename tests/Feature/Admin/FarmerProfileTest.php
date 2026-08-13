<?php

namespace Tests\Feature\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingStatus;
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

class FarmerProfileTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);

        return auth('api')->login(
            $admin
        );
    }

    public function test_admin_can_create_detailed_farmer_profile(): void
    {
        $token = $this->adminToken();

        $response = $this
            ->withToken($token)
            ->postJson(
                '/api/v1/admin/farmers',
                [
                    'name' =>
                        'Ibrahim Musa',

                    'email' =>
                        'ibrahim@example.com',

                    'phone_number' =>
                        '08012345678',

                    'state' =>
                        'Niger',

                    'lga' =>
                        'Bida',

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

                    'status' =>
                        FarmerStatus::Active->value,
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.name',
                'Ibrahim Musa'
            )
            ->assertJsonPath(
                'data.email',
                'ibrahim@example.com'
            )
            ->assertJsonPath(
                'data.verification_status',
                FarmerVerificationStatus::Pending
                    ->value
            )
            ->assertJsonPath(
                'data.farm.name',
                'Musa Farms'
            )
            ->assertJsonPath(
                'data.farm.size_hectares',
                '12.50'
            )
            ->assertJsonPath(
                'data.farm.farming_method',
                'Mixed farming'
            )
            ->assertJsonPath(
                'data.farm.years_experience',
                14
            );

        $farmerCode =
            $response->json(
                'data.farmer_code'
            );

        $this->assertMatchesRegularExpression(
            '/^FAR-\d{6}$/',
            $farmerCode
        );

        $this->assertDatabaseHas(
            'farmers',
            [
                'email' =>
                    'ibrahim@example.com',

                'farmer_code' =>
                    $farmerCode,

                'farm_name' =>
                    'Musa Farms',

                'verification_status' =>
                    'pending',
            ]
        );
    }

    public function test_farmer_code_and_verification_cannot_be_supplied_by_client(): void
    {
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/admin/farmers',
                [
                    'name' =>
                        'Ibrahim Musa',

                    'phone_number' =>
                        '08012345678',

                    'state' =>
                        'Niger',

                    'lga' =>
                        'Bida',

                    'status' =>
                        FarmerStatus::Active->value,

                    'farmer_code' =>
                        'FAR-HACKED',

                    'verification_status' =>
                        'verified',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'farmer_code',
                'verification_status',
            ]);
    }

    public function test_admin_can_update_farmer_profile_without_changing_code(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $originalCode =
            $farmer->farmer_code;

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}",
                [
                    'name' =>
                        'Ibrahim Musa',

                    'email' =>
                        'ibrahim.musa@example.com',

                    'phone_number' =>
                        '08012345678',

                    'state' =>
                        'Kaduna',

                    'lga' =>
                        'Kagarko',

                    'address' =>
                        '10 Farm Road',

                    'gender' =>
                        'Male',

                    'date_of_birth' =>
                        '1985-04-12',

                    'farm_name' =>
                        'Musa Integrated Farms',

                    'farm_size_hectares' =>
                        20,

                    'farming_method' =>
                        'Irrigated farming',

                    'years_experience' =>
                        18,

                    'farm_address' =>
                        'Kagarko Farm Settlement',

                    'status' =>
                        FarmerStatus::Active->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.farmer_code',
                $originalCode
            )
            ->assertJsonPath(
                'data.state',
                'Kaduna'
            )
            ->assertJsonPath(
                'data.farm.name',
                'Musa Integrated Farms'
            )
            ->assertJsonPath(
                'data.farm.size_hectares',
                '20.00'
            );

        $farmer->refresh();

        $this->assertSame(
            $originalCode,
            $farmer->farmer_code
        );

        $this->assertSame(
            'Musa Integrated Farms',
            $farmer->farm_name
        );
    }

    public function test_farmer_overview_uses_only_paid_item_earnings(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
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

        $listing = Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                '5000.00',

            'unit' =>
                'bag',

            'stock' =>
                100,

            'status' =>
                ListingStatus::Active,
        ]);

        $paidOrder = Order::create([
            'user_id' =>
                $buyer->id,

            'listing_id' =>
                $listing->id,

            'quantity' =>
                2,

            'order_number' =>
                'ORD-FARM-000001',

            'subtotal' =>
                '10000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '10000.00',

            'status' =>
                OrderStatus::Delivered,

            'payment_status' =>
                PaymentStatus::Paid,
        ]);

        OrderItem::create([
            'order_id' =>
                $paidOrder->id,

            'listing_id' =>
                $listing->id,

            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'produce_name' =>
                'Rice',

            'category_name' =>
                'Grains',

            'unit' =>
                'bag',

            'quantity' =>
                2,

            'unit_price' =>
                '5000.00',

            'discount_amount' =>
                '0.00',

            'line_total' =>
                '10000.00',
        ]);

        $pendingOrder = Order::create([
            'user_id' =>
                $buyer->id,

            'listing_id' =>
                $listing->id,

            'quantity' =>
                1,

            'order_number' =>
                'ORD-FARM-000002',

            'subtotal' =>
                '5000.00',

            'delivery_fee' =>
                '0.00',

            'total' =>
                '5000.00',

            'status' =>
                OrderStatus::Delivered,

            'payment_status' =>
                PaymentStatus::Pending,
        ]);

        OrderItem::create([
            'order_id' =>
                $pendingOrder->id,

            'listing_id' =>
                $listing->id,

            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'produce_name' =>
                'Rice',

            'category_name' =>
                'Grains',

            'unit' =>
                'bag',

            'quantity' =>
                1,

            'unit_price' =>
                '5000.00',

            'discount_amount' =>
                '0.00',

            'line_total' =>
                '5000.00',
        ]);

        $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.orders_count',
                2
            )
            ->assertJsonPath(
                'data.completed_orders_count',
                2
            )
            ->assertJsonPath(
                'data.total_earned',
                '10000.00'
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
}