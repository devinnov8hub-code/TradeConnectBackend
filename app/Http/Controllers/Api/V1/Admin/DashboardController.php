<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $now =
            now();

        $startOfToday =
            $now
                ->copy()
                ->startOfDay();

        $endOfToday =
            $now
                ->copy()
                ->endOfDay();

        $startOfWeek =
            $now
                ->copy()
                ->startOfWeek();

        /*
         * Dashboard comparison period.
         *
         * Compare the current month-to-date against the
         * equivalent portion of the previous month.
         *
         * Example:
         *
         * Aug 1 - Aug 17
         * compared with
         * Jul 1 - Jul 17
         *
         * This avoids comparing a partial current month
         * against an entire previous month.
         */
        $currentPeriodStart =
            $now
                ->copy()
                ->startOfMonth()
                ->startOfDay();

        $currentPeriodEnd =
            $now->copy();

        $previousPeriodEnd =
            $now
                ->copy()
                ->subMonthNoOverflow();

        $previousPeriodStart =
            $previousPeriodEnd
                ->copy()
                ->startOfMonth()
                ->startOfDay();

        /*
         * Prefer placed_at for modern orders.
         *
         * Older orders may not have placed_at, so created_at
         * remains the compatibility fallback.
         */
        $ordersToday =
            $this->countOrdersBetween(
                $startOfToday,
                $endOfToday
            );

        /*
         * Period comparison source counts.
         *
         * These percentages describe new activity during the
         * period rather than attempting to reconstruct a
         * historical snapshot of active/inactive state.
         */
        $currentOrders =
            $this->countOrdersBetween(
                $currentPeriodStart,
                $currentPeriodEnd
            );

        $previousOrders =
            $this->countOrdersBetween(
                $previousPeriodStart,
                $previousPeriodEnd
            );

        $currentListings =
            Listing::query()
                ->whereBetween(
                    'created_at',
                    [
                        $currentPeriodStart,
                        $currentPeriodEnd,
                    ]
                )
                ->count();

        $previousListings =
            Listing::query()
                ->whereBetween(
                    'created_at',
                    [
                        $previousPeriodStart,
                        $previousPeriodEnd,
                    ]
                )
                ->count();

        $currentFarmers =
            Farmer::query()
                ->whereBetween(
                    'created_at',
                    [
                        $currentPeriodStart,
                        $currentPeriodEnd,
                    ]
                )
                ->count();

        $previousFarmers =
            Farmer::query()
                ->whereBetween(
                    'created_at',
                    [
                        $previousPeriodStart,
                        $previousPeriodEnd,
                    ]
                )
                ->count();

        $currentBuyers =
            User::query()
                ->where(
                    'role',
                    UserRole::User
                )
                ->whereBetween(
                    'created_at',
                    [
                        $currentPeriodStart,
                        $currentPeriodEnd,
                    ]
                )
                ->count();

        $previousBuyers =
            User::query()
                ->where(
                    'role',
                    UserRole::User
                )
                ->whereBetween(
                    'created_at',
                    [
                        $previousPeriodStart,
                        $previousPeriodEnd,
                    ]
                )
                ->count();

        $activeBuyers =
            User::query()
                ->where(
                    'role',
                    UserRole::User
                )
                ->where(
                    'status',
                    UserStatus::Active
                )
                ->count();

        /*
         * "New buyers" measures registrations during the
         * current week.
         *
         * A buyer who registered this week but was later
         * deactivated still counts as a new registration.
         */
        $newBuyersThisWeek =
            User::query()
                ->where(
                    'role',
                    UserRole::User
                )
                ->where(
                    'created_at',
                    '>=',
                    $startOfWeek
                )
                ->count();

        /*
         * Dashboard action queue.
         *
         * Only non-terminal orders belong here.
         *
         * New orders are prioritised ahead of orders already
         * in transit. Within each status, older orders come
         * first.
         */
        $actionQueueOrders =
            Order::query()
                ->with([
                    'user',

                    'items.produce.category',
                    'items.farmer',

                    // Legacy compatibility.
                    'listing.produce.category',
                    'listing.farmer',
                ])
                ->whereIn(
                    'status',
                    [
                        OrderStatus::New,
                        OrderStatus::InTransit,
                    ]
                )
                ->orderByRaw(
                    'CASE
                        WHEN status = ? THEN 0
                        WHEN status = ? THEN 1
                        ELSE 2
                    END',
                    [
                        OrderStatus::New->value,
                        OrderStatus::InTransit->value,
                    ]
                )
                ->orderByRaw(
                    'COALESCE(placed_at, created_at) ASC'
                )
                ->orderBy(
                    'id'
                )
                ->limit(5)
                ->get()
                ->map(
                    fn (Order $order): array =>
                        $this->actionQueueItem(
                            $order
                        )
                )
                ->values();

        return response()->json([
            'data' => [
                'total_orders' =>
                    Order::query()
                        ->count(),

                'orders_change_percent' =>
                    $this->changePercent(
                        $currentOrders,
                        $previousOrders
                    ),

                'orders_today' =>
                    $ordersToday,

                'total_listings' =>
                    Listing::query()
                        ->count(),

                'listings_change_percent' =>
                    $this->changePercent(
                        $currentListings,
                        $previousListings
                    ),

                'pending_listings' =>
                    Listing::query()
                        ->where(
                            'publication_status',
                            ListingPublicationStatus::Pending
                        )
                        ->count(),

                'active_farmers' =>
                    Farmer::query()
                        ->where(
                            'status',
                            FarmerStatus::Active
                        )
                        ->count(),

                'farmers_change_percent' =>
                    $this->changePercent(
                        $currentFarmers,
                        $previousFarmers
                    ),

                'pending_farmer_verifications' =>
                    Farmer::query()
                        ->where(
                            'verification_status',
                            FarmerVerificationStatus::Pending
                        )
                        ->count(),

                'active_buyers' =>
                    $activeBuyers,

                'buyers_change_percent' =>
                    $this->changePercent(
                        $currentBuyers,
                        $previousBuyers
                    ),

                /*
                 * Compatibility key retained for existing
                 * frontend code consuming the original
                 * dashboard response.
                 */
                'active_users' =>
                    $activeBuyers,

                'new_buyers_this_week' =>
                    $newBuyersThisWeek,

                /*
                 * Make the meaning of the percentages
                 * explicit for frontend integration.
                 */
                'comparison' => [
                    'basis' =>
                        'month_to_date_vs_previous_month_to_date',

                    'current_period' => [
                        'start' =>
                            $currentPeriodStart
                                ->toISOString(),

                        'end' =>
                            $currentPeriodEnd
                                ->toISOString(),
                    ],

                    'previous_period' => [
                        'start' =>
                            $previousPeriodStart
                                ->toISOString(),

                        'end' =>
                            $previousPeriodEnd
                                ->toISOString(),
                    ],
                ],

                'order_action_queue' =>
                    $actionQueueOrders,

                'order_action_queue_count' =>
                    Order::query()
                        ->whereIn(
                            'status',
                            [
                                OrderStatus::New,
                                OrderStatus::InTransit,
                            ]
                        )
                        ->count(),
            ],
        ]);
    }

    private function countOrdersBetween(
        Carbon $start,
        Carbon $end
    ): int {
        return Order::query()
            ->where(
                function (
                    Builder $query
                ) use (
                    $start,
                    $end
                ): void {
                    $query
                        ->whereBetween(
                            'placed_at',
                            [
                                $start,
                                $end,
                            ]
                        )
                        ->orWhere(
                            function (
                                Builder $legacyQuery
                            ) use (
                                $start,
                                $end
                            ): void {
                                $legacyQuery
                                    ->whereNull(
                                        'placed_at'
                                    )
                                    ->whereBetween(
                                        'created_at',
                                        [
                                            $start,
                                            $end,
                                        ]
                                    );
                            }
                        );
                }
            )
            ->count();
    }

    private function changePercent(
        int $current,
        int $previous
    ): ?float {
        /*
         * Percentage growth from zero is mathematically
         * undefined.
         *
         * If both periods contain zero activity, return 0.
         * If only the previous period is zero, return null so
         * the frontend can show "New" or another appropriate
         * representation instead of an invented percentage.
         */
        if ($previous === 0) {
            return $current === 0
                ? 0.0
                : null;
        }

        return round(
            (
                (
                    $current
                    - $previous
                )
                / $previous
            )
            * 100,
            2
        );
    }

    private function actionQueueItem(
        Order $order
    ): array {
        $orderNumber =
            $order->order_number
            ?? 'ORD-'
                .str_pad(
                    (string)
                        $order->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                );

        $isPaid =
            $order->payment_status
            === PaymentStatus::Paid;

        /*
         * This describes the normal next fulfillment action.
         *
         * It does not perform the transition. The existing
         * PATCH /admin/orders/{order} endpoint remains the
         * authoritative mutation endpoint.
         */
        $nextStatus =
            match (
                $order->status
            ) {
                OrderStatus::New =>
                    OrderStatus::InTransit,

                OrderStatus::InTransit =>
                    OrderStatus::Delivered,

                default =>
                    null,
            };

        $action =
            match (
                $order->status
            ) {
                OrderStatus::New =>
                    'process_order',

                OrderStatus::InTransit =>
                    'complete_delivery',

                default =>
                    null,
            };

        $label =
            match (
                $order->status
            ) {
                OrderStatus::New =>
                    'Process order',

                OrderStatus::InTransit =>
                    'Complete delivery',

                default =>
                    null,
            };

        $placedAt =
            $order->placed_at
            ?? $order->created_at;

        return [
            'id' =>
                $order->id,

            'order_number' =>
                $orderNumber,

            'status' =>
                $order
                    ->status
                    ->value,

            'payment_status' =>
                $order
                    ->payment_status
                    ->value,

            'is_paid' =>
                $isPaid,

            'total' =>
                $order->total,

            'buyer' =>
                $order->user
                    ? [
                        'id' =>
                            $order
                                ->user
                                ->id,

                        'name' =>
                            $order
                                ->user
                                ->name,

                        'email' =>
                            $order
                                ->user
                                ->email,
                    ]
                    : null,

            'items_count' =>
                $order
                    ->items
                    ->isNotEmpty()
                        ? $order
                            ->items
                            ->count()
                        : (
                            $order->listing_id
                                ? 1
                                : 0
                        ),

            'action' => [
                'key' =>
                    $action,

                'label' =>
                    $label,

                'next_status' =>
                    $nextStatus?->value,

                'can_cancel' =>
                    $order
                        ->isCancellable(),

                'update_url' =>
                    '/api/v1/admin/orders/'
                    .$order->id,
            ],

            'placed_at' =>
                $placedAt,

            'detail_url' =>
                '/api/v1/admin/orders/'
                .$order->id,
        ];
    }
}