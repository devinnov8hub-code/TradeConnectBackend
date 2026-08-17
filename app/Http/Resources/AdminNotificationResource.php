<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $data =
            is_array(
                $this->data
            )
                ? $this->data
                : [];

        $entityType =
            $data[
                'entity_type'
            ]
            ?? null;

        $entityId =
            $data[
                'entity_id'
            ]
            ?? null;

        $entity =
            $entityType !== null
            || $entityId !== null
                ? [
                    'type' =>
                        $entityType,

                    'id' =>
                        $entityId,
                ]
                : null;

        return [
            'id' =>
                (string)
                    $this->id,

            'type' =>
                (string)
                    $this->type,

            'title' =>
                $data[
                    'title'
                ]
                ?? null,

            'message' =>
                $data[
                    'message'
                ]
                ?? null,

            'action_url' =>
                $data[
                    'action_url'
                ]
                ?? null,

            'entity' =>
                $entity,

            'is_read' =>
                $this->read_at
                !== null,

            'read_at' =>
                $this
                    ->read_at
                    ?->toISOString(),

            'created_at' =>
                $this
                    ->created_at
                    ?->toISOString(),
        ];
    }
}