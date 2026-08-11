<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Listing;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with([
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

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $requestedItems = collect(
            $request->validated('items')
        );

        $order = DB::transaction(
            function () use ($request, $requestedItems): Order {
                /*
                 * Always lock listings in the same order.
                 *
                 * This reduces the chance of deadlocks when two
                 * buyers simultaneously order overlapping items.
                 */
                $listingIds = $requestedItems
                    ->pluck('listing_id')
                    ->map(fn ($id) => (int) $id)
                    ->sort()
                    ->values();

                $listings = Listing::query()
                    ->with([
                        'produce.category',
                        'farmer',
                    ])
                    ->whereIn('id', $listingIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $preparedItems = [];
                $subtotal = '0.00';

                /*
                 * Validate everything again while holding
                 * database row locks.
                 */
                foreach ($requestedItems as $index => $item) {
                    $listingId = (int) $item['listing_id'];
                    $quantity = (int) $item['quantity'];

                    $listing = $listings->get($listingId);

                    if (! $listing) {
                        throw ValidationException::withMessages([
                            "items.{$index}.listing_id" => [
                                'Listing not found.',
                            ],
                        ]);
                    }

                    if ($listing->status !== ListingStatus::Active) {
                        throw ValidationException::withMessages([
                            "items.{$index}.listing_id" => [
                                'This listing is not available.',
                            ],
                        ]);
                    }

                    if ($listing->stock < $quantity) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => [
                                'Insufficient stock for this listing.',
                            ],
                        ]);
                    }

                    /*
                     * Never accept price calculations from the client.
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
                        'listing_id' => $listing->id,
                        'farmer_id' => $listing->farmer_id,
                        'produce_id' => $listing->produce_id,

                        /*
                         * Historical snapshot.
                         */
                        'produce_name' => $listing->produce->name,
                        'category_name' => $listing
                            ->produce
                            ->category?->name,

                        /*
                         * Listing unit support comes in the
                         * marketplace/listings phase.
                         */
                        'unit' => null,

                        'quantity' => $quantity,
                        'unit_price' => (string) $listing->price,
                        'discount_amount' => '0.00',
                        'line_total' => $lineTotal,
                    ];
                }

                /*
                 * At this point every item passed validation.
                 *
                 * Nothing has been decremented yet.
                 */
                $firstItem = $preparedItems[0];

                /*
                 * We don't yet have the delivery pricing business
                 * rule, so the server currently owns a zero fee.
                 */
                $deliveryFee = '0.00';

                $total = bcadd(
                    $subtotal,
                    $deliveryFee,
                    2
                );

                $order = Order::create([
                    'user_id' => $request->user()->id,

                    /*
                     * Temporary bridge for the original database
                     * columns, which are currently non-null.
                     *
                     * New application logic uses order_items.
                     */
                    'listing_id' => $firstItem['listing_id'],
                    'quantity' => $firstItem['quantity'],

                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $total,

                    'status' => OrderStatus::New,
                    'payment_status' => 'pending',

                    'placed_at' => now(),
                ]);

                /*
                 * The database ID guarantees uniqueness for this
                 * simple order-number strategy.
                 */
                $order->update([
                    'order_number' => 'ORD-'.str_pad(
                        (string) $order->id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    ),
                ]);

                /*
                 * Only after every item has been validated do
                 * we mutate stock and create order items.
                 */
                foreach ($preparedItems as $preparedItem) {
                    $listing = $listings->get(
                        $preparedItem['listing_id']
                    );

                    $listing->decrement(
                        'stock',
                        $preparedItem['quantity']
                    );

                    $order->items()->create(
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
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(
        Request $request,
        Order $order
    ): JsonResponse {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Order not found.',
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
            'data' => new OrderResource($order),
        ]);
    }

    public function cancel(
        Request $request,
        Order $order
    ): JsonResponse {
        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        }

        if (! $order->isCancellable()) {
            return response()->json([
                'message' => 'Only orders with status new can be cancelled.',
            ], 422);
        }

        $order = DB::transaction(
            function () use ($order): Order {
                /*
                 * Lock the order too.
                 *
                 * Without this, two simultaneous cancellation
                 * requests could potentially restore stock twice.
                 */
                $lockedOrder = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedOrder->isCancellable()) {
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
                    'status' => OrderStatus::Cancelled,
                    'cancelled_at' => now(),
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
                /*
                 * listing_id is nullable on order_items because
                 * historical items can survive listing deletion.
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
         * Legacy orders created before order_items existed.
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