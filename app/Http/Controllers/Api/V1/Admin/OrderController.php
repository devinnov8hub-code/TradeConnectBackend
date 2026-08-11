<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = Order::query()
            ->with(['user', 'listing.produce.category', 'listing.farmer'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => OrderResource::collection($orders),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['user', 'listing.produce.category', 'listing.farmer']);

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }

    public function update(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        if (in_array($order->status, [OrderStatus::Delivered, OrderStatus::Cancelled], true)) {
            return response()->json([
                'message' => 'This order can no longer be updated.',
            ], 422);
        }

        $newStatus = $request->enum('status', OrderStatus::class);

        $order = DB::transaction(function () use ($order, $newStatus) {
            $previousStatus = $order->status;

            $order->update(['status' => $newStatus]);

            if ($newStatus === OrderStatus::Cancelled && $previousStatus !== OrderStatus::Cancelled) {
                $order->listing()->lockForUpdate()->first()?->increment('stock', $order->quantity);
            }

            return $order->fresh(['user', 'listing.produce.category', 'listing.farmer']);
        });

        return response()->json([
            'data' => new OrderResource($order),
        ]);
    }
}
