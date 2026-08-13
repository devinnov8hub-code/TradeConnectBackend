<?php

namespace Tests\Feature\Dispute;

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
use App\Models\OrderItem;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_create_and_message_dispute(): void
    {
        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        [
            $order,
            $orderItem,
            $farmer,
        ] =
            $this->createOrderFor(
                $buyer,
                'ORD-DSP-000001'
            );

        $create =
            $this
                ->withToken(
                    auth('api')->login(
                        $buyer
                    )
                )
                ->postJson(
                    '/api/v1/disputes',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $orderItem->id,

                        'subject' =>
                            'Wrong quantity delivered',

                        'message' =>
                            'I ordered 5 bags but only received 3.',
                    ]
                );

        $create
            ->assertCreated()
            ->assertJsonPath(
                'data.subject',
                'Wrong quantity delivered'
            )
            ->assertJsonPath(
                'data.status',
                DisputeStatus::UnderReview
                    ->value
            )
            ->assertJsonPath(
                'data.order_item_id',
                $orderItem->id
            )
            ->assertJsonPath(
                'data.affected_item.id',
                $orderItem->id
            )
            ->assertJsonPath(
                'data.affected_farmer.id',
                $farmer->id
            )
            ->assertJsonPath(
                'data.unread_count',
                0
            )
            ->assertJsonCount(
                1,
                'data.messages'
            );

        $disputeId =
            $create->json(
                'data.id'
            );

        $this
            ->withToken(
                auth('api')->login(
                    $admin
                )
            )
            ->postJson(
                "/api/v1/admin/disputes/{$disputeId}/messages",
                [
                    'message' =>
                        'Please share a delivery photo.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.sender.role',
                'admin'
            );

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                "/api/v1/disputes/{$disputeId}/messages",
                [
                    'message' =>
                        'I have the delivery evidence.',
                ]
            )
            ->assertCreated();

        $this
            ->withToken(
                auth('api')->login(
                    $admin
                )
            )
            ->getJson(
                "/api/v1/admin/disputes/{$disputeId}"
            )
            ->assertOk()
            ->assertJsonCount(
                3,
                'data.messages'
            )
            ->assertJsonPath(
                'data.buyer.name',
                $buyer->name
            )
            ->assertJsonPath(
                'data.affected_farmer.id',
                $farmer->id
            );

        $resolve =
            $this
                ->withToken(
                    auth('api')->login(
                        $admin
                    )
                )
                ->patchJson(
                    "/api/v1/admin/disputes/{$disputeId}",
                    [
                        'status' =>
                            DisputeStatus::Resolved
                                ->value,

                        'note' =>
                            'Buyer and seller confirmed the corrected delivery.',
                    ]
                );

        $resolve
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'resolved'
            )
            ->assertJsonPath(
                'data.resolution_note',
                'Buyer and seller confirmed the corrected delivery.'
            );

        $this->assertNotNull(
            $resolve->json(
                'data.resolved_at'
            )
        );

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                "/api/v1/disputes/{$disputeId}/messages",
                [
                    'message' =>
                        'Should fail',
                ]
            )
            ->assertUnprocessable();
    }

    public function test_buyer_cannot_dispute_another_users_order(): void
    {
        $owner =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $other =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        [
            $order,
            $orderItem,
        ] =
            $this->createOrderFor(
                $owner,
                'ORD-DSP-000002'
            );

        $this
            ->withToken(
                auth('api')->login(
                    $other
                )
            )
            ->postJson(
                '/api/v1/disputes',
                [
                    'order_id' =>
                        $order->id,

                    'order_item_id' =>
                        $orderItem->id,

                    'subject' =>
                        'Issue',

                    'message' =>
                        'Not my order',
                ]
            )
            ->assertNotFound();
    }

    public function test_only_one_dispute_per_order(): void
    {
        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        [
            $order,
            $orderItem,
        ] =
            $this->createOrderFor(
                $buyer,
                'ORD-DSP-000003'
            );

        Dispute::create([
            'order_id' =>
                $order->id,

            'order_item_id' =>
                $orderItem->id,

            'user_id' =>
                $buyer->id,

            'subject' =>
                'Existing',

            'status' =>
                DisputeStatus::UnderReview,
        ]);

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->postJson(
                '/api/v1/disputes',
                [
                    'order_id' =>
                        $order->id,

                    'order_item_id' =>
                        $orderItem->id,

                    'subject' =>
                        'Another',

                    'message' =>
                        'Second dispute',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'order_id',
            ]);
    }

    private function createOrderFor(
        User $buyer,
        string $orderNumber
    ): array {
        $farmer =
            Farmer::create([
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
                    'Rice',

                'image' =>
                    base64_encode(
                        'rice'
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
                    45000,

                'unit' =>
                    'bag',

                'stock' =>
                    50,

                'publication_status' =>
                    ListingPublicationStatus::Live,
            ]);

        $order =
            Order::create([
                'user_id' =>
                    $buyer->id,

                'listing_id' =>
                    $listing->id,

                'quantity' =>
                    2,

                'order_number' =>
                    $orderNumber,

                'subtotal' =>
                    '90000.00',

                'delivery_fee' =>
                    '0.00',

                'total' =>
                    '90000.00',

                'status' =>
                    OrderStatus::Delivered,

                'payment_status' =>
                    PaymentStatus::Paid,

                'placed_at' =>
                    now(),
            ]);

        $orderItem =
            OrderItem::create([
                'order_id' =>
                    $order->id,

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
                    '45000.00',

                'discount_amount' =>
                    '0.00',

                'line_total' =>
                    '90000.00',
            ]);

        return [
            $order,
            $orderItem,
            $farmer,
        ];
    }
}