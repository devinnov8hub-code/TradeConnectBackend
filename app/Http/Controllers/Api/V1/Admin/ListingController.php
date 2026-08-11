<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreListingRequest;
use App\Http\Requests\Admin\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Farmer;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;

class ListingController extends Controller
{
    public function all(): JsonResponse
    {
        $listings = Listing::query()
            ->with(['produce.category', 'farmer'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => ListingResource::collection($listings),
        ]);
    }

    public function index(Farmer $farmer): JsonResponse
    {
        $listings = $farmer->listings()
            ->with(['produce.category', 'farmer'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => ListingResource::collection($listings),
        ]);
    }

    public function store(StoreListingRequest $request, Farmer $farmer): JsonResponse
    {
        $listing = $farmer->listings()->create($request->validated());
        $listing->load(['produce.category', 'farmer']);

        return response()->json([
            'data' => new ListingResource($listing),
        ], 201);
    }

    public function show(Listing $listing): JsonResponse
    {
        $listing->load(['produce.category', 'farmer']);

        return response()->json([
            'data' => new ListingResource($listing),
        ]);
    }

    public function update(UpdateListingRequest $request, Listing $listing): JsonResponse
    {
        $listing->update($request->validated());
        $listing->load(['produce.category', 'farmer']);

        return response()->json([
            'data' => new ListingResource($listing),
        ]);
    }

    public function destroy(Listing $listing): JsonResponse
    {
        $listing->delete();

        return response()->json(['message' => 'Listing deleted.']);
    }
}
