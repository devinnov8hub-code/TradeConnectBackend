<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',

    // Legacy single-item fields. Kept temporarily.
    'listing_id',
    'quantity',

    'order_number',
    'subtotal',
    'delivery_fee',
    'total',

    'status',

    'payment_status',
    'payment_provider',
    'payment_reference',

    'delivery_method',
    'delivery_name',
    'delivery_phone',
    'delivery_state',
    'delivery_lga',
    'delivery_address',
    'delivery_notes',

    'placed_at',
    'confirmed_at',
    'processing_at',
    'out_for_delivery_at',
    'deliver_by',
    'delivered_at',
    'cancelled_at',

    'paid_at',
    'payment_failed_at',
    'refunded_at',
])]
class Order extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',

            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,

            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'processing_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'deliver_by' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',

            'paid_at' => 'datetime',
            'payment_failed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
     * Legacy relationship.
     *
     * Existing API code still uses this. Once order creation has
     * been migrated completely to OrderItem, this relationship
     * can eventually be removed.
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function isCancellable(): bool
    {
        return $this->status === OrderStatus::New;
    }
}