<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $type,
        public string $title,
        public string $message,
        public ?string $actionUrl = null,
        public ?string $entityType = null,
        public int|string|null $entityId = null
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(
        object $notifiable
    ): array {
        return [
            'database',
        ];
    }

    /**
     * Store a short domain-friendly type instead of the
     * full PHP notification class name.
     */
    public function databaseType(
        object $notifiable
    ): string {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(
        object $notifiable
    ): array {
        return [
            'title' =>
                $this->title,

            'message' =>
                $this->message,

            'action_url' =>
                $this->actionUrl,

            'entity_type' =>
                $this->entityType,

            'entity_id' =>
                $this->entityId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        object $notifiable
    ): array {
        return $this->toDatabase(
            $notifiable
        );
    }
}