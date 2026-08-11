<?php

namespace Tests\Feature\Dispute;

use App\Enums\DisputeStatus;
use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderFor(User $buyer): Order
    {
        $farmer = Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => '08012345678',
        ]);
        $category = Category::create(['name' => 'Grains']);
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
            'stock' => 50,
            'status' => ListingStatus::Active,
        ]);

        return Order::create([
            'user_id' => $buyer->id,
            'listing_id' => $listing->id,
            'quantity' => 2,
            'total' => 90000,
            'status' => OrderStatus::Delivered,
        ]);
    }

    public function test_buyer_can_create_and_message_dispute(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::User]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $order = $this->createOrderFor($buyer);

        $create = $this->withToken(auth('api')->login($buyer))->postJson('/api/v1/disputes', [
            'order_id' => $order->id,
            'subject' => 'Wrong quantity delivered',
            'message' => 'I ordered 5 bags but only received 3.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.subject', 'Wrong quantity delivered')
            ->assertJsonPath('data.status', DisputeStatus::Open->value)
            ->assertJsonCount(1, 'data.messages');

        $disputeId = $create->json('data.id');

        $this->withToken(auth('api')->login($admin))
            ->postJson("/api/v1/admin/disputes/{$disputeId}/messages", [
                'message' => 'Please share a delivery photo.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.sender.role', 'admin');

        $this->withToken(auth('api')->login($buyer))
            ->postJson("/api/v1/disputes/{$disputeId}/messages", [
                'message' => 'Photo attached in description.',
            ])
            ->assertCreated();

        $this->withToken(auth('api')->login($admin))
            ->getJson("/api/v1/admin/disputes/{$disputeId}")
            ->assertOk()
            ->assertJsonCount(3, 'data.messages')
            ->assertJsonPath('data.buyer.name', $buyer->name);

        $this->withToken(auth('api')->login($admin))
            ->patchJson("/api/v1/admin/disputes/{$disputeId}", [
                'status' => DisputeStatus::Resolved->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', DisputeStatus::Resolved->value);

        $this->withToken(auth('api')->login($buyer))
            ->postJson("/api/v1/disputes/{$disputeId}/messages", [
                'message' => 'Should fail',
            ])
            ->assertStatus(422);
    }

    public function test_buyer_cannot_dispute_another_users_order(): void
    {
        $owner = User::factory()->create(['role' => UserRole::User]);
        $other = User::factory()->create(['role' => UserRole::User]);
        $order = $this->createOrderFor($owner);

        $this->withToken(auth('api')->login($other))
            ->postJson('/api/v1/disputes', [
                'order_id' => $order->id,
                'subject' => 'Issue',
                'message' => 'Not my order',
            ])
            ->assertNotFound();
    }

    public function test_only_one_dispute_per_order(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::User]);
        $order = $this->createOrderFor($buyer);
        $token = auth('api')->login($buyer);

        Dispute::create([
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'subject' => 'Existing',
            'status' => DisputeStatus::Open,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/disputes', [
                'order_id' => $order->id,
                'subject' => 'Another',
                'message' => 'Second dispute',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
    }
}
