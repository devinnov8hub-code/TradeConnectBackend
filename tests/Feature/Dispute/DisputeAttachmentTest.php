<?php

namespace Tests\Feature\Dispute;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\DisputeMessage;
use App\Models\DisputeMessageAttachment;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DisputeAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(
            'local'
        );
    }

    public function test_buyer_can_upload_attachments_with_initial_dispute_message(): void
    {
        $buyer =
            $this->createBuyer();

        [
            $order,
            $item,
        ] =
            $this->createOrderFor(
                $buyer,
                'ORD-ATT-000001'
            );

        $token =
            auth('api')->login(
                $buyer
            );

        $response =
            $this
                ->withToken($token)
                ->post(
                    '/api/v1/disputes',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'subject' =>
                            'Damaged delivery',

                        'message' =>
                            'The bags arrived damaged.',

                        'attachments' => [
                            UploadedFile::fake()
                                ->image(
                                    'damage.jpg',
                                    800,
                                    600
                                ),

                            UploadedFile::fake()
                                ->create(
                                    'receipt.pdf',
                                    100,
                                    'application/pdf'
                                ),
                        ],
                    ],
                    [
                        'Accept' =>
                            'application/json',
                    ]
                );

        $response
            ->assertCreated()
            ->assertJsonCount(
                2,
                'data.messages.0.attachments'
            )
            ->assertJsonPath(
                'data.messages.0.attachments.0.original_name',
                'damage.jpg'
            )
            ->assertJsonPath(
                'data.messages.0.attachments.1.original_name',
                'receipt.pdf'
            );

        $this->assertDatabaseCount(
            'dispute_message_attachments',
            2
        );

        $attachment =
            DisputeMessageAttachment::query()
                ->orderBy('id')
                ->firstOrFail();

        Storage::disk(
            'local'
        )->assertExists(
            $attachment->path
        );

        $disputeId =
            $response->json(
                'data.id'
            );

        $response->assertJsonPath(
            'data.messages.0.attachments.0.download_url',
            "/api/v1/disputes/{$disputeId}/attachments/{$attachment->id}"
        );

        $this
            ->withToken($token)
            ->get(
                "/api/v1/disputes/{$disputeId}/attachments/{$attachment->id}"
            )
            ->assertOk();
    }

    public function test_admin_can_reply_with_attachment_and_download_it(): void
    {
        $buyer =
            $this->createBuyer();

        $admin =
            $this->createAdmin();

        [
            $order,
            $item,
        ] =
            $this->createOrderFor(
                $buyer,
                'ORD-ATT-000002'
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
                            $item->id,

                        'subject' =>
                            'Quality issue',

                        'message' =>
                            'Please review this order.',
                    ]
                );

        $create->assertCreated();

        $disputeId =
            $create->json(
                'data.id'
            );

        $adminToken =
            auth('api')->login(
                $admin
            );

        $reply =
            $this
                ->withToken(
                    $adminToken
                )
                ->post(
                    "/api/v1/admin/disputes/{$disputeId}/messages",
                    [
                        'message' =>
                            'Attached is our inspection report.',

                        'attachments' => [
                            UploadedFile::fake()
                                ->create(
                                    'inspection.pdf',
                                    120,
                                    'application/pdf'
                                ),
                        ],
                    ],
                    [
                        'Accept' =>
                            'application/json',
                    ]
                );

        $reply
            ->assertCreated()
            ->assertJsonCount(
                1,
                'data.attachments'
            )
            ->assertJsonPath(
                'data.attachments.0.original_name',
                'inspection.pdf'
            );

        $attachment =
            DisputeMessageAttachment::query()
                ->latest('id')
                ->firstOrFail();

        Storage::disk(
            'local'
        )->assertExists(
            $attachment->path
        );

        $reply->assertJsonPath(
            'data.attachments.0.download_url',
            "/api/v1/admin/disputes/{$disputeId}/attachments/{$attachment->id}"
        );

        $this
            ->withToken(
                $adminToken
            )
            ->get(
                "/api/v1/admin/disputes/{$disputeId}/attachments/{$attachment->id}"
            )
            ->assertOk();
    }

    public function test_buyer_cannot_download_attachment_from_another_buyers_dispute(): void
    {
        $owner =
            $this->createBuyer();

        $otherBuyer =
            $this->createBuyer();

        [
            $order,
            $item,
        ] =
            $this->createOrderFor(
                $owner,
                'ORD-ATT-000003'
            );

        $create =
            $this
                ->withToken(
                    auth('api')->login(
                        $owner
                    )
                )
                ->post(
                    '/api/v1/disputes',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'subject' =>
                            'Private evidence',

                        'message' =>
                            'This evidence should remain private.',

                        'attachments' => [
                            UploadedFile::fake()
                                ->image(
                                    'private.jpg'
                                ),
                        ],
                    ],
                    [
                        'Accept' =>
                            'application/json',
                    ]
                );

        $create->assertCreated();

        $disputeId =
            $create->json(
                'data.id'
            );

        $attachment =
            DisputeMessageAttachment::query()
                ->firstOrFail();

        $this
            ->withToken(
                auth('api')->login(
                    $otherBuyer
                )
            )
            ->get(
                "/api/v1/disputes/{$disputeId}/attachments/{$attachment->id}"
            )
            ->assertNotFound();
    }

    public function test_invalid_attachment_type_is_rejected(): void
    {
        $buyer =
            $this->createBuyer();

        [
            $order,
            $item,
        ] =
            $this->createOrderFor(
                $buyer,
                'ORD-ATT-000004'
            );

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->post(
                '/api/v1/disputes',
                [
                    'order_id' =>
                        $order->id,

                    'order_item_id' =>
                        $item->id,

                    'subject' =>
                        'Invalid attachment',

                    'message' =>
                        'Attempt invalid upload.',

                    'attachments' => [
                        UploadedFile::fake()
                            ->create(
                                'script.exe',
                                100,
                                'application/octet-stream'
                            ),
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attachments.0',
            ]);

        $this->assertDatabaseCount(
            'disputes',
            0
        );

        $this->assertDatabaseCount(
            'dispute_message_attachments',
            0
        );
    }

    public function test_more_than_five_attachments_are_rejected(): void
    {
        $buyer =
            $this->createBuyer();

        [
            $order,
            $item,
        ] =
            $this->createOrderFor(
                $buyer,
                'ORD-ATT-000005'
            );

        $attachments = [];

        foreach (
            range(1, 6)
            as $index
        ) {
            $attachments[] =
                UploadedFile::fake()
                    ->image(
                        "evidence-{$index}.jpg"
                    );
        }

        $this
            ->withToken(
                auth('api')->login(
                    $buyer
                )
            )
            ->post(
                '/api/v1/disputes',
                [
                    'order_id' =>
                        $order->id,

                    'order_item_id' =>
                        $item->id,

                    'subject' =>
                        'Too many files',

                    'message' =>
                        'There are too many attachments.',

                    'attachments' =>
                        $attachments,
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attachments',
            ]);

        $this->assertDatabaseCount(
            'disputes',
            0
        );
    }

    public function test_deleting_message_removes_private_attachment_file(): void
    {
        $buyer =
            $this->createBuyer();

        [
            $order,
            $item,
        ] =
            $this->createOrderFor(
                $buyer,
                'ORD-ATT-000006'
            );

        $create =
            $this
                ->withToken(
                    auth('api')->login(
                        $buyer
                    )
                )
                ->post(
                    '/api/v1/disputes',
                    [
                        'order_id' =>
                            $order->id,

                        'order_item_id' =>
                            $item->id,

                        'subject' =>
                            'Cleanup test',

                        'message' =>
                            'Attachment should be cleaned up.',

                        'attachments' => [
                            UploadedFile::fake()
                                ->image(
                                    'cleanup.jpg'
                                ),
                        ],
                    ],
                    [
                        'Accept' =>
                            'application/json',
                    ]
                );

        $create->assertCreated();

        $message =
            DisputeMessage::query()
                ->with(
                    'attachments'
                )
                ->firstOrFail();

        $attachment =
            $message
                ->attachments
                ->firstOrFail();

        $path =
            $attachment->path;

        Storage::disk(
            'local'
        )->assertExists(
            $path
        );

        $message->delete();

        $this->assertDatabaseMissing(
            'dispute_message_attachments',
            [
                'id' =>
                    $attachment->id,
            ]
        );

        Storage::disk(
            'local'
        )->assertMissing(
            $path
        );
    }

    private function createBuyer(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::User,
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' =>
                UserRole::Admin,
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
                    'Rice '.$orderNumber,

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
                    '45000.00',

                'unit' =>
                    'bag',

                'stock' =>
                    50,

                'minimum_order_quantity' =>
                    1,

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

        $item =
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
                    $produce->name,

                'category_name' =>
                    $category->name,

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
            $item,
        ];
    }
}