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
            ->with(['listing.produce.category'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = DB::transaction(function () use ($request) {
            $listing = Listing::query()
                ->lockForUpdate()
                ->findOrFail($request->validated('listing_id'));

            $quantity = $request->validated('quantity');

            if ($listing->status !== ListingStatus::Active) {
                throw ValidationException::withMessages([
                    'listing_id' => ['This listing is not available.'],
                ]);
            }

            if ($listing->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient stock for this listing.'],
                ]);
            }

            $listing->decrement('stock', $quantity);

            return Order::create([
                'user_id' => $request->user()->id,
                'listing_id' => $listing->id,
                'quantity' => $quantity,
                'total' => bcmul((string) $listing->price, (string) $quantity, 2),
                'status' => OrderStatus::New,
            ]);
        });

        $order->load(['listing.produce.category']);

        return response()->json([
            'data' => new OrderResource($order),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $order->load(['listing.produce.category']);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if (! $order->isCancellable()) {
            return response()->json([
                'message' => 'Only orders with status new can be cancelled.',
            ], 422);
        }

        $order = DB::transaction(function () use ($order) {
            $order->update(['status' => OrderStatus::Cancelled]);

            $order->listing()->lockForUpdate()->first()?->increment('stock', $order->quantity);

            return $order->fresh(['listing.produce.category']);
        });

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }
}
