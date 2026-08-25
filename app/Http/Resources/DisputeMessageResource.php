<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisputeMessageResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'dispute_id' =>
                $this->dispute_id,

            'body' =>
                $this->body,

            'sender' => [
                'id' =>
                    $this
                        ->user
                        ->id,

                'name' =>
                    $this
                        ->user
                        ->name,

                'role' =>
                    $this
                        ->user
                        ->role
                        ->value,
            ],

            /*
             * Attachment bytes are private.
             *
             * The frontend receives a protected download
             * endpoint rather than a public storage URL.
             */
            'attachments' =>
                $this->when(
                    $this->relationLoaded(
                        'attachments'
                    ),
                    fn () =>
                        $this
                            ->attachments
                            ->map(
                                function (
                                    $attachment
                                ) use ($request): array {
                                    $routeName =
                                        $request
                                            ->user()
                                            ?->role
                                        === UserRole::Admin
                                            ? 'admin.disputes.attachments.download'
                                            : 'disputes.attachments.download';

                                    return [
                                        'id' =>
                                            $attachment
                                                ->id,

                                        'original_name' =>
                                            $attachment
                                                ->original_name,

                                        'mime_type' =>
                                            $attachment
                                                ->mime_type,

                                        'size_bytes' =>
                                            $attachment
                                                ->size_bytes,

                                        'download_url' =>
                                            route(
                                                $routeName,
                                                [
                                                    'dispute' =>
                                                        $this
                                                            ->dispute_id,

                                                    'attachment' =>
                                                        $attachment
                                                            ->id,
                                                ],
                                                false
                                            ),
                                    ];
                                }
                            )
                            ->values()
                ),

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}