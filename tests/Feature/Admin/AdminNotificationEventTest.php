<?php

namespace Tests\Feature\Admin;

use App\Enums\DisputeStatus;
use App\Enums\FarmerStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNotificationEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_order_notifies_active_admins_once_after_order_number_is_assigned(): void
    {
        $activeAdmin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,

                'status' =>
                    UserStatus::Active,
            ]);

        $inactiveAdmin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,

                'status' =>
                    UserStatus::Inactive,
            ]);

        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $listing =
            $this->createListing();

        $order =
            Order::create([
                'user_id' =>
                    $buyer->id,

                'listing_id' =>
                    $listing->id,

                'quantity' =>
                    2,

                'subtotal' =>
                    '5000.00',

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    '5000.00',

                'status' =>
                    OrderStatus::New,

                'payment_status' =>
                    PaymentStatus::Pending,

                'placed_at' =>
                    now(),
            ]);

        /*
         * This mirrors checkout: the public order number is
         * assigned only after the initial insert.
         */
        $this->assertSame(
            0,
            $activeAdmin
                ->notifications()
                ->count()
        );

        $order->update([
            'order_number' =>
                'ORD-'.str_pad(
                    (string) $order->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
        ]);

        $this->assertSame(
            1,
            $activeAdmin
                ->notifications()
                ->count()
        );

        $this->assertSame(
            0,
            $inactiveAdmin
                ->notifications()
                ->count()
        );

        $notification =
            $activeAdmin
                ->notifications()
                ->firstOrFail();

        $this->assertSame(
            'order',
            $notification->type
        );

        $this->assertSame(
            'New order',
            $notification->data['title']
        );

        $this->assertSame(
            "Order {$order->order_number} requires attention.",
            $notification->data['message']
        );

        $this->assertSame(
            "/api/v1/admin/orders/{$order->id}",
            $notification->data['action_url']
        );

        $this->assertSame(
            'order',
            $notification->data['entity_type']
        );

        $this->assertSame(
            $order->id,
            $notification->data['entity_id']
        );

        /*
         * Editing an existing number must not generate
         * another "new order" notification.
         */
        $order->update([
            'order_number' =>
                'ORD-RENAMED-001',
        ]);

        $this->assertSame(
            1,
            $activeAdmin
                ->notifications()
                ->count()
        );
    }

    public function test_new_dispute_notifies_active_admins(): void
    {
        $activeAdmin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,

                'status' =>
                    UserStatus::Active,
            ]);

        $inactiveAdmin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,

                'status' =>
                    UserStatus::Inactive,
            ]);

        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $listing =
            $this->createListing();

        $order =
            Order::create([
                'user_id' =>
                    $buyer->id,

                'listing_id' =>
                    $listing->id,

                'quantity' =>
                    1,

                'order_number' =>
                    'ORD-DSP-NOT-001',

                'subtotal' =>
                    '2500.00',

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    '2500.00',

                'status' =>
                    OrderStatus::New,

                'payment_status' =>
                    PaymentStatus::Pending,

                'placed_at' =>
                    now(),
            ]);

        /*
         * Remove the order notification so this test isolates
         * the dispute event.
         */
        $activeAdmin
            ->notifications()
            ->delete();

        $dispute =
            Dispute::create([
                'order_id' =>
                    $order->id,

                'user_id' =>
                    $buyer->id,

                'subject' =>
                    'Wrong quantity delivered',

                'status' =>
                    DisputeStatus::UnderReview,
            ]);

        $this->assertSame(
            1,
            $activeAdmin
                ->notifications()
                ->count()
        );

        $this->assertSame(
            0,
            $inactiveAdmin
                ->notifications()
                ->count()
        );

        $notification =
            $activeAdmin
                ->notifications()
                ->firstOrFail();

        $this->assertSame(
            'dispute',
            $notification->type
        );

        $this->assertSame(
            'New dispute',
            $notification->data['title']
        );

        $this->assertSame(
            'A buyer opened a new dispute.',
            $notification->data['message']
        );

        $this->assertSame(
            "/api/v1/admin/disputes/{$dispute->id}",
            $notification->data['action_url']
        );

        $this->assertSame(
            'dispute',
            $notification->data['entity_type']
        );

        $this->assertSame(
            $dispute->id,
            $notification->data['entity_id']
        );
    }

    private function createListing(): Listing
    {
        $category =
            Category::create([
                'name' =>
                    'Grains',
            ]);

        $produce =
            Produce::create([
                'category_id' =>
                    $category->id,

                'name' =>
                    'Rice',
            ]);

        $farmer =
            Farmer::create([
                'name' =>
                    'Notification Farmer',

                'state' =>
                    'Niger',

                'lga' =>
                    'Bida',

                'phone_number' =>
                    '08012345678',

                'status' =>
                    FarmerStatus::Active,

                'verification_status' =>
                    'verified',
            ]);

        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                '2500.00',

            'unit' =>
                'bag',

            'stock' =>
                20,

            'status' =>
                ListingStatus::Active,

            'publication_status' =>
                ListingPublicationStatus::Live,
        ]);
    }
}