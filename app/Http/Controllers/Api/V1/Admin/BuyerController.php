<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BuyerController extends Controller
{
    public function index(): JsonResponse
    {
        $buyers = User::query()
            ->where('role', UserRole::User)
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'role', 'created_at', 'updated_at']);

        return response()->json(['data' => $buyers]);
    }

    public function show(User $buyer): JsonResponse
    {
        if ($buyer->role !== UserRole::User) {
            return response()->json(['message' => 'Buyer not found.'], 404);
        }

        $buyer->loadCount('orders');

        return response()->json([
            'data' => [
                'id' => $buyer->id,
                'name' => $buyer->name,
                'email' => $buyer->email,
                'role' => $buyer->role->value,
                'orders_count' => $buyer->orders_count,
                'created_at' => $buyer->created_at,
                'updated_at' => $buyer->updated_at,
            ],
        ]);
    }
}
