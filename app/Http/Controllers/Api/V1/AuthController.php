<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(
        RegisterRequest $request
    ): JsonResponse {
        /*
         * PoC behavior:
         * role selection remains available during
         * public registration.
         */
        $user = User::create(
            $request
                ->safe()
                ->only([
                    'name',
                    'email',
                    'password',
                    'role',
                ])
        );

        $token = auth('api')->login(
            $user
        );

        return $this->respondWithToken(
            $token,
            $user,
            201
        );
    }

    public function login(
        LoginRequest $request
    ): JsonResponse {
        $token = auth('api')->attempt(
            $request->only(
                'email',
                'password'
            )
        );

        if (! $token) {
            return response()->json([
                'message' =>
                    'Invalid credentials.',
            ], 401);
        }

        /** @var User $user */
        $user = auth('api')->user();

        /*
         * Credentials may be correct, but suspended accounts
         * must not receive a usable authenticated session.
         */
        if (
            $user->status
            === UserStatus::Inactive
        ) {
            auth('api')->logout();

            return response()->json([
                'message' =>
                    'Account is inactive.',
            ], 403);
        }

        return $this->respondWithToken(
            $token,
            $user
        );
    }

    public function me(
        Request $request
    ): JsonResponse {
        return response()->json([
            'data' =>
                $this->userPayload(
                    $request->user()
                ),
        ]);
    }

    private function respondWithToken(
        string $token,
        User $user,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'access_token' =>
                $token,

            'token_type' =>
                'bearer',

            'expires_in' =>
                auth('api')
                    ->factory()
                    ->getTTL()
                * 60,

            'user' =>
                $this->userPayload(
                    $user
                ),
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(
        User $user
    ): array {
        return [
            'id' =>
                $user->id,

            'account_code' =>
                $user->account_code,

            'name' =>
                $user->name,

            'email' =>
                $user->email,

            'phone_number' =>
                $user->phone_number,

            'state' =>
                $user->state,

            'lga' =>
                $user->lga,

            'address' =>
                $user->address,

            'avatar_path' =>
                $user->avatar_path,

            'role' =>
                $user->role->value,

            'status' =>
                $user->status->value,

            'created_at' =>
                $user->created_at,

            'updated_at' =>
                $user->updated_at,
        ];
    }
}