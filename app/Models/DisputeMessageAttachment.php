<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'dispute_message_id',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
])]
class DisputeMessageAttachment extends Model
{
    protected static function booted(): void
    {
        static::deleted(
            function (
                DisputeMessageAttachment $attachment
            ): void {
                if (
                    $attachment->path
                    && Storage::disk(
                        'local'
                    )->exists(
                        $attachment->path
                    )
                ) {
                    Storage::disk(
                        'local'
                    )->delete(
                        $attachment->path
                    );
                }
            }
        );
    }

    protected function casts(): array
    {
        return [
            'size_bytes' =>
                'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(
            DisputeMessage::class,
            'dispute_message_id'
        );
    }
}