<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexFarmerRequest;
use App\Http\Requests\Admin\StoreFarmerRequest;
use App\Http\Requests\Admin\UpdateFarmerRequest;
use App\Http\Resources\FarmerResource;
use App\Http\Resources\ListingResource;
use App\Http\Resources\OrderResource;
use App\Models\Farmer;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class FarmerController extends Controller
{
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
                $request->filled('state'),
                fn (Builder $query) =>
                    $query->where(
                        'state',
                        $request->validated('state')
                    )
            )
            ->when(
                $request->filled('lga'),
                fn (Builder $query) =>
                    $query->where(
                        'lga',
                        $request->validated('lga')
                    )
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        $request->validated('status')
                    )
            )
            ->when(
                $request->filled(
                    'verification_status'
                ),
                fn (Builder $query) =>
                    $query->where(
                        'verification_status',
                        $request->validated(
                            'verification_status'
                        )
                    )
            )
            ->orderBy(
                $sort,
                strtolower($order) === 'desc'
                    ? 'desc'
                    : 'asc'
            )
            ->paginate($perPage)
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
                new FarmerResource($farmer),
        ], 201);
    }

    public function show(
        Farmer $farmer
    ): JsonResponse {
        $farmer
            ->load([
                'listings' =>
                    fn ($query) =>
                        $query
                            ->with([
                                'produce.category',
                                'farmer',
                            ])
                            ->orderByDesc(
                                'created_at'
                            ),
            ])
            ->loadCount('listings');

        /*
         * An order belongs to this farmer when one or more
         * order_items belong to the farmer.
         *
         * This works correctly for multi-farmer orders.
         */
        $orders = Order::query()
            ->whereHas(
                'items',
                fn (Builder $query) =>
                    $query->where(
                        'farmer_id',
                        $farmer->id
                    )
            )
            ->with([
                'user',
                'items.produce.category',
                'items.farmer',

                // Legacy compatibility.
                'listing.produce.category',
                'listing.farmer',
            ])
            ->orderByDesc('created_at')
            ->get();

        $ordersCount =
            $orders->count();

        $completedOrdersCount =
            $orders
                ->filter(
                    fn (Order $order) =>
                        $order->status
                        === OrderStatus::Delivered
                )
                ->count();

        /*
         * Only this farmer's item lines from orders whose
         * payment has actually been confirmed count toward
         * earnings.
         */
        $totalEarned = OrderItem::query()
            ->where(
                'farmer_id',
                $farmer->id
            )
            ->whereHas(
                'order',
                fn (Builder $query) =>
                    $query->where(
                        'payment_status',
                        PaymentStatus::Paid->value
                    )
            )
            ->sum('line_total');

        $profile = (
            new FarmerResource($farmer)
        )->toArray(request());

        return response()->json([
            'data' => [
                ...$profile,

                'orders_count' =>
                    $ordersCount,

                'completed_orders_count' =>
                    $completedOrdersCount,

                'total_earned' =>
                    number_format(
                        (float) $totalEarned,
                        2,
                        '.',
                        ''
                    ),

                'listings' =>
                    ListingResource::collection(
                        $farmer->listings
                    ),

                'orders' =>
                    OrderResource::collection(
                        $orders
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
                new FarmerResource($farmer),
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
}