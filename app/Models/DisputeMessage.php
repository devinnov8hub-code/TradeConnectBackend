<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'dispute_id',
    'user_id',
    'body',
])]
class DisputeMessage extends Model
{
    protected static function booted(): void
    {
        /*
         * Explicitly delete attachment models so their
         * filesystem cleanup event is executed.
         */
        static::deleting(
            function (
                DisputeMessage $message
            ): void {
                $message
                    ->attachments()
                    ->get()
                    ->each(
                        fn (
                            DisputeMessageAttachment $attachment
                        ) =>
                            $attachment
                                ->delete()
                    );
            }
        );
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(
            Dispute::class
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function attachments(): HasMany
    {
        return $this
            ->hasMany(
                DisputeMessageAttachment::class
            )
            ->orderBy('id');
    }
}