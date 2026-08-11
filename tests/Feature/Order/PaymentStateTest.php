<?php

namespace Tests\Feature\Order;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_payment_status_is_cast_to_enum(): void
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
            'price' => 5000,
            'stock' => 100,
            'status' => ListingStatus::Active,
        ]);

        $order = Order::create([
            'user_id' => $buyer->id,

            // Temporary legacy fields.
            'listing_id' => $listing->id,
            'quantity' => 2,

            'subtotal' => 10000,
            'delivery_fee' => 0,
            'total' => 10000,

            'status' => 'new',
            'payment_status' => PaymentStatus::Pending,
        ]);

        $order->refresh();

        $this->assertSame(
            PaymentStatus::Pending,
            $order->payment_status
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'pending',
        ]);

        $this->assertNull(
            $order->payment_provider
        );

        $this->assertNull(
            $order->payment_reference
        );

        $this->assertNull(
            $order->paid_at
        );
    }
}