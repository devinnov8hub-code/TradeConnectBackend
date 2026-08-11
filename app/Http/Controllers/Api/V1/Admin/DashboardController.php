<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FarmerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_orders' => Order::query()->count(),
                'total_listings' => Listing::query()->count(),
                'active_farmers' => Farmer::query()
                    ->where('status', FarmerStatus::Active)
                    ->count(),
                'active_users' => User::query()
                    ->where('role', UserRole::User)
                    ->count(),
            ],
        ]);
    }
}
