<?php

namespace Tests\Feature\Order;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
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

class OrderItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_contain_multiple_snapshot_items(): void
    {
        $buyer = User::factory()->create([
            'role' => UserRole::User,
        ]);

        $farmer = Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Kaduna',
            'lga' => 'Kagarko',
            'status' => FarmerStatus::Active,
            'phone_number' => '08012345678',
        ]);

        $category = Category::create([
            'name' => 'Roots & Tubers',
        ]);

        $yam = Produce::create([
            'category_id' => $category->id,
            'name' => 'Yam',
            'image' => base64_encode('yam'),
            'image_mime' => 'image/jpeg',
        ]);

        $sweetPotatoes = Produce::create([
            'category_id' => $category->id,
            'name' => 'Sweet Potatoes',
            'image' => base64_encode('sweet-potatoes'),
            'image_mime' => 'image/jpeg',
        ]);

        $yamListing = Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $yam->id,
            'price' => 1100,
            'stock' => 100,
            'status' => ListingStatus::Active,
        ]);

        $sweetPotatoListing = Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $sweetPotatoes->id,
            'price' => 1040,
            'stock' => 100,
            'status' => ListingStatus::Active,
        ]);

        /*
         * listing_id and quantity are still required by the legacy schema.
         * They will disappear in a later migration once the API no longer
         * depends on them.
         */
        $order = Order::create([
            'user_id' => $buyer->id,
            'listing_id' => $yamListing->id,
            'quantity' => 5,
            'subtotal' => 10700,
            'delivery_fee' => 1000,
            'total' => 11700,
            'status' => OrderStatus::New,
            'payment_status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'listing_id' => $yamListing->id,
            'farmer_id' => $farmer->id,
            'produce_id' => $yam->id,
            'produce_name' => 'Yam',
            'category_name' => 'Roots & Tubers',
            'unit' => 'kg',
            'quantity' => 5,
            'unit_price' => 1100,
            'discount_amount' => 0,
            'line_total' => 5500,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'listing_id' => $sweetPotatoListing->id,
            'farmer_id' => $farmer->id,
            'produce_id' => $sweetPotatoes->id,
            'produce_name' => 'Sweet Potatoes',
            'category_name' => 'Roots & Tubers',
            'unit' => 'kg',
            'quantity' => 5,
            'unit_price' => 1040,
            'discount_amount' => 0,
            'line_total' => 5200,
        ]);

        $order->load('items');

        $this->assertCount(2, $order->items);

        $this->assertSame(
            'Yam',
            $order->items->first()->produce_name
        );

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'produce_name' => 'Yam',
            'quantity' => 5,
            'line_total' => 5500,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'produce_name' => 'Sweet Potatoes',
            'quantity' => 5,
            'line_total' => 5200,
        ]);
    }
}