<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dispute_id',
    'user_id',
    'last_read_message_id',
    'read_at',
])]
class DisputeRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'read_at' =>
                'datetime',
        ];
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

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(
            DisputeMessage::class,
            'last_read_message_id'
        );
    }
}