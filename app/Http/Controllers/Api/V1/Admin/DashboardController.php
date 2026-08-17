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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $startOfToday =
            now()->startOfDay();

        $endOfToday =
            now()->endOfDay();

        $startOfWeek =
            now()->startOfWeek();

        /*
         * Prefer placed_at for modern orders.
         *
         * Older orders may not have placed_at, so their
         * created_at timestamp remains the compatibility
         * fallback for the "orders today" metric.
         */
        $ordersToday =
            Order::query()
                ->where(
                    function (
                        Builder $query
                    ) use (
                        $startOfToday,
                        $endOfToday
                    ): void {
                        $query
                            ->whereBetween(
                                'placed_at',
                                [
                                    $startOfToday,
                                    $endOfToday,
                                ]
                            )
                            ->orWhere(
                                function (
                                    Builder $legacyQuery
                                ) use (
                                    $startOfToday,
                                    $endOfToday
                                ): void {
                                    $legacyQuery
                                        ->whereNull(
                                            'placed_at'
                                        )
                                        ->whereBetween(
                                            'created_at',
                                            [
                                                $startOfToday,
                                                $endOfToday,
                                            ]
                                        );
                                }
                            );
                    }
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
         * first so long-waiting work naturally rises to the
         * top of the admin queue.
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

                'orders_today' =>
                    $ordersToday,

                'total_listings' =>
                    Listing::query()
                        ->count(),

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

                'pending_farmer_verifications' =>
                    Farmer::query()
                        ->where(
                            'verification_status',
                            FarmerVerificationStatus::Pending
                        )
                        ->count(),

                'active_buyers' =>
                    $activeBuyers,

                /*
                 * Compatibility key retained for existing
                 * frontend code consuming the original
                 * dashboard response.
                 */
                'active_users' =>
                    $activeBuyers,

                'new_buyers_this_week' =>
                    $newBuyersThisWeek,

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

                /*
                 * Existing cancellation policy:
                 * only new unpaid orders are cancellable.
                 */
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