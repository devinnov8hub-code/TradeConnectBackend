<?php

namespace Tests\Feature\Order;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' =>
                'sk_test_tradeconnect',

            'services.paystack.base_url' =>
                'https://api.paystack.co',

            'services.paystack.currency' =>
                'NGN',
        ]);
    }

    public function test_buyer_can_initialize_paystack_payment(): void
    {
        [$buyer, $order] =
            $this->createOrder();

        Http::fake(
            function (Request $request) {
                return Http::response([
                    'status' => true,
                    'message' =>
                        'Authorization URL created',

                    'data' => [
                        'authorization_url' =>
                            'https://checkout.paystack.com/test-access',

                        'access_code' =>
                            'test-access',

                        'reference' =>
                            $request['reference'],
                    ],
                ], 200);
            }
        );

        $response = $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->postJson(
                "/api/v1/orders/{$order->id}/payment/initialize"
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.payment_status',
                'pending'
            )
            ->assertJsonPath(
                'data.provider',
                'paystack'
            )
            ->assertJsonPath(
                'data.amount',
                '1000000'
            )
            ->assertJsonPath(
                'data.currency',
                'NGN'
            );

        $reference =
            $response->json(
                'data.reference'
            );

        $this->assertNotEmpty(
            $reference
        );

        $this->assertDatabaseHas(
            'orders',
            [
                'id' =>
                    $order->id,

                'payment_status' =>
                    'pending',

                'payment_provider' =>
                    'paystack',

                'payment_reference' =>
                    $reference,

                'payment_access_code' =>
                    'test-access',
            ]
        );

        Http::assertSent(
            function (
                Request $request
            ) use ($buyer) {
                return $request['email']
                        === $buyer->email
                    && $request['amount']
                        === '1000000'
                    && $request['currency']
                        === 'NGN'
                    && $request->hasHeader(
                        'Authorization',
                        'Bearer sk_test_tradeconnect'
                    );
            }
        );
    }

    public function test_client_cannot_choose_payment_amount_or_reference(): void
    {
        [$buyer, $order] =
            $this->createOrder();

        Http::fake();

        $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->postJson(
                "/api/v1/orders/{$order->id}/payment/initialize",
                [
                    'amount' => 1,
                    'reference' =>
                        'FAKE-REFERENCE',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'amount',
                'reference',
            ]);

        Http::assertNothingSent();
    }

    public function test_successful_verification_marks_order_paid(): void
    {
        [$buyer, $order] =
            $this->createOrder();

        $this->preparePaystackOrder(
            $order,
            'TC-VERIFY-SUCCESS'
        );

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,
                    'message' =>
                        'Verification successful',

                    'data' => [
                        'status' =>
                            'success',

                        'reference' =>
                            'TC-VERIFY-SUCCESS',

                        'amount' =>
                            1000000,

                        'currency' =>
                            'NGN',

                        'paid_at' =>
                            '2026-08-11T14:30:00.000Z',
                    ],
                ], 200),
        ]);

        $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->postJson(
                "/api/v1/orders/{$order->id}/payment/verify"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.payment_status',
                'paid'
            )
            ->assertJsonPath(
                'data.provider_status',
                'success'
            );

        $order->refresh();

        $this->assertSame(
            PaymentStatus::Paid,
            $order->payment_status
        );

        $this->assertNotNull(
            $order->paid_at
        );
    }

    public function test_wrong_amount_does_not_mark_order_paid(): void
    {
        [$buyer, $order] =
            $this->createOrder();

        $this->preparePaystackOrder(
            $order,
            'TC-WRONG-AMOUNT'
        );

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,

                    'data' => [
                        'status' =>
                            'success',

                        'reference' =>
                            'TC-WRONG-AMOUNT',

                        'amount' =>
                            100,

                        'currency' =>
                            'NGN',
                    ],
                ], 200),
        ]);

        $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->postJson(
                "/api/v1/orders/{$order->id}/payment/verify"
            )
            ->assertStatus(409);

        $order->refresh();

        $this->assertSame(
            PaymentStatus::Pending,
            $order->payment_status
        );

        $this->assertNull(
            $order->paid_at
        );
    }

    public function test_failed_transaction_marks_payment_failed(): void
    {
        [$buyer, $order] =
            $this->createOrder();

        $this->preparePaystackOrder(
            $order,
            'TC-VERIFY-FAILED'
        );

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' =>
                Http::response([
                    'status' => true,

                    'data' => [
                        'status' =>
                            'failed',

                        'reference' =>
                            'TC-VERIFY-FAILED',

                        'amount' =>
                            1000000,

                        'currency' =>
                            'NGN',
                    ],
                ], 200),
        ]);

        $this
            ->withToken(
                auth('api')->login($buyer)
            )
            ->postJson(
                "/api/v1/orders/{$order->id}/payment/verify"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.payment_status',
                'failed'
            );

        $order->refresh();

        $this->assertSame(
            PaymentStatus::Failed,
            $order->payment_status
        );

        $this->assertNotNull(
            $order->payment_failed_at
        );
    }

    private function createOrder(): array
    {
        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $farmer =
            Farmer::create([
                'name' =>
                    'Ibrahim Musa',

                'state' =>
                    'Kaduna',

                'lga' =>
                    'Kagarko',

                'status' =>
                    FarmerStatus::Active,

                'phone_number' =>
                    '08012345678',
            ]);

        $category =
            Category::create([
                'name' => 'Grains',
            ]);

        $produce =
            Produce::create([
                'category_id' =>
                    $category->id,

                'name' =>
                    'Rice',

                'image' =>
                    base64_encode('rice'),

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
                    5000,

                'stock' =>
                    100,

                'status' =>
                    ListingStatus::Active,
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
                    'ORD-000001',

                'subtotal' =>
                    '10000.00',

                'delivery_fee' =>
                    0,

                'total' =>
                    '10000.00',

                'status' =>
                    OrderStatus::New,

                'payment_status' =>
                    PaymentStatus::Pending,
            ]);

        return [
            $buyer,
            $order,
        ];
    }

    private function preparePaystackOrder(
        Order $order,
        string $reference
    ): void {
        $order->update([
            'payment_status' =>
                PaymentStatus::Pending,

            'payment_provider' =>
                'paystack',

            'payment_reference' =>
                $reference,

            'payment_access_code' =>
                'access-code',

            'payment_authorization_url' =>
                'https://checkout.paystack.com/access-code',
        ]);
    }
}