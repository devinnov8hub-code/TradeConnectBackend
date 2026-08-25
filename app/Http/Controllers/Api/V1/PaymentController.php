<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\InitializePaymentRequest;
use App\Models\Order;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentController extends Controller
{
    public function initialize(
        InitializePaymentRequest $request,
        Order $order,
        PaystackService $paystack
    ): JsonResponse {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        if ($order->status !== OrderStatus::New) {
            return response()->json([
                'message' => 'Only new orders can be paid.',
            ], 422);
        }

        if ($order->payment_status === PaymentStatus::Paid) {
            return response()->json([
                'message' =>
                    'This order has already been paid.',
            ], 422);
        }

        /*
         * If checkout was already initialized, return
         * the existing Paystack checkout information.
         */
        if (
            $order->payment_status === PaymentStatus::Pending
            && $order->payment_provider === 'paystack'
            && $order->payment_reference
            && $order->payment_access_code
            && $order->payment_authorization_url
        ) {
            return response()->json([
                'data' => $this->initializationPayload(
                    $order,
                    $paystack
                ),
            ]);
        }

        $reference = sprintf(
            'TC-%s-%s',
            $order->order_number
                ?? 'ORD-'.$order->id,
            Str::upper(
                Str::random(12)
            )
        );

        /*
         * Order totals are stored in Naira.
         *
         * Paystack expects the lower denomination,
         * so ₦10,000 becomes 1,000,000 kobo.
         */
        $amount = $this->amountInSubunit(
            $order
        );

        try {
            $data = $paystack->initializeTransaction(
                email: $request->user()->email,
                amount: $amount,
                reference: $reference,
                metadata: [
                    'order_id' =>
                        $order->id,

                    'order_number' =>
                        $order->order_number,

                    'buyer_id' =>
                        $request->user()->id,
                ]
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' =>
                    'Unable to initialize payment at this time.',
            ], 502);
        }

        /*
         * Paystack should return the same reference
         * that our backend generated.
         */
        if (
            ($data['reference'] ?? null)
            !== $reference
        ) {
            Log::warning(
                'Paystack initialization reference mismatch.',
                [
                    'order_id' =>
                        $order->id,

                    'expected_reference' =>
                        $reference,

                    'received_reference' =>
                        $data['reference']
                        ?? null,
                ]
            );

            return response()->json([
                'message' =>
                    'Payment provider returned an invalid reference.',
            ], 502);
        }

        $order->update([
            'payment_status' =>
                PaymentStatus::Pending,

            'payment_provider' =>
                'paystack',

            'payment_reference' =>
                $reference,

            'payment_access_code' =>
                $data['access_code'],

            'payment_authorization_url' =>
                $data['authorization_url'],

            'payment_failed_at' =>
                null,

            'refunded_at' =>
                null,
        ]);

        $order->refresh();

        return response()->json([
            'data' => $this->initializationPayload(
                $order,
                $paystack
            ),
        ]);
    }

    public function verify(
        Request $request,
        Order $order,
        PaystackService $paystack
    ): JsonResponse {
        if (
            $order->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        /*
         * Verification is idempotent.
         */
        if (
            $order->payment_status
            === PaymentStatus::Paid
        ) {
            return response()->json([
                'data' =>
                    $this->verificationPayload(
                        $order,
                        'success'
                    ),
            ]);
        }

        if (
            $order->payment_provider
                !== 'paystack'
            || ! $order->payment_reference
        ) {
            return response()->json([
                'message' =>
                    'Payment has not been initialized for this order.',
            ], 422);
        }

        try {
            $data = $paystack->verifyTransaction(
                $order->payment_reference
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' =>
                    'Unable to verify payment at this time.',
            ], 502);
        }

        /*
         * Check reference first.
         */
        if (
            ($data['reference'] ?? null)
            !== $order->payment_reference
        ) {
            Log::warning(
                'Paystack verification reference mismatch.',
                [
                    'order_id' =>
                        $order->id,

                    'expected_reference' =>
                        $order->payment_reference,

                    'received_reference' =>
                        $data['reference']
                        ?? null,
                ]
            );

            return response()->json([
                'message' =>
                    'Payment reference does not match this order.',
            ], 409);
        }

        $providerStatus = (string) (
            $data['status']
            ?? ''
        );

        /*
         * response.status only tells us the API call worked.
         *
         * data.status is the actual transaction status.
         */
        if ($providerStatus === 'success') {
            $expectedAmount =
                $this->amountInSubunit(
                    $order
                );

            $receivedAmount =
                isset($data['amount'])
                    ? (string) $data['amount']
                    : '';

            /*
             * A successful status with the wrong amount
             * must NEVER mark our order as paid.
             */
            if (
                $receivedAmount
                !== $expectedAmount
            ) {
                Log::warning(
                    'Paystack verification amount mismatch.',
                    [
                        'order_id' =>
                            $order->id,

                        'reference' =>
                            $order->payment_reference,

                        'expected_amount' =>
                            $expectedAmount,

                        'received_amount' =>
                            $receivedAmount,
                    ]
                );

                return response()->json([
                    'message' =>
                        'Payment amount does not match the order total.',
                ], 409);
            }

            $expectedCurrency =
                $paystack->currency();

            $receivedCurrency =
                strtoupper(
                    (string) (
                        $data['currency']
                        ?? ''
                    )
                );

            if (
                $receivedCurrency
                !== $expectedCurrency
            ) {
                Log::warning(
                    'Paystack verification currency mismatch.',
                    [
                        'order_id' =>
                            $order->id,

                        'reference' =>
                            $order->payment_reference,

                        'expected_currency' =>
                            $expectedCurrency,

                        'received_currency' =>
                            $receivedCurrency,
                    ]
                );

                return response()->json([
                    'message' =>
                        'Payment currency does not match the order currency.',
                ], 409);
            }

            $order->update([
                'payment_status' =>
                    PaymentStatus::Paid,

                'paid_at' =>
                    $data['paid_at']
                    ?? now(),

                'payment_failed_at' =>
                    null,
            ]);
        } elseif (
            in_array(
                $providerStatus,
                [
                    'failed',
                    'abandoned',
                ],
                true
            )
        ) {
            $order->update([
                'payment_status' =>
                    PaymentStatus::Failed,

                'payment_failed_at' =>
                    now(),
            ]);
        }

        $order->refresh();

        return response()->json([
            'data' =>
                $this->verificationPayload(
                    $order,
                    $providerStatus
                ),
        ]);
    }

    private function amountInSubunit(
        Order $order
    ): string {
        return bcmul(
            (string) $order->total,
            '100',
            0
        );
    }

    private function initializationPayload(
        Order $order,
        PaystackService $paystack
    ): array {
        return [
            'order_id' =>
                $order->id,

            'order_number' =>
                $order->order_number,

            'payment_status' =>
                $order->payment_status->value,

            'provider' =>
                $order->payment_provider,

            'reference' =>
                $order->payment_reference,

            'authorization_url' =>
                $order->payment_authorization_url,

            'access_code' =>
                $order->payment_access_code,

            'amount' =>
                $this->amountInSubunit(
                    $order
                ),

            'currency' =>
                $paystack->currency(),
        ];
    }

    private function verificationPayload(
        Order $order,
        string $providerStatus
    ): array {
        return [
            'order_id' =>
                $order->id,

            'order_number' =>
                $order->order_number,

            'payment_status' =>
                $order->payment_status->value,

            'provider' =>
                $order->payment_provider,

            'reference' =>
                $order->payment_reference,

            'provider_status' =>
                $providerStatus,

            'paid_at' =>
                $order->paid_at,
        ];
    }
}