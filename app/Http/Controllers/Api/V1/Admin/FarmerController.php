<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexFarmerRequest;
use App\Http\Requests\Admin\StoreFarmerRequest;
use App\Http\Requests\Admin\UpdateFarmerRequest;
use App\Http\Requests\Admin\UpdateFarmerStatusRequest;
use App\Http\Requests\Admin\UpdateFarmerVerificationRequest;
use App\Http\Resources\FarmerOrderSummaryResource;
use App\Http\Resources\FarmerResource;
use App\Http\Resources\ListingResource;
use App\Http\Resources\OrderResource;
use App\Models\Farmer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FarmerController extends Controller
{
    private const PROFILE_PREVIEW_LIMIT = 5;

    public function index(
        IndexFarmerRequest $request
    ): JsonResponse {
        $sort = $request->validated(
            'sort',
            'name'
        ) ?? 'name';

        $order = $request->validated(
            'order',
            'asc'
        ) ?? 'asc';

        $perPage = (int) (
            $request->validated(
                'per_page',
                20
            ) ?? 20
        );

        $farmers = Farmer::query()
            ->withCount('listings')
            ->when(
                $request->filled(
                    'search'
                ),
                function (
                    Builder $query
                ) use ($request): void {
                    $search = '%'
                        .$request
                            ->validated(
                                'search'
                            )
                        .'%';

                    $query->where(
                        function (
                            Builder $query
                        ) use ($search): void {
                            $query
                                ->where(
                                    'name',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'farmer_code',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'phone_number',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'farm_name',
                                    'like',
                                    $search
                                );
                        }
                    );
                }
            )
            ->when(
                $request->filled(
                    'state'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'state',
                        $request
                            ->validated(
                                'state'
                            )
                    )
            )
            ->when(
                $request->filled(
                    'lga'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'lga',
                        $request
                            ->validated(
                                'lga'
                            )
                    )
            )
            ->when(
                $request->filled(
                    'status'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        $request
                            ->validated(
                                'status'
                            )
                    )
            )
            ->when(
                $request->filled(
                    'verification_status'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'verification_status',
                        $request
                            ->validated(
                                'verification_status'
                            )
                    )
            )
            ->orderBy(
                $sort,
                strtolower(
                    $order
                ) === 'desc'
                    ? 'desc'
                    : 'asc'
            )
            ->paginate(
                $perPage
            )
            ->withQueryString();

        return FarmerResource::collection(
            $farmers
        )->response();
    }

    public function store(
        StoreFarmerRequest $request
    ): JsonResponse {
        $farmer = Farmer::create(
            $request->validated()
        );

        $farmer->refresh();

        return response()->json([
            'data' =>
                new FarmerResource(
                    $farmer
                ),
        ], 201);
    }

    public function show(
        Farmer $farmer
    ): JsonResponse {
        /*
         * Preserve the original v1 farmer-detail contract
         * while keeping the richer summary fields additive.
         *
         * Original keys such as listings, orders, orders_count,
         * and total_earned retain their legacy meaning.
         * Enhanced UI previews live under recent_listings and
         * recent_orders.
         */
        $farmer->loadCount(
            'listings'
        );

        $listingStatusCounts =
            $farmer
                ->listings()
                ->selectRaw(
                    'publication_status, COUNT(*) as aggregate'
                )
                ->groupBy(
                    'publication_status'
                )
                ->pluck(
                    'aggregate',
                    'publication_status'
                );

        /*
         * Enhanced farmer-scoped order query.
         *
         * Modern orders are associated through order_items.
         * Legacy orders that have no order_items yet are included
         * through their original listing relationship.
         */
        $farmerOrderQuery =
            Order::query()
                ->where(
                    function (
                        Builder $query
                    ) use ($farmer): void {
                        $query
                            ->whereHas(
                                'items',
                                fn (Builder $itemQuery) =>
                                    $itemQuery->where(
                                        'farmer_id',
                                        $farmer->id
                                    )
                            )
                            ->orWhere(
                                function (
                                    Builder $legacyOrderQuery
                                ) use ($farmer): void {
                                    $legacyOrderQuery
                                        ->whereDoesntHave(
                                            'items'
                                        )
                                        ->whereHas(
                                            'listing',
                                            fn (Builder $listingQuery) =>
                                                $listingQuery->where(
                                                    'farmer_id',
                                                    $farmer->id
                                                )
                                        );
                                }
                            );
                    }
                );

        $orderStatusCounts =
            (clone $farmerOrderQuery)
                ->selectRaw(
                    'status, COUNT(*) as aggregate'
                )
                ->groupBy(
                    'status'
                )
                ->pluck(
                    'aggregate',
                    'status'
                );

        $enhancedOrdersCount =
            (int) $orderStatusCounts
                ->sum();

        $completedOrdersCount =
            (int) (
                $orderStatusCounts->get(
                    OrderStatus::Delivered
                        ->value,
                    0
                )
            );

        $paidOrdersCount =
            (clone $farmerOrderQuery)
                ->where(
                    'payment_status',
                    PaymentStatus::Paid
                        ->value
                )
                ->count();

        /*
         * Preferred paid earnings metric for the enhanced UI.
         *
         * Modern order-item revenue is farmer-scoped so a parent
         * order containing other farmers is never over-counted.
         */
        $paidItemEarnings =
            OrderItem::query()
                ->where(
                    'farmer_id',
                    $farmer->id
                )
                ->whereHas(
                    'order',
                    fn (Builder $query) =>
                        $query->where(
                            'payment_status',
                            PaymentStatus::Paid
                                ->value
                        )
                )
                ->sum(
                    'line_total'
                );

        /*
         * A legacy paid order may not yet have order_items.
         * Include those parent totals only when no items exist.
         */
        $legacyPaidEarnings =
            Order::query()
                ->whereDoesntHave(
                    'items'
                )
                ->whereHas(
                    'listing',
                    fn (Builder $query) =>
                        $query->where(
                            'farmer_id',
                            $farmer->id
                        )
                )
                ->where(
                    'payment_status',
                    PaymentStatus::Paid
                        ->value
                )
                ->sum(
                    'total'
                );

        $paidTotalEarned =
            bcadd(
                (string) $paidItemEarnings,
                (string) $legacyPaidEarnings,
                2
            );

        /*
         * Original v1 collections and earnings semantics.
         *
         * The original endpoint returned every listing and every
         * order linked through orders.listing_id. total_earned was
         * the sum of non-cancelled parent-order totals, regardless
         * of payment status. Keep those exact meanings on the old
         * top-level keys for backwards compatibility.
         */
        $legacyListings =
            $farmer
                ->listings()
                ->with([
                    'produce.category',
                    'farmer',
                ])
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc(
                    'id'
                )
                ->get();

        $legacyOrders =
            $farmer
                ->orders()
                ->with([
                    'listing.produce.category',
                    'listing.farmer',
                    'items.produce.category',
                    'items.farmer',
                ])
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc(
                    'id'
                )
                ->get();

        $legacyOrdersCount =
            $legacyOrders
                ->count();

        $legacyTotalEarned =
            $legacyOrders
                ->reject(
                    fn (Order $order) =>
                        $order->status
                        === OrderStatus::Cancelled
                )
                ->sum(
                    fn (Order $order) =>
                        (float) $order->total
                );

        /*
         * Enhanced profile previews remain available under new,
         * additive keys so they no longer change the meaning of
         * the original listings/orders arrays.
         */
        $recentListings =
            $farmer
                ->listings()
                ->with([
                    'produce.category',
                    'farmer',
                ])
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc(
                    'id'
                )
                ->limit(
                    self::PROFILE_PREVIEW_LIMIT
                )
                ->get();

        $recentOrders =
            (clone $farmerOrderQuery)
                ->with([
                    'user',

                    'items' =>
                        function (
                            $query
                        ) use ($farmer): void {
                            $query
                                ->where(
                                    'farmer_id',
                                    $farmer->id
                                )
                                ->with([
                                    'produce.category',
                                    'farmer',
                                ]);
                        },
                ])
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc(
                    'id'
                )
                ->limit(
                    self::PROFILE_PREVIEW_LIMIT
                )
                ->get();

        $profile = (
            new FarmerResource(
                $farmer
            )
        )->toArray(
            request()
        );

        $formattedLegacyTotalEarned =
            number_format(
                (float) $legacyTotalEarned,
                2,
                '.',
                ''
            );

        $formattedPaidTotalEarned =
            number_format(
                (float) $paidTotalEarned,
                2,
                '.',
                ''
            );

        return response()->json([
            'data' => [
                ...$profile,

                /*
                 * Original v1 compatibility fields.
                 */
                'orders_count' =>
                    $legacyOrdersCount,

                'completed_orders_count' =>
                    $completedOrdersCount,

                'total_earned' =>
                    $formattedLegacyTotalEarned,

                /*
                 * Preferred structured summary for the enhanced
                 * farmer profile UI.
                 */
                'summary' => [
                    'listings' => [
                        'total' =>
                            (int)
                                $farmer
                                    ->listings_count,

                        'live' =>
                            (int) (
                                $listingStatusCounts
                                    ->get(
                                        ListingPublicationStatus::Live
                                            ->value,
                                        0
                                    )
                            ),

                        'pending' =>
                            (int) (
                                $listingStatusCounts
                                    ->get(
                                        ListingPublicationStatus::Pending
                                            ->value,
                                        0
                                    )
                            ),

                        'inactive' =>
                            (int) (
                                $listingStatusCounts
                                    ->get(
                                        ListingPublicationStatus::Inactive
                                            ->value,
                                        0
                                    )
                            ),
                    ],

                    'orders' => [
                        'total' =>
                            $enhancedOrdersCount,

                        'new' =>
                            (int) (
                                $orderStatusCounts
                                    ->get(
                                        OrderStatus::New
                                            ->value,
                                        0
                                    )
                            ),

                        'in_transit' =>
                            (int) (
                                $orderStatusCounts
                                    ->get(
                                        OrderStatus::InTransit
                                            ->value,
                                        0
                                    )
                            ),

                        'delivered' =>
                            $completedOrdersCount,

                        'cancelled' =>
                            (int) (
                                $orderStatusCounts
                                    ->get(
                                        OrderStatus::Cancelled
                                            ->value,
                                        0
                                    )
                            ),
                    ],

                    'sales' => [
                        'paid_orders_count' =>
                            $paidOrdersCount,

                        'total_earned' =>
                            $formattedPaidTotalEarned,
                    ],
                ],

                'preview_limit' =>
                    self::PROFILE_PREVIEW_LIMIT,

                /*
                 * Original complete collections.
                 */
                'listings' =>
                    ListingResource::collection(
                        $legacyListings
                    ),

                'orders' =>
                    OrderResource::collection(
                        $legacyOrders
                    ),

                /*
                 * Enhanced bounded previews.
                 */
                'recent_listings' =>
                    ListingResource::collection(
                        $recentListings
                    ),

                'recent_orders' =>
                    FarmerOrderSummaryResource::collection(
                        $recentOrders
                    ),
            ],
        ]);
    }

    public function update(
        UpdateFarmerRequest $request,
        Farmer $farmer
    ): JsonResponse {
        $farmer->update(
            $request->validated()
        );

        $farmer->refresh();

        return response()->json([
            'data' =>
                new FarmerResource(
                    $farmer
                ),
        ]);
    }

    public function updateStatus(
        UpdateFarmerStatusRequest $request,
        Farmer $farmer
    ): JsonResponse {
        $status =
            FarmerStatus::from(
                $request->validated(
                    'status'
                )
            );

        DB::transaction(
            function () use (
                $farmer,
                $status
            ): void {
                $farmer->forceFill([
                    'status' =>
                        $status,

                    /*
                     * Preserve the original suspension time
                     * when the same inactive request is
                     * repeated.
                     */
                    'suspended_at' =>
                        $status
                        === FarmerStatus::Inactive
                            ? (
                                $farmer
                                    ->suspended_at
                                ?? now()
                            )
                            : null,
                ])->save();

                $this
                    ->unpublishListingsIfIneligible(
                        $farmer
                    );
            }
        );

        $farmer->refresh();

        return response()->json([
            'data' =>
                new FarmerResource(
                    $farmer
                ),
        ]);
    }

    public function updateVerification(
        UpdateFarmerVerificationRequest $request,
        Farmer $farmer
    ): JsonResponse {
        $verificationStatus =
            FarmerVerificationStatus::from(
                $request->validated(
                    'verification_status'
                )
            );

        DB::transaction(
            function () use (
                $farmer,
                $verificationStatus
            ): void {
                $farmer->forceFill([
                    'verification_status' =>
                        $verificationStatus,

                    /*
                     * Preserve the original verification time
                     * if "verified" is submitted repeatedly.
                     *
                     * Pending/rejected farmers are not
                     * verified.
                     */
                    'verified_at' =>
                        $verificationStatus
                        === FarmerVerificationStatus::Verified
                            ? (
                                $farmer
                                    ->verified_at
                                ?? now()
                            )
                            : null,
                ])->save();

                $this
                    ->unpublishListingsIfIneligible(
                        $farmer
                    );
            }
        );

        $farmer->refresh();

        return response()->json([
            'data' =>
                new FarmerResource(
                    $farmer
                ),
        ]);
    }

    public function destroy(
        Farmer $farmer
    ): JsonResponse {
        $farmer->delete();

        return response()->json([
            'message' =>
                'Farmer deleted.',
        ]);
    }

    private function unpublishListingsIfIneligible(
        Farmer $farmer
    ): void {
        if (
            $farmer
                ->canPublishListings()
        ) {
            return;
        }

        /*
         * Becoming inactive, pending, or rejected
         * immediately removes marketplace publication.
         *
         * Pending listings remain pending. Only
         * currently live/active listings are forced
         * inactive.
         */
        $farmer
            ->listings()
            ->where(
                function (
                    Builder $query
                ): void {
                    $query
                        ->where(
                            'publication_status',
                            ListingPublicationStatus::Live
                                ->value
                        )
                        ->orWhere(
                            'status',
                            ListingStatus::Active
                                ->value
                        );
                }
            )
            ->update([
                'publication_status' =>
                    ListingPublicationStatus::Inactive
                        ->value,

                'status' =>
                    ListingStatus::Inactive
                        ->value,
            ]);
    }
}