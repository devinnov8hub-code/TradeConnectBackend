<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexFarmerOrderRequest;
use App\Http\Requests\Admin\IndexOrderRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    
    public function index(
        IndexOrderRequest $request
    ): JsonResponse {
        $sort = $request->validated(
            'sort',
            'created_at'
        ) ?? 'created_at';

        $order = $request->validated(
            'order',
            'desc'
        ) ?? 'desc';

        $perPage = (int) (
            $request->validated(
                'per_page',
                20
            ) ?? 20
        );

        $orders = Order::query()
            ->with([
                'user',

                'items.produce.category',
                'items.farmer',

                // Legacy compatibility.
                'listing.produce.category',
                'listing.farmer',
            ])
            ->when(
                $request->filled('search'),
                function (
                    Builder $query
                ) use ($request): void {
                    $search = '%'
                        .$request->validated('search')
                        .'%';

                    $query->where(
                        function (
                            Builder $query
                        ) use ($search): void {
                            $query
                                ->where(
                                    'orders.order_number',
                                    'like',
                                    $search
                                )
                                ->orWhereHas(
                                    'user',
                                    function (
                                        Builder $userQuery
                                    ) use ($search): void {
                                        $userQuery
                                            ->where(
                                                'name',
                                                'like',
                                                $search
                                            )
                                            ->orWhere(
                                                'email',
                                                'like',
                                                $search
                                            );
                                    }
                                )
                                ->orWhereHas(
                                    'items',
                                    function (
                                        Builder $itemQuery
                                    ) use ($search): void {
                                        $itemQuery->where(
                                            function (
                                                Builder $query
                                            ) use ($search): void {
                                                $query
                                                    ->where(
                                                        'produce_name',
                                                        'like',
                                                        $search
                                                    )
                                                    ->orWhere(
                                                        'category_name',
                                                        'like',
                                                        $search
                                                    );
                                            }
                                        );
                                    }
                                )
                                ->orWhereHas(
                                    'items.farmer',
                                    function (
                                        Builder $farmerQuery
                                    ) use ($search): void {
                                        $farmerQuery
                                            ->where(
                                                'name',
                                                'like',
                                                $search
                                            )
                                            ->orWhere(
                                                'farmer_code',
                                                'like',
                                                $search
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) =>
                    $query->where(
                        'orders.status',
                        $request->validated(
                            'status'
                        )
                    )
            )
            ->when(
                $request->filled(
                    'payment_status'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'orders.payment_status',
                        $request->validated(
                            'payment_status'
                        )
                    )
            )
            ->when(
                $request->filled('farmer_id'),
                function (
                    Builder $query
                ) use ($request): void {
                    $query->whereHas(
                        'items',
                        fn (Builder $itemQuery) =>
                            $itemQuery->where(
                                'farmer_id',
                                $request->validated(
                                    'farmer_id'
                                )
                            )
                    );
                }
            )
            ->orderBy(
                'orders.'.$sort,
                strtolower($order) === 'asc'
                    ? 'asc'
                    : 'desc'
            )
            ->paginate($perPage)
            ->withQueryString();

        return OrderResource::collection(
            $orders
        )->response();
    }

    public function farmerIndex(
        IndexFarmerOrderRequest $request,
        Farmer $farmer
    ): JsonResponse {
        $sort = $request->validated(
            'sort',
            'created_at'
        ) ?? 'created_at';

        $order = $request->validated(
            'order',
            'desc'
        ) ?? 'desc';

        $perPage = (int) (
            $request->validated(
                'per_page',
                20
            ) ?? 20
        );

        /*
         * A parent order belongs in this farmer's order
         * history when at least one order item belongs
         * to the farmer.
         *
         * We deliberately do not rely on orders.listing_id
         * because one parent order can contain items from
         * multiple farmers.
         */
        $orders = Order::query()
            ->whereHas(
                'items',
                fn (Builder $query) =>
                    $query->where(
                        'farmer_id',
                        $farmer->id
                    )
            )
            ->with([
                'user',

                'items.produce.category',
                'items.farmer',

                // Legacy compatibility.
                'listing.produce.category',
                'listing.farmer',
            ])
            ->when(
                $request->filled('search'),
                function (
                    Builder $query
                ) use (
                    $request,
                    $farmer
                ): void {
                    $search = '%'
                        .$request->validated('search')
                        .'%';

                    $query->where(
                        function (
                            Builder $query
                        ) use (
                            $search,
                            $farmer
                        ): void {
                            $query
                                ->where(
                                    'orders.order_number',
                                    'like',
                                    $search
                                )
                                ->orWhereHas(
                                    'user',
                                    function (
                                        Builder $userQuery
                                    ) use ($search): void {
                                        $userQuery
                                            ->where(
                                                'name',
                                                'like',
                                                $search
                                            )
                                            ->orWhere(
                                                'email',
                                                'like',
                                                $search
                                            );
                                    }
                                )
                                ->orWhereHas(
                                    'items',
                                    function (
                                        Builder $itemQuery
                                    ) use (
                                        $farmer,
                                        $search
                                    ): void {
                                        $itemQuery
                                            ->where(
                                                'farmer_id',
                                                $farmer->id
                                            )
                                            ->where(
                                                function (
                                                    Builder $query
                                                ) use ($search): void {
                                                    $query
                                                        ->where(
                                                            'produce_name',
                                                            'like',
                                                            $search
                                                        )
                                                        ->orWhere(
                                                            'category_name',
                                                            'like',
                                                            $search
                                                        );
                                                }
                                            );
                                    }
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) =>
                    $query->where(
                        'orders.status',
                        $request->validated(
                            'status'
                        )
                    )
            )
            ->when(
                $request->filled(
                    'payment_status'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'orders.payment_status',
                        $request->validated(
                            'payment_status'
                        )
                    )
            )
            ->orderBy(
                'orders.'.$sort,
                strtolower($order) === 'asc'
                    ? 'asc'
                    : 'desc'
            )
            ->paginate($perPage)
            ->withQueryString();

        return OrderResource::collection(
            $orders
        )->response();
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
            'data' =>
                new OrderResource($order),
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
            && $order->payment_status
                === PaymentStatus::Paid
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
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

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
            'data' =>
                new OrderResource($order),
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