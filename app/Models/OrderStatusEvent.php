<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'from_status',
    'to_status',
    'changed_by_user_id',
    'note',
    'occurred_at',
])]
class OrderStatusEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_status' =>
                OrderStatus::class,

            'to_status' =>
                OrderStatus::class,

            'occurred_at' =>
                'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class
        );
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'changed_by_user_id'
        );
    }
}