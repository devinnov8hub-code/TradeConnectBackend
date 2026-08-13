<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FarmerStatus;
use App\Enums\ListingPublicationStatus;
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

        $today =
            now()
                ->startOfDay()
                ->toDateString();

        $listings = Listing::query()
            ->with([
                'produce.category',
                'farmer',
            ])

            /*
             * Marketplace visibility.
             *
             * publication_status is the new preferred
             * state while status remains temporarily
             * checked for compatibility/safety.
             */
            ->where(
                'listings.publication_status',
                ListingPublicationStatus::Live
                    ->value
            )
            ->where(
                'listings.status',
                ListingStatus::Active
                    ->value
            )

            /*
             * A listing belonging to an inactive farmer
             * should not remain visible even if its own
             * publication state still says live.
             */
            ->whereHas(
                'farmer',
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        FarmerStatus::Active
                            ->value
                    )
            )

            /*
             * Rich marketplace search.
             */
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
                                    'listings.description',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'listings.label',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'listings.grade',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'listings.unit',
                                    'like',
                                    $search
                                )
                                ->orWhereHas(
                                    'produce',
                                    fn (Builder $produceQuery) =>
                                        $produceQuery->where(
                                            'name',
                                            'like',
                                            $search
                                        )
                                )
                                ->orWhereHas(
                                    'produce.category',
                                    fn (Builder $categoryQuery) =>
                                        $categoryQuery->where(
                                            'name',
                                            'like',
                                            $search
                                        )
                                )
                                ->orWhereHas(
                                    'farmer',
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
                                                'state',
                                                'like',
                                                $search
                                            )
                                            ->orWhere(
                                                'lga',
                                                'like',
                                                $search
                                            );
                                    }
                                );
                        }
                    );
                }
            )

            /*
             * Catalog filters.
             */
            ->when(
                $request->filled(
                    'category_id'
                ),
                function (
                    Builder $query
                ) use ($request): void {
                    $query->whereHas(
                        'produce',
                        fn (Builder $produceQuery) =>
                            $produceQuery->where(
                                'category_id',
                                $request->validated(
                                    'category_id'
                                )
                            )
                    );
                }
            )
            ->when(
                $request->filled(
                    'farmer_id'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'listings.farmer_id',
                        $request->validated(
                            'farmer_id'
                        )
                    )
            )

            /*
             * Farmer location filters.
             */
            ->when(
                $request->filled('state'),
                function (
                    Builder $query
                ) use ($request): void {
                    $query->whereHas(
                        'farmer',
                        fn (Builder $farmerQuery) =>
                            $farmerQuery->where(
                                'state',
                                $request->validated(
                                    'state'
                                )
                            )
                    );
                }
            )
            ->when(
                $request->filled('lga'),
                function (
                    Builder $query
                ) use ($request): void {
                    $query->whereHas(
                        'farmer',
                        fn (Builder $farmerQuery) =>
                            $farmerQuery->where(
                                'lga',
                                $request->validated(
                                    'lga'
                                )
                            )
                    );
                }
            )

            /*
             * Listing label.
             */
            ->when(
                $request->filled('label'),
                fn (Builder $query) =>
                    $query->where(
                        'listings.label',
                        $request->validated(
                            'label'
                        )
                    )
            )

            /*
             * Availability projection.
             *
             * available:
             *   date-ready + enough stock for the
             *   minimum order.
             *
             * upcoming:
             *   future availability date.
             *
             * out_of_stock:
             *   date-ready but stock is below the
             *   minimum quantity required to order.
             */
            ->when(
                $request->filled(
                    'availability'
                ),
                function (
                    Builder $query
                ) use (
                    $request,
                    $today
                ): void {
                    $availability =
                        $request->validated(
                            'availability'
                        );

                    if (
                        $availability
                        === 'available'
                    ) {
                        $query
                            ->where(
                                function (
                                    Builder $dateQuery
                                ) use ($today): void {
                                    $dateQuery
                                        ->whereNull(
                                            'listings.available_from'
                                        )
                                        ->orWhereDate(
                                            'listings.available_from',
                                            '<=',
                                            $today
                                        );
                                }
                            )
                            ->whereColumn(
                                'listings.stock',
                                '>=',
                                'listings.minimum_order_quantity'
                            );

                        return;
                    }

                    if (
                        $availability
                        === 'upcoming'
                    ) {
                        $query->whereDate(
                            'listings.available_from',
                            '>',
                            $today
                        );

                        return;
                    }

                    if (
                        $availability
                        === 'out_of_stock'
                    ) {
                        $query
                            ->where(
                                function (
                                    Builder $dateQuery
                                ) use ($today): void {
                                    $dateQuery
                                        ->whereNull(
                                            'listings.available_from'
                                        )
                                        ->orWhereDate(
                                            'listings.available_from',
                                            '<=',
                                            $today
                                        );
                                }
                            )
                            ->whereColumn(
                                'listings.stock',
                                '<',
                                'listings.minimum_order_quantity'
                            );
                    }
                }
            )

            /*
             * Selling-price range.
             */
            ->when(
                $request->filled(
                    'min_price'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'listings.price',
                        '>=',
                        $request->validated(
                            'min_price'
                        )
                    )
            )
            ->when(
                $request->filled(
                    'max_price'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'listings.price',
                        '<=',
                        $request->validated(
                            'max_price'
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
            ->paginate(
                $perPage
            )
            ->withQueryString();

        return ListingResource::collection(
            $listings
        )->response();
    }

    public function show(
        Listing $listing
    ): JsonResponse {
        $listing->load([
            'produce.category',
            'farmer',
        ]);

        /*
         * Public detail follows the same visibility
         * contract as the marketplace list.
         */
        if (
            $listing->publication_status
                !== ListingPublicationStatus::Live
            || $listing->status
                !== ListingStatus::Active
            || $listing->farmer->status
                !== FarmerStatus::Active
        ) {
            return response()->json([
                'message' =>
                    'Listing not found.',
            ], 404);
        }

        return response()->json([
            'data' =>
                new ListingResource(
                    $listing
                ),
        ]);
    }

    private function applySort(
        Builder $query,
        string $sort,
        string $order
    ): void {
        $direction =
            strtolower($order)
                === 'asc'
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