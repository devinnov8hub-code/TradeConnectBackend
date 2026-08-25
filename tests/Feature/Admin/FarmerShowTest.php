<?php

namespace Tests\Feature\Admin;

use App\Enums\FarmerStatus;
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

class FarmerShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_farmer_with_listings_and_orders(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $buyer = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $token = auth('api')->login($admin);

        $farmer = Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => '08012345678',
        ]);

        $category = Category::create([
            'name' => 'Grains',
        ]);

        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => 'Rice',
            'image' => base64_encode('rice'),
            'image_mime' => 'image/jpeg',
        ]);

        $listing = Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => 45000,
            'unit' => 'bag',
            'stock' => 50,
            'status' => ListingStatus::Active,
        ]);

        /*
         * Delivered and paid order.
         *
         * This order contributes to the farmer's earnings.
         */
        $paidOrder = Order::create([
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
            'quantity' => 2,
            'order_number' => 'ORD-FARM-000001',
            'subtotal' => '90000.00',
            'delivery_fee' => '0.00',
            'total' => '90000.00',
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
        ]);

        OrderItem::create([
            'order_id' => $paidOrder->id,
            'listing_id' => $listing->id,
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'produce_name' => 'Rice',
            'category_name' => 'Grains',
            'unit' => 'bag',
            'quantity' => 2,
            'unit_price' => '45000.00',
            'discount_amount' => '0.00',
            'line_total' => '90000.00',
        ]);

        /*
         * Cancelled and unpaid order.
         *
         * It belongs to this farmer's order history,
         * but must not contribute to earnings.
         */
        $cancelledOrder = Order::create([
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
            'quantity' => 1,
            'order_number' => 'ORD-FARM-000002',
            'subtotal' => '45000.00',
            'delivery_fee' => '0.00',
            'total' => '45000.00',
            'status' => OrderStatus::Cancelled,
            'payment_status' => PaymentStatus::Pending,
        ]);

        OrderItem::create([
            'order_id' => $cancelledOrder->id,
            'listing_id' => $listing->id,
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'produce_name' => 'Rice',
            'category_name' => 'Grains',
            'unit' => 'bag',
            'quantity' => 1,
            'unit_price' => '45000.00',
            'discount_amount' => '0.00',
            'line_total' => '45000.00',
        ]);

        $response = $this
            ->withToken($token)
            ->getJson(
                "/api/v1/admin/farmers/{$farmer->id}"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.listings_count',
                1
            )
            ->assertJsonPath(
                'data.orders_count',
                2
            )
            ->assertJsonPath(
                'data.completed_orders_count',
                1
            )
            ->assertJsonPath(
                'data.total_earned',
                '90000.00'
            )
            ->assertJsonCount(
                1,
                'data.listings'
            )
            ->assertJsonCount(
                2,
                'data.orders'
            )
            ->assertJsonPath(
                'data.listings.0.produce.name',
                'Rice'
            )
            ->assertJsonPath(
                'data.listings.0.unit',
                'bag'
            );
    }
}