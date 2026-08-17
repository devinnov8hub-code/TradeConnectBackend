<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
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
                 * Compatibility key retained for any existing
                 * frontend code consuming the old dashboard
                 * response.
                 */
                'active_users' =>
                    $activeBuyers,

                'new_buyers_this_week' =>
                    $newBuyersThisWeek,
            ],
        ]);
    }
}