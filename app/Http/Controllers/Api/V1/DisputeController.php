<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispute\IndexDisputeRequest;
use App\Http\Requests\Dispute\StoreDisputeMessageRequest;
use App\Http\Requests\Dispute\StoreDisputeRequest;
use App\Http\Resources\DisputeMessageResource;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    public function index(
        IndexDisputeRequest $request
    ): JsonResponse {
        $sort =
            $request->validated(
                'sort',
                'created_at'
            )
            ?? 'created_at';

        $order =
            $request->validated(
                'order',
                'desc'
            )
            ?? 'desc';

        $perPage =
            (int) (
                $request->validated(
                    'per_page',
                    20
                )
                ?? 20
            );

        $buyer =
            $request->user();

        $disputes =
            $buyer
                ->disputes()
                ->with([
                    'user',

                    'order',

                    'affectedOrderItem.produce.category',
                    'affectedOrderItem.farmer',

                    'lastMessage.user',

                    'resolvedBy',
                    'closedBy',
                ])
                ->withCount(
                    'messages'
                )
                ->withUnreadCountFor(
                    $buyer
                )
                ->when(
                    $request->filled(
                        'search'
                    ),
                    function (
                        Builder $query
                    ) use ($request): void {
                        $search =
                            '%'
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
                                        'subject',
                                        'like',
                                        $search
                                    )
                                    ->orWhereHas(
                                        'order',
                                        fn (
                                            Builder $orderQuery
                                        ) =>
                                            $orderQuery
                                                ->where(
                                                    'order_number',
                                                    'like',
                                                    $search
                                                )
                                    )
                                    ->orWhereHas(
                                        'affectedOrderItem',
                                        function (
                                            Builder $itemQuery
                                        ) use ($search): void {
                                            $itemQuery
                                                ->where(
                                                    'produce_name',
                                                    'like',
                                                    $search
                                                )
                                                ->orWhere(
                                                    'category_name',
                                                    'like',
                                                    $search
                                                );
                                        }
                                    );
                            }
                        );
                    }
                )
                ->when(
                    $request->filled(
                        'status'
                    ),
                    fn (
                        Builder $query
                    ) =>
                        $query->where(
                            'status',
                            $request
                                ->validated(
                                    'status'
                                )
                        )
                )
                ->when(
                    $request->boolean(
                        'unread'
                    ),
                    fn (
                        Builder $query
                    ) =>
                        $query
                            ->whereUnreadFor(
                                $buyer
                            )
                )
                ->orderBy(
                    $sort,
                    strtolower(
                        $order
                    ) === 'asc'
                        ? 'asc'
                        : 'desc'
                )
                ->paginate(
                    $perPage
                )
                ->withQueryString();

        return DisputeResource::collection(
            $disputes
        )->response();
    }

    public function store(
        StoreDisputeRequest $request
    ): JsonResponse {
        $order =
            Order::query()
                ->with('items')
                ->findOrFail(
                    $request->validated(
                        'order_id'
                    )
                );

        if (
            $order->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Order not found.',
            ], 404);
        }

        if (
            $order->status
            === OrderStatus::Cancelled
        ) {
            throw ValidationException::withMessages([
                'order_id' => [
                    'Cancelled orders cannot be disputed.',
                ],
            ]);
        }

        if (
            $order
                ->dispute()
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'order_id' => [
                    'A dispute already exists for this order.',
                ],
            ]);
        }

        $affectedOrderItem =
            null;

        if (
            $request->filled(
                'order_item_id'
            )
        ) {
            $affectedOrderItem =
                $order
                    ->items
                    ->firstWhere(
                        'id',
                        (int)
                            $request
                                ->validated(
                                    'order_item_id'
                                )
                    );

            if (
                ! $affectedOrderItem
            ) {
                throw ValidationException::withMessages([
                    'order_item_id' => [
                        'The selected order item does not belong to this order.',
                    ],
                ]);
            }
        }

        $dispute =
            DB::transaction(
                function () use (
                    $request,
                    $order,
                    $affectedOrderItem
                ): Dispute {
                    $dispute =
                        Dispute::create([
                            'order_id' =>
                                $order->id,

                            'order_item_id' =>
                                $affectedOrderItem?->id,

                            'user_id' =>
                                $request
                                    ->user()
                                    ->id,

                            'subject' =>
                                $request
                                    ->validated(
                                        'subject'
                                    ),

                            'status' =>
                                DisputeStatus::UnderReview,
                        ]);

                    $message =
                        $dispute
                            ->messages()
                            ->create([
                                'user_id' =>
                                    $request
                                        ->user()
                                        ->id,

                                'body' =>
                                    $request
                                        ->validated(
                                            'message'
                                        ),
                            ]);

                    /*
                     * A user has necessarily read the
                     * message they just submitted.
                     */
                    $dispute->markReadBy(
                        $request->user(),
                        $message
                    );

                    return $dispute;
                }
            );

        $this->prepareForViewer(
            $dispute,
            $request->user()
        );

        return response()->json([
            'data' =>
                new DisputeResource(
                    $dispute
                ),
        ], 201);
    }

    public function show(
        Request $request,
        Dispute $dispute
    ): JsonResponse {
        if (
            $dispute->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Dispute not found.',
            ], 404);
        }

        $this->prepareForViewer(
            $dispute,
            $request->user()
        );

        return response()->json([
            'data' =>
                new DisputeResource(
                    $dispute
                ),
        ]);
    }

    public function storeMessage(
        StoreDisputeMessageRequest $request,
        Dispute $dispute
    ): JsonResponse {
        if (
            $dispute->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Dispute not found.',
            ], 404);
        }

        if (
            ! $dispute
                ->canReceiveMessages()
        ) {
            return response()->json([
                'message' =>
                    'Messages can only be sent on disputes under review.',
            ], 422);
        }

        $message =
            DB::transaction(
                function () use (
                    $request,
                    $dispute
                ) {
                    $message =
                        $dispute
                            ->messages()
                            ->create([
                                'user_id' =>
                                    $request
                                        ->user()
                                        ->id,

                                'body' =>
                                    $request
                                        ->validated(
                                            'message'
                                        ),
                            ]);

                    $dispute->markReadBy(
                        $request->user(),
                        $message
                    );

                    return $message;
                }
            );

        $message->load(
            'user'
        );

        return response()->json([
            'data' =>
                new DisputeMessageResource(
                    $message
                ),
        ], 201);
    }

    public function markRead(
        Request $request,
        Dispute $dispute
    ): JsonResponse {
        if (
            $dispute->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Dispute not found.',
            ], 404);
        }

        $dispute->markReadBy(
            $request->user()
        );

        $this->prepareForViewer(
            $dispute,
            $request->user()
        );

        return response()->json([
            'data' =>
                new DisputeResource(
                    $dispute
                ),
        ]);
    }

    private function prepareForViewer(
        Dispute $dispute,
        User $viewer
    ): void {
        $dispute
            ->load([
                'user',

                'order.listing.produce',

                'order.items.produce.category',
                'order.items.farmer',

                'affectedOrderItem.produce.category',
                'affectedOrderItem.farmer',

                'lastMessage.user',

                'resolvedBy',
                'closedBy',

                'messages' =>
                    fn ($query) =>
                        $query
                            ->with('user')
                            ->orderBy(
                                'created_at'
                            )
                            ->orderBy('id'),
            ])
            ->loadCount(
                'messages'
            );

        $dispute->setAttribute(
            'unread_count',
            $dispute
                ->unreadCountFor(
                    $viewer
                )
        );
    }
}