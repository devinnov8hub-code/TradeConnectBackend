<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexBuyerRequest;
use App\Http\Requests\Admin\UpdateBuyerStatusRequest;
use App\Http\Resources\BuyerResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class BuyerController extends Controller
{
    public function index(
        IndexBuyerRequest $request
    ): JsonResponse {
        $sort = $request->validated(
            'sort',
            'created_at'
        ) ?? 'created_at';

        $order = $request->validated(
            'order',
            'desc'
        ) ?? 'desc';

        $perPage = (int) (
            $request->validated(
                'per_page',
                20
            ) ?? 20
        );

        $buyers = User::query()
            ->where(
                'role',
                UserRole::User->value
            )
            ->withCount('orders')
            ->when(
                $request->filled('search'),
                function (
                    Builder $query
                ) use ($request): void {
                    $search = '%'
                        .$request->validated(
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
                                    'email',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'account_code',
                                    'like',
                                    $search
                                )
                                ->orWhere(
                                    'phone_number',
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
                        $request->validated(
                            'state'
                        )
                    )
            )
            ->when(
                $request->filled('lga'),
                fn (Builder $query) =>
                    $query->where(
                        'lga',
                        $request->validated(
                            'lga'
                        )
                    )
            )
            ->when(
                $request->filled('status'),
                fn (Builder $query) =>
                    $query->where(
                        'status',
                        $request->validated(
                            'status'
                        )
                    )
            )
            ->orderBy(
                $sort,
                strtolower($order) === 'asc'
                    ? 'asc'
                    : 'desc'
            )
            ->paginate($perPage)
            ->withQueryString();

        return BuyerResource::collection(
            $buyers
        )->response();
    }

    public function show(
        User $buyer
    ): JsonResponse {
        if (
            $buyer->role
            !== UserRole::User
        ) {
            return response()->json([
                'message' =>
                    'Buyer not found.',
            ], 404);
        }

        $buyer->loadCount(
            'orders'
        );

        return (
            new BuyerResource($buyer)
        )->response();
    }

    public function updateStatus(
        UpdateBuyerStatusRequest $request,
        User $buyer
    ): JsonResponse {
        /*
         * The {buyer} route binding uses the User model,
         * so explicitly prevent admin accounts from being
         * managed through buyer endpoints.
         */
        if (
            $buyer->role
            !== UserRole::User
        ) {
            return response()->json([
                'message' =>
                    'Buyer not found.',
            ], 404);
        }

        $status = UserStatus::from(
            $request->validated(
                'status'
            )
        );

        $buyer->forceFill([
            'status' =>
                $status,
        ])->save();

        $buyer->refresh();

        $buyer->loadCount(
            'orders'
        );

        return (
            new BuyerResource($buyer)
        )->response();
    }
}