<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFarmerRequest;
use App\Http\Requests\Admin\UpdateFarmerRequest;
use App\Http\Resources\ListingResource;
use App\Http\Resources\OrderResource;
use App\Models\Farmer;
use Illuminate\Http\JsonResponse;

class FarmerController extends Controller
{
    public function index(): JsonResponse
    {
        $farmers = Farmer::query()
            ->withCount('listings')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $farmers]);
    }

    public function store(StoreFarmerRequest $request): JsonResponse
    {
        $farmer = Farmer::create($request->validated());

        return response()->json(['data' => $farmer], 201);
    }

    public function show(Farmer $farmer): JsonResponse
    {
        $farmer->load([
            'listings' => fn ($query) => $query->with(['produce.category', 'farmer'])->orderByDesc('created_at'),
            'orders' => fn ($query) => $query->with(['listing.produce.category'])->orderByDesc('created_at'),
        ])->loadCount('listings');

        $ordersCount = $farmer->orders->count();
        $totalEarned = $farmer->orders
            ->reject(fn ($order) => $order->status === OrderStatus::Cancelled)
            ->sum(fn ($order) => (float) $order->total);

        return response()->json([
            'data' => [
                'id' => $farmer->id,
                'name' => $farmer->name,
                'state' => $farmer->state,
                'lga' => $farmer->lga,
                'status' => $farmer->status->value,
                'phone_number' => $farmer->phone_number,
                'listings_count' => $farmer->listings_count,
                'orders_count' => $ordersCount,
                'total_earned' => number_format($totalEarned, 2, '.', ''),
                'listings' => ListingResource::collection($farmer->listings),
                'orders' => OrderResource::collection($farmer->orders),
                'created_at' => $farmer->created_at,
                'updated_at' => $farmer->updated_at,
            ],
        ]);
    }

    public function update(UpdateFarmerRequest $request, Farmer $farmer): JsonResponse
    {
        $farmer->update($request->validated());

        return response()->json(['data' => $farmer->fresh()]);
    }

    public function destroy(Farmer $farmer): JsonResponse
    {
        $farmer->delete();

        return response()->json(['message' => 'Farmer deleted.']);
    }
}
