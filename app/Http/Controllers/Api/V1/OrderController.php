<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\IndexOrderRequest;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Listing;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        /*
         * Always begin through the authenticated buyer's
         * relationship so another buyer's orders can never
         * enter the result set through search or filters.
         */
        $orders = $request
            ->user()
            ->orders()
            ->with([
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
                        .$request->validated(
                            'search'
                        )
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

                                /*
                                 * Temporary legacy support for
                                 * orders that may still rely on
                                 * orders.listing_id instead of
                                 * order_items.
                                 */
                                ->orWhereHas(
                                    'listing.produce',
                                    function (
                                        Builder $produceQuery
                                    ) use ($search): void {
                                        $produceQuery->where(
                                            'name',
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

    public function store(
        StoreOrderRequest $request
    ): JsonResponse {
        $validated = $request->validated();

        $requestedItems = collect(
            $validated['items']
        );

        $order = DB::transaction(
            function () use (
                $request,
                $validated,
                $requestedItems
            ): Order {
                /*
                 * Lock listings in a predictable order.
                 */
                $listingIds = $requestedItems
                    ->pluck('listing_id')
                    ->map(
                        fn ($id) => (int) $id
                    )
                    ->sort()
                    ->values();

                $listings = Listing::query()
                    ->with([
                        'produce.category',
                        'farmer',
                    ])
                    ->whereIn(
                        'id',
                        $listingIds
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $preparedItems = [];
                $subtotal = '0.00';

                /*
                 * Revalidate stock while database rows
                 * are locked.
                 */
                foreach (
                    $requestedItems as $index => $item
                ) {
                    $listingId = (int) $item[
                        'listing_id'
                    ];

                    $quantity = (int) $item[
                        'quantity'
                    ];

                    $listing = $listings->get(
                        $listingId
                    );

                    if (! $listing) {
                        throw ValidationException::withMessages([
                            "items.{$index}.listing_id" => [
                                'Listing not found.',
                            ],
                        ]);
                    }

                    if (
                        $listing->status
                        !== ListingStatus::Active
                    ) {
                        throw ValidationException::withMessages([
                            "items.{$index}.listing_id" => [
                                'This listing is not available.',
                            ],
                        ]);
                    }

                    if (
                        $listing->stock
                        < $quantity
                    ) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => [
                                'Insufficient stock for this listing.',
                            ],
                        ]);
                    }

                    /*
                     * Prices always come from the database.
                     */
                    $lineTotal = bcmul(
                        (string) $listing->price,
                        (string) $quantity,
                        2
                    );

                    $subtotal = bcadd(
                        $subtotal,
                        $lineTotal,
                        2
                    );

                    $preparedItems[] = [
                        'listing_id' =>
                            $listing->id,

                        'farmer_id' =>
                            $listing->farmer_id,

                        'produce_id' =>
                            $listing->produce_id,

                        'produce_name' =>
                            $listing->produce->name,

                        'category_name' =>
                            $listing
                                ->produce
                                ->category?->name,

                        'unit' =>
                            $listing->unit,

                        'quantity' =>
                            $quantity,

                        'unit_price' =>
                            (string) $listing->price,

                        'discount_amount' =>
                            '0.00',

                        'line_total' =>
                            $lineTotal,
                    ];
                }

                $firstItem = $preparedItems[0];

                /*
                 * Delivery pricing is intentionally not
                 * invented yet.
                 *
                 * The server still owns the value, but both
                 * delivery methods currently cost 0 until the
                 * real business rule is confirmed.
                 */
                $deliveryFee = '0.00';

                $total = bcadd(
                    $subtotal,
                    $deliveryFee,
                    2
                );

                $order = Order::create([
                    'user_id' =>
                        $request->user()->id,

                    /*
                     * Temporary legacy bridge.
                     */
                    'listing_id' =>
                        $firstItem['listing_id'],

                    'quantity' =>
                        $firstItem['quantity'],

                    'subtotal' =>
                        $subtotal,

                    'delivery_fee' =>
                        $deliveryFee,

                    'total' =>
                        $total,

                    'status' =>
                        OrderStatus::New,

                    'payment_status' =>
                        'pending',

                    /*
                     * Delivery snapshot.
                     *
                     * This belongs to the order so changing
                     * the buyer's profile later cannot alter
                     * historical delivery information.
                     */
                    'delivery_method' =>
                        $validated['delivery_method'],

                    'delivery_name' =>
                        $validated['delivery_name'],

                    'delivery_phone' =>
                        $validated['delivery_phone'],

                    'delivery_state' =>
                        $validated['delivery_state'],

                    'delivery_lga' =>
                        $validated['delivery_lga'],

                    'delivery_address' =>
                        $validated['delivery_address'],

                    'delivery_notes' =>
                        $validated['delivery_notes']
                        ?? null,

                    'placed_at' =>
                        now(),
                ]);

                $order->update([
                    'order_number' =>
                        'ORD-'.str_pad(
                            (string) $order->id,
                            6,
                            '0',
                            STR_PAD_LEFT
                        ),
                ]);

                /*
                 * Stock changes only happen after all
                 * items have passed validation.
                 */
                foreach (
                    $preparedItems
                    as $preparedItem
                ) {
                    $listing = $listings->get(
                        $preparedItem[
                            'listing_id'
                        ]
                    );

                    $listing->decrement(
                        'stock',
                        $preparedItem[
                            'quantity'
                        ]
                    );

                    $order
                        ->items()
                        ->create(
                            $preparedItem
                        );
                }

                return $order;
            }
        );

        $order->load([
            'items.produce.category',
            'items.farmer',

            // Legacy compatibility.
            'listing.produce.category',
            'listing.farmer',
        ]);

        return response()->json([
            'data' =>
                new OrderResource($order),
        ], 201);
    }

    public function show(
        Request $request,
        Order $order
    ): JsonResponse {
        if (
            $order->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Order not found.',
            ], 404);
        }

        $order->load([
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

    public function cancel(
        Request $request,
        Order $order
    ): JsonResponse {
        if (
            $order->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Order not found.',
            ], 404);
        }

        if (! $order->isCancellable()) {
            return response()->json([
                'message' =>
                    'Only orders with status new can be cancelled.',
            ], 422);
        }

        $order = DB::transaction(
            function () use ($order): Order {
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    ! $lockedOrder->isCancellable()
                ) {
                    throw ValidationException::withMessages([
                        'order' => [
                            'Only orders with status new can be cancelled.',
                        ],
                    ]);
                }

                $this->restoreOrderStock(
                    $lockedOrder
                );

                $lockedOrder->update([
                    'status' =>
                        OrderStatus::Cancelled,

                    'cancelled_at' =>
                        now(),
                ]);

                return $lockedOrder->fresh([
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
        $order->loadMissing(
            'items'
        );

        if (
            $order->items->isNotEmpty()
        ) {
            $listingIds = $order
                ->items
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

            foreach (
                $order->items
                as $item
            ) {
                if (
                    ! $item->listing_id
                ) {
                    continue;
                }

                $listings
                    ->get(
                        $item->listing_id
                    )
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