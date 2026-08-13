<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDisputeStatusRequest;
use App\Http\Requests\Dispute\IndexDisputeRequest;
use App\Http\Requests\Dispute\StoreDisputeMessageRequest;
use App\Http\Resources\DisputeMessageResource;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Models\DisputeMessageAttachment;
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

        $admin =
            $request->user();

        $disputes =
            Dispute::query()
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
                    $admin
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
                                        'user',
                                        function (
                                            Builder $userQuery
                                        ) use ($search): void {
                                            $userQuery
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
                                                );
                                        }
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
                                                )
                                                ->orWhereHas(
                                                    'farmer',
                                                    fn (
                                                        Builder $farmerQuery
                                                    ) =>
                                                        $farmerQuery
                                                            ->where(
                                                                'name',
                                                                'like',
                                                                $search
                                                            )
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
                                $admin
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

    public function show(
        Request $request,
        Dispute $dispute
    ): JsonResponse {
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

    public function update(
        UpdateDisputeStatusRequest $request,
        Dispute $dispute
    ): JsonResponse {
        $targetStatus =
            DisputeStatus::from(
                $request->validated(
                    'status'
                )
            );

        $dispute =
            DB::transaction(
                function () use (
                    $request,
                    $dispute,
                    $targetStatus
                ): Dispute {
                    $lockedDispute =
                        Dispute::query()
                            ->whereKey(
                                $dispute->id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    if (
                        ! $lockedDispute
                            ->canTransitionTo(
                                $targetStatus
                            )
                    ) {
                        throw ValidationException::withMessages([
                            'status' => [
                                'Dispute cannot transition from '
                                .$lockedDispute
                                    ->status
                                    ->value
                                .' to '
                                .$targetStatus
                                    ->value
                                .'.',
                            ],
                        ]);
                    }

                    $updates = [
                        'status' =>
                            $targetStatus,
                    ];

                    if (
                        $request->has(
                            'note'
                        )
                    ) {
                        $updates[
                            'resolution_note'
                        ] =
                            $request
                                ->validated(
                                    'note'
                                );
                    }

                    if (
                        $targetStatus
                        === DisputeStatus::Resolved
                    ) {
                        $updates[
                            'resolved_at'
                        ] =
                            $lockedDispute
                                ->resolved_at
                            ?? now();

                        $updates[
                            'resolved_by_user_id'
                        ] =
                            $request
                                ->user()
                                ->id;
                    }

                    if (
                        $targetStatus
                        === DisputeStatus::Closed
                    ) {
                        $updates[
                            'closed_at'
                        ] =
                            $lockedDispute
                                ->closed_at
                            ?? now();

                        $updates[
                            'closed_by_user_id'
                        ] =
                            $request
                                ->user()
                                ->id;
                    }

                    $lockedDispute
                        ->forceFill(
                            $updates
                        )
                        ->save();

                    return $lockedDispute;
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
        ]);
    }

    public function markRead(
        Request $request,
        Dispute $dispute
    ): JsonResponse {
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
        $attachment->loadMissing(
            'message'
        );

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