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
         * Keep the existing listings_count field
         * available through FarmerResource.
         */
        $farmer->loadCount(
            'listings'
        );

        /*
         * Publication-state counts.
         *
         * Use publication_status rather than the
         * temporary legacy status field.
         */
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
         * Parent-order query scoped through this
         * farmer's order_items.
         *
         * whereHas means a multi-farmer parent order
         * is counted once for this farmer.
         */
        $farmerOrderQuery =
            Order::query()
                ->whereHas(
                    'items',
                    fn (Builder $query) =>
                        $query->where(
                            'farmer_id',
                            $farmer->id
                        )
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

        $ordersCount =
            $orderStatusCounts
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
         * Earnings remain line-item scoped.
         *
         * Never use parent order.total here because
         * the checkout may contain other farmers.
         */
        $totalEarned =
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
         * Farmer detail is now a preview endpoint,
         * not an unlimited history endpoint.
         *
         * Full paginated histories already exist at
         * /farmers/{farmer}/listings and
         * /farmers/{farmer}/orders.
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
                ->orderByDesc('id')
                ->limit(
                    self::PROFILE_PREVIEW_LIMIT
                )
                ->get();

        /*
         * Eager-load ONLY this farmer's items.
         *
         * FarmerOrderSummaryResource can therefore
         * calculate farmer_total safely on a
         * multi-farmer order.
         */
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
                ->orderByDesc('id')
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

        $formattedTotalEarned =
            number_format(
                (float) $totalEarned,
                2,
                '.',
                ''
            );

        return response()->json([
            'data' => [
                ...$profile,

                /*
                 * Existing compatibility fields.
                 */
                'orders_count' =>
                    (int) $ordersCount,

                'completed_orders_count' =>
                    $completedOrdersCount,

                'total_earned' =>
                    $formattedTotalEarned,

                /*
                 * Preferred structured summary for
                 * the farmer profile UI.
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
                            (int) $ordersCount,

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
                            $formattedTotalEarned,
                    ],
                ],

                /*
                 * These arrays are intentionally previews.
                 * Use the dedicated paginated endpoints
                 * for complete histories.
                 */
                'preview_limit' =>
                    self::PROFILE_PREVIEW_LIMIT,

                'listings' =>
                    ListingResource::collection(
                        $recentListings
                    ),

                'orders' =>
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