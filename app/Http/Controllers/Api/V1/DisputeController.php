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
use App\Models\DisputeMessageAttachment;
use App\Models\Order;
use App\Models\User;
use App\Services\DisputeAttachmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class DisputeController extends Controller
{
    public function __construct(
        private readonly DisputeAttachmentService $attachmentService
    ) {
    }

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
                    'lastMessage.attachments',

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

        $storedPaths = [];

        try {
            $dispute =
                DB::transaction(
                    function () use (
                        $request,
                        $order,
                        $affectedOrderItem,
                        &$storedPaths
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

                        $dispute->markReadBy(
                            $request->user(),
                            $message
                        );

                        /*
                         * Store files last so there are no
                         * later business operations that can
                         * fail after successful filesystem
                         * writes.
                         */
                        $storedPaths =
                            $this
                                ->attachmentService
                                ->storeForMessage(
                                    $message,
                                    $request->file(
                                        'attachments',
                                        []
                                    )
                                );

                        return $dispute;
                    }
                );
        } catch (Throwable $exception) {
            /*
             * Also handles the rare case where database
             * transaction commit itself fails after files
             * were successfully written.
             */
            $this
                ->attachmentService
                ->deletePaths(
                    $storedPaths
                );

            throw $exception;
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

        $storedPaths = [];

        try {
            $message =
                DB::transaction(
                    function () use (
                        $request,
                        $dispute,
                        &$storedPaths
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

                        $storedPaths =
                            $this
                                ->attachmentService
                                ->storeForMessage(
                                    $message,
                                    $request->file(
                                        'attachments',
                                        []
                                    )
                                );

                        return $message;
                    }
                );
        } catch (Throwable $exception) {
            $this
                ->attachmentService
                ->deletePaths(
                    $storedPaths
                );

            throw $exception;
        }

        $message->load([
            'user',
            'attachments',
        ]);

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

    public function downloadAttachment(
        Request $request,
        Dispute $dispute,
        DisputeMessageAttachment $attachment
    ) {
        /*
         * A buyer may download evidence only from their
         * own dispute.
         */
        if (
            $dispute->user_id
            !== $request->user()->id
        ) {
            return response()->json([
                'message' =>
                    'Attachment not found.',
            ], 404);
        }

        $attachment->loadMissing(
            'message'
        );

        /*
         * Route model binding does not automatically
         * guarantee this attachment belongs to the
         * dispute in the URL.
         */
        if (
            ! $attachment->message
            || $attachment
                ->message
                ->dispute_id
            !== $dispute->id
        ) {
            return response()->json([
                'message' =>
                    'Attachment not found.',
            ], 404);
        }

        if (
            ! Storage::disk(
                'local'
            )->exists(
                $attachment->path
            )
        ) {
            return response()->json([
                'message' =>
                    'Attachment not found.',
            ], 404);
        }

        return Storage::disk(
            'local'
        )->download(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' =>
                    $attachment
                        ->mime_type,
            ]
        );
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
                'lastMessage.attachments',

                'resolvedBy',
                'closedBy',

                'messages' =>
                    fn ($query) =>
                        $query
                            ->with([
                                'user',
                                'attachments',
                            ])
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