<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
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
                'message' => 'This order can no longer be updated.',
            ], 422);
        }

        $newStatus = $request->enum(
            'status',
            OrderStatus::class
        );

        $order = DB::transaction(
            function () use ($order, $newStatus): Order {
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

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

                $previousStatus = $lockedOrder->status;

                $updates = [
                    'status' => $newStatus,
                ];

                if ($newStatus === OrderStatus::InTransit) {
                    $updates['out_for_delivery_at'] =
                        $lockedOrder->out_for_delivery_at
                        ?? now();
                }

                if ($newStatus === OrderStatus::Delivered) {
                    $updates['delivered_at'] =
                        $lockedOrder->delivered_at
                        ?? now();
                }

                if ($newStatus === OrderStatus::Cancelled) {
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
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            $listings = Listing::query()
                ->whereIn('id', $listingIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($order->items as $item) {
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
        if ($order->listing_id && $order->quantity) {
            Listing::query()
                ->whereKey($order->listing_id)
                ->lockForUpdate()
                ->first()
                ?->increment(
                    'stock',
                    $order->quantity
                );
        }
    }
}