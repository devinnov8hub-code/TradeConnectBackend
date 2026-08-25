<?php

namespace App\Observers;

use App\Models\Dispute;
use App\Notifications\AdminNotification;
use App\Services\AdminNotificationDispatcher;

class DisputeObserver
{
    public function __construct(
        private readonly AdminNotificationDispatcher $dispatcher
    ) {
    }

    public function created(
        Dispute $dispute
    ): void {
        $this->dispatcher->send(
            new AdminNotification(
                type: 'dispute',
                title: 'New dispute',
                message:
                    'A buyer opened a new dispute.',
                actionUrl:
                    "/api/v1/admin/disputes/{$dispute->id}",
                entityType: 'dispute',
                entityId: $dispute->id
            )
        );
    }
}