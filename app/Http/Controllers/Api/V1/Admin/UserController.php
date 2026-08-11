<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'role', 'created_at', 'updated_at']);

        return response()->json(['data' => $users]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user->only(['id', 'name', 'email', 'role', 'created_at', 'updated_at']),
        ]);
    }
}
