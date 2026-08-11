<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Listing;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::query()
            ->with([
                'user',

                'items.produce.category',
                'items.farmer',

                // Legacy compatibility.
                'listing.produce.category',
                'listing.farmer',
            ])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    public function show(
        Order $order
    ): JsonResponse {
        $order->load([
            'user',

            'items.produce.category',
            'items.farmer',

            // Legacy compatibility.
            'listing.produce.category',
            'listing.farmer',
        ]);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function update(
        UpdateOrderStatusRequest $request,
        Order $order
    ): JsonResponse {
        /*
         * Completed orders cannot be changed.
         */
        if (
            in_array(
                $order->status,
                [
                    OrderStatus::Delivered,
                    OrderStatus::Cancelled,
                ],
                true
            )
        ) {
            return response()->json([
                'message' =>
                    'This order can no longer be updated.',
            ], 422);
        }

        $newStatus = $request->enum(
            'status',
            OrderStatus::class
        );

        /*
         * A paid order cannot simply be cancelled.
         *
         * Once we implement refunds, cancellation of a paid
         * order will need to go through the refund workflow.
         */
        if (
            $newStatus === OrderStatus::Cancelled
            && $order->payment_status === PaymentStatus::Paid
        ) {
            return response()->json([
                'message' =>
                    'Paid orders cannot be cancelled until a refund has been processed.',
            ], 422);
        }

        $order = DB::transaction(
            function () use (
                $order,
                $newStatus
            ): Order {
                /*
                 * Reload and lock the order inside the transaction.
                 *
                 * This ensures nobody else can modify the same
                 * order while we're changing its status.
                 */
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Repeat the paid-order cancellation check after
                 * acquiring the database lock.
                 *
                 * The order may have changed between the original
                 * request check and this transaction.
                 */
                if (
                    $newStatus === OrderStatus::Cancelled
                    && $lockedOrder->payment_status
                        === PaymentStatus::Paid
                ) {
                    throw ValidationException::withMessages([
                        'order' => [
                            'Paid orders cannot be cancelled until a refund has been processed.',
                        ],
                    ]);
                }

                /*
                 * Also check the current status again after
                 * acquiring the database lock.
                 */
                if (
                    in_array(
                        $lockedOrder->status,
                        [
                            OrderStatus::Delivered,
                            OrderStatus::Cancelled,
                        ],
                        true
                    )
                ) {
                    throw ValidationException::withMessages([
                        'order' => [
                            'This order can no longer be updated.',
                        ],
                    ]);
                }

                $previousStatus =
                    $lockedOrder->status;

                $updates = [
                    'status' => $newStatus,
                ];

                if (
                    $newStatus
                    === OrderStatus::InTransit
                ) {
                    $updates['out_for_delivery_at'] =
                        $lockedOrder->out_for_delivery_at
                        ?? now();
                }

                if (
                    $newStatus
                    === OrderStatus::Delivered
                ) {
                    $updates['delivered_at'] =
                        $lockedOrder->delivered_at
                        ?? now();
                }

                if (
                    $newStatus
                    === OrderStatus::Cancelled
                ) {
                    $updates['cancelled_at'] =
                        $lockedOrder->cancelled_at
                        ?? now();

                    if (
                        $previousStatus
                        !== OrderStatus::Cancelled
                    ) {
                        $this->restoreOrderStock(
                            $lockedOrder
                        );
                    }
                }

                $lockedOrder->update(
                    $updates
                );

                return $lockedOrder->fresh([
                    'user',

                    'items.produce.category',
                    'items.farmer',

                    // Legacy compatibility.
                    'listing.produce.category',
                    'listing.farmer',
                ]);
            }
        );

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    private function restoreOrderStock(
        Order $order
    ): void {
        $order->loadMissing('items');

        /*
         * New multi-item orders.
         */
        if ($order->items->isNotEmpty()) {
            $listingIds = $order->items
                ->pluck('listing_id')
                ->filter()
                ->map(
                    fn ($id) => (int) $id
                )
                ->unique()
                ->sort()
                ->values();

            $listings = Listing::query()
                ->whereIn(
                    'id',
                    $listingIds
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($order->items as $item) {
                /*
                 * order_items.listing_id is nullable because
                 * historical order items may survive if a
                 * listing is deleted later.
                 */
                if (! $item->listing_id) {
                    continue;
                }

                $listings
                    ->get($item->listing_id)
                    ?->increment(
                        'stock',
                        $item->quantity
                    );
            }

            return;
        }

        /*
         * Legacy order support.
         */
        if (
            $order->listing_id
            && $order->quantity
        ) {
            Listing::query()
                ->whereKey(
                    $order->listing_id
                )
                ->lockForUpdate()
                ->first()
                ?->increment(
                    'stock',
                    $order->quantity
                );
        }
    }
}