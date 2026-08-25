<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexNotificationRequest;
use App\Http\Resources\AdminNotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(
        IndexNotificationRequest $request
    ): JsonResponse {
        $admin =
            $request->user(
                'api'
            );

        $status =
            $request->validated(
                'status',
                'all'
            )
            ?? 'all';

        $type =
            $request->validated(
                'type'
            );

        $perPage =
            (int) (
                $request->validated(
                    'per_page',
                    20
                )
                ?? 20
            );

        /*
         * Always query through the authenticated admin's
         * notification relationship.
         *
         * This prevents one admin from seeing another
         * admin's private notification records.
         */
        $query =
            $admin
                ->notifications()
                ->when(
                    $status === 'unread',
                    fn ($query) =>
                        $query->whereNull(
                            'read_at'
                        )
                )
                ->when(
                    $status === 'read',
                    fn ($query) =>
                        $query->whereNotNull(
                            'read_at'
                        )
                )
                ->when(
                    $type !== null,
                    fn ($query) =>
                        $query->where(
                            'type',
                            $type
                        )
                )
                ->orderByDesc(
                    'created_at'
                )
                ->orderByDesc(
                    'id'
                );

        $notifications =
            $query
                ->paginate(
                    $perPage
                )
                ->withQueryString();

        $data =
            $notifications
                ->getCollection()
                ->map(
                    fn (
                        DatabaseNotification $notification
                    ): array =>
                        (
                            new AdminNotificationResource(
                                $notification
                            )
                        )->resolve(
                            $request
                        )
                )
                ->values()
                ->all();

        return response()->json([
            'data' =>
                $data,

            'links' => [
                'first' =>
                    $notifications
                        ->url(1),

                'last' =>
                    $notifications
                        ->url(
                            $notifications
                                ->lastPage()
                        ),

                'prev' =>
                    $notifications
                        ->previousPageUrl(),

                'next' =>
                    $notifications
                        ->nextPageUrl(),
            ],

            'meta' => [
                'current_page' =>
                    $notifications
                        ->currentPage(),

                'from' =>
                    $notifications
                        ->firstItem(),

                'last_page' =>
                    $notifications
                        ->lastPage(),

                'per_page' =>
                    $notifications
                        ->perPage(),

                'to' =>
                    $notifications
                        ->lastItem(),

                'total' =>
                    $notifications
                        ->total(),

                /*
                 * This count is intentionally global for the
                 * current admin rather than restricted to the
                 * active list filter.
                 *
                 * It is suitable for the notification badge.
                 */
                'unread_count' =>
                    $admin
                        ->unreadNotifications()
                        ->count(),

                'status' =>
                    $status,

                'type' =>
                    $type,
            ],
        ]);
    }

    public function markRead(
        Request $request,
        string $notification
    ): JsonResponse {
        $admin =
            $request->user(
                'api'
            );

        /*
         * Scope lookup through the current admin.
         *
         * A valid notification UUID belonging to another
         * administrator therefore behaves as not found.
         */
        $record =
            $admin
                ->notifications()
                ->where(
                    'id',
                    $notification
                )
                ->firstOrFail();

        if (
            $record->read_at
            === null
        ) {
            $record
                ->markAsRead();
        }

        $record
            ->refresh();

        return response()->json([
            'data' =>
                (
                    new AdminNotificationResource(
                        $record
                    )
                )->resolve(
                    $request
                ),
        ]);
    }

    public function markAllRead(
        Request $request
    ): JsonResponse {
        $admin =
            $request->user(
                'api'
            );

        $markedReadCount =
            $admin
                ->unreadNotifications()
                ->count();

        /*
         * Mass update instead of loading every notification
         * into application memory.
         */
        $admin
            ->unreadNotifications()
            ->update([
                'read_at' =>
                    now(),
            ]);

        return response()->json([
            'data' => [
                'marked_read_count' =>
                    $markedReadCount,

                'unread_count' =>
                    0,
            ],
        ]);
    }
}