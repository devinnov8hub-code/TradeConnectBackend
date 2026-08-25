<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_paginated_notifications_with_unread_filter(): void
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $otherAdmin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $admin->notify(
            new AdminNotification(
                type: 'order',
                title: 'New order',
                message:
                    'Order ORD-NOT-001 requires attention.',
                actionUrl:
                    '/api/v1/admin/orders/1',
                entityType:
                    'order',
                entityId:
                    1
            )
        );

        $admin->notify(
            new AdminNotification(
                type: 'dispute',
                title: 'New dispute',
                message:
                    'A buyer opened a new dispute.',
                actionUrl:
                    '/api/v1/admin/disputes/1',
                entityType:
                    'dispute',
                entityId:
                    1
            )
        );

        $admin->notify(
            new AdminNotification(
                type: 'listing',
                title: 'Listing pending',
                message:
                    'A listing is awaiting review.',
                actionUrl:
                    '/api/v1/admin/listings/1',
                entityType:
                    'listing',
                entityId:
                    1
            )
        );

        /*
         * Make one notification read.
         */
        $admin
            ->notifications()
            ->where(
                'type',
                'order'
            )
            ->firstOrFail()
            ->markAsRead();

        /*
         * This notification belongs to somebody else and
         * must never appear in the current admin's response.
         */
        $otherAdmin->notify(
            new AdminNotification(
                type: 'system',
                title: 'Other admin only',
                message:
                    'This should remain private.'
            )
        );

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/notifications'
                .'?status=unread'
                .'&per_page=1'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                1
            )
            ->assertJsonPath(
                'meta.total',
                2
            )
            ->assertJsonPath(
                'meta.unread_count',
                2
            )
            ->assertJsonPath(
                'meta.status',
                'unread'
            )
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'type',
                        'title',
                        'message',
                        'action_url',
                        'entity',
                        'is_read',
                        'read_at',
                        'created_at',
                    ],
                ],

                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],

                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'per_page',
                    'to',
                    'total',
                    'unread_count',
                    'status',
                    'type',
                ],
            ]);
    }

    public function test_admin_can_filter_notifications_by_type(): void
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $admin->notify(
            new AdminNotification(
                type: 'order',
                title: 'Order notification',
                message:
                    'A new order was placed.'
            )
        );

        $admin->notify(
            new AdminNotification(
                type: 'dispute',
                title: 'Dispute notification',
                message:
                    'A new dispute was opened.'
            )
        );

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/notifications'
                .'?type=dispute'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.type',
                'dispute'
            )
            ->assertJsonPath(
                'data.0.title',
                'Dispute notification'
            )
            ->assertJsonPath(
                'data.0.is_read',
                false
            )
            ->assertJsonPath(
                'meta.type',
                'dispute'
            );
    }

    public function test_admin_can_mark_own_notification_as_read(): void
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $admin->notify(
            new AdminNotification(
                type: 'order',
                title: 'New order',
                message:
                    'A new order requires attention.'
            )
        );

        $notification =
            $admin
                ->notifications()
                ->firstOrFail();

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/admin/notifications/'
                .$notification->id
                .'/read'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                (string)
                    $notification
                        ->id
            )
            ->assertJsonPath(
                'data.is_read',
                true
            );

        $this->assertNotNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_admin_cannot_mark_another_admin_notification_as_read(): void
    {
        $firstAdmin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $secondAdmin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $secondAdmin->notify(
            new AdminNotification(
                type: 'system',
                title: 'Private notification',
                message:
                    'Only the second admin should see this.'
            )
        );

        $notification =
            $secondAdmin
                ->notifications()
                ->firstOrFail();

        $token =
            auth('api')->login(
                $firstAdmin
            );

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/admin/notifications/'
                .$notification->id
                .'/read'
            )
            ->assertNotFound();

        $this->assertNull(
            $notification
                ->fresh()
                ->read_at
        );
    }

    public function test_admin_can_mark_all_notifications_as_read(): void
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $admin->notify(
            new AdminNotification(
                type: 'order',
                title: 'First notification',
                message:
                    'First unread notification.'
            )
        );

        $admin->notify(
            new AdminNotification(
                type: 'dispute',
                title: 'Second notification',
                message:
                    'Second unread notification.'
            )
        );

        $this->assertSame(
            2,
            $admin
                ->unreadNotifications()
                ->count()
        );

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/admin/notifications/read-all'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.marked_read_count',
                2
            )
            ->assertJsonPath(
                'data.unread_count',
                0
            );

        $this->assertSame(
            0,
            $admin
                ->unreadNotifications()
                ->count()
        );
    }

    public function test_notification_validation_rejects_invalid_filters(): void
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        $token =
            auth('api')->login(
                $admin
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/notifications'
                .'?status=unknown'
                .'&type=unknown'
                .'&per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'type',
                'per_page',
            ]);
    }

    public function test_non_admin_cannot_access_admin_notifications(): void
    {
        $buyer =
            User::factory()->create([
                'role' =>
                    UserRole::User,
            ]);

        $token =
            auth('api')->login(
                $buyer
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/notifications'
            )
            ->assertForbidden();
    }
}