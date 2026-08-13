<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexFarmerListingRequest;
use App\Http\Requests\Admin\IndexListingRequest;
use App\Http\Requests\Admin\StoreListingRequest;
use App\Http\Requests\Admin\UpdateListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Farmer;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class ListingController extends Controller
{
    public function all(
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
                                    'listings.unit',
                                    'like',
                                    $search
                                )
                                ->orWhereHas(
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
                                    function (
                                        Builder $q
                                    ) use ($search): void {
                                        $q
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
                $request->filled('farmer_id'),
                fn (Builder $query) =>
                    $query->where(
                        'listings.farmer_id',
                        $request->validated(
                            'farmer_id'
                        )
                    )
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
                $request->filled('status'),
                fn (Builder $query) =>
                    $query->where(
                        'listings.status',
                        $request->validated(
                            'status'
                        )
                    )
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

    public function index(
        IndexFarmerListingRequest $request,
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

        $listings = $farmer
            ->listings()
            ->with([
                'produce.category',
                'farmer',
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
                                    'listings.unit',
                                    'like',
                                    $search
                                )
                                ->orWhereHas(
                                    'produce',
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
                $request->filled('status'),
                fn (Builder $query) =>
                    $query->where(
                        'listings.status',
                        $request->validated(
                            'status'
                        )
                    )
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

    public function store(
        StoreListingRequest $request,
        Farmer $farmer
    ): JsonResponse {
        $listing = $farmer
            ->listings()
            ->create(
                $request->validated()
            );

        $listing->load([
            'produce.category',
            'farmer',
        ]);

        return response()->json([
            'data' =>
                new ListingResource($listing),
        ], 201);
    }

    public function show(
        Listing $listing
    ): JsonResponse {
        $listing->load([
            'produce.category',
            'farmer',
        ]);

        return response()->json([
            'data' =>
                new ListingResource($listing),
        ]);
    }

    public function update(
        UpdateListingRequest $request,
        Listing $listing
    ): JsonResponse {
        $listing->update(
            $request->validated()
        );

        $listing->load([
            'produce.category',
            'farmer',
        ]);

        return response()->json([
            'data' =>
                new ListingResource($listing),
        ]);
    }

    public function destroy(
        Listing $listing
    ): JsonResponse {
        $listing->delete();

        return response()->json([
            'message' =>
                'Listing deleted.',
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
                    'listings.price',
                    $direction
                ),

            'stock' =>
                $query->orderBy(
                    'listings.stock',
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
                    'listings.created_at',
                    $direction
                ),
        };
    }
}