<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Listing\IndexListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ListingController extends Controller
{
    public function index(
        IndexListingRequest $request
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

        $listings = Listing::query()
            ->with([
                'produce.category',
                'farmer',
            ])
            ->where(
                'status',
                ListingStatus::Active
            )
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
                                ->whereHas(
                                    'produce',
                                    fn (Builder $q) =>
                                        $q->where(
                                            'name',
                                            'like',
                                            $search
                                        )
                                )
                                ->orWhereHas(
                                    'farmer',
                                    fn (Builder $q) =>
                                        $q->where(
                                            'name',
                                            'like',
                                            $search
                                        )
                                )
                                ->orWhereHas(
                                    'produce.category',
                                    fn (Builder $q) =>
                                        $q->where(
                                            'name',
                                            'like',
                                            $search
                                        )
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled('category_id'),
                function (
                    Builder $query
                ) use ($request): void {
                    $query->whereHas(
                        'produce',
                        fn (Builder $q) =>
                            $q->where(
                                'category_id',
                                $request->validated(
                                    'category_id'
                                )
                            )
                    );
                }
            )
            ->when(
                $request->filled('farmer_id'),
                function (
                    Builder $query
                ) use ($request): void {
                    $query->where(
                        'farmer_id',
                        $request->validated(
                            'farmer_id'
                        )
                    );
                }
            )
            ->tap(
                fn (Builder $query) =>
                    $this->applySort(
                        $query,
                        $sort,
                        $order
                    )
            )
            ->paginate($perPage)
            ->withQueryString();

        return ListingResource::collection(
            $listings
        )->response();
    }

    public function show(
        Listing $listing
    ): JsonResponse {
        if (
            $listing->status
            !== ListingStatus::Active
        ) {
            return response()->json([
                'message' =>
                    'Listing not found.',
            ], 404);
        }

        $listing->load([
            'produce.category',
            'farmer',
        ]);

        return response()->json([
            'data' =>
                new ListingResource($listing),
        ]);
    }

    private function applySort(
        Builder $query,
        string $sort,
        string $order
    ): void {
        $direction =
            strtolower($order) === 'asc'
                ? 'asc'
                : 'desc';

        match ($sort) {
            'price' =>
                $query->orderBy(
                    'price',
                    $direction
                ),

            'stock' =>
                $query->orderBy(
                    'stock',
                    $direction
                ),

            'produce' =>
                $query
                    ->join(
                        'produce',
                        'listings.produce_id',
                        '=',
                        'produce.id'
                    )
                    ->orderBy(
                        'produce.name',
                        $direction
                    )
                    ->select(
                        'listings.*'
                    ),

            'farmer' =>
                $query
                    ->join(
                        'farmers',
                        'listings.farmer_id',
                        '=',
                        'farmers.id'
                    )
                    ->orderBy(
                        'farmers.name',
                        $direction
                    )
                    ->select(
                        'listings.*'
                    ),

            'category' =>
                $query
                    ->join(
                        'produce',
                        'listings.produce_id',
                        '=',
                        'produce.id'
                    )
                    ->join(
                        'categories',
                        'produce.category_id',
                        '=',
                        'categories.id'
                    )
                    ->orderBy(
                        'categories.name',
                        $direction
                    )
                    ->select(
                        'listings.*'
                    ),

            default =>
                $query->orderBy(
                    'created_at',
                    $direction
                ),
        };
    }
}