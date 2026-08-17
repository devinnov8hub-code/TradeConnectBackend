<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Support\Facades\Notification;

class AdminNotificationDispatcher
{
    public function send(
        AdminNotification $notification
    ): void {
        $admins =
            User::query()
                ->where(
                    'role',
                    UserRole::Admin->value
                )
                ->where(
                    'status',
                    UserStatus::Active->value
                )
                ->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send(
            $admins,
            $notification
        );
    }
}