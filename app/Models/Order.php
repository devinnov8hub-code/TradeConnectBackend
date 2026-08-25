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

    // Legacy single-item fields.
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
    'payment_access_code',
    'payment_authorization_url',

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
    protected static function booted(): void
    {
        /*
         * Every newly-created order begins its
         * append-only status timeline here.
         */
        static::created(
            function (Order $order): void {
                $order->recordStatusEvent(
                    null,
                    $order->status
                );
            }
        );

        /*
         * Capture status transitions regardless of
         * whether they originate from the admin
         * workflow or buyer cancellation workflow.
         */
        static::updated(
            function (Order $order): void {
                if (
                    ! $order->wasChanged(
                        'status'
                    )
                ) {
                    return;
                }

                $previous =
                    $order->getPrevious();

                $previousStatus =
                    $previous['status']
                    ?? null;

                if (
                    $previousStatus
                    instanceof OrderStatus
                ) {
                    $fromStatus =
                        $previousStatus;
                } elseif (
                    $previousStatus !== null
                ) {
                    $fromStatus =
                        OrderStatus::tryFrom(
                            (string)
                                $previousStatus
                        );
                } else {
                    $fromStatus = null;
                }

                $order->recordStatusEvent(
                    $fromStatus,
                    $order->status
                );
            }
        );
    }

    protected function casts(): array
    {
        return [
            'subtotal' =>
                'decimal:2',

            'delivery_fee' =>
                'decimal:2',

            'total' =>
                'decimal:2',

            'status' =>
                OrderStatus::class,

            'payment_status' =>
                PaymentStatus::class,

            'placed_at' =>
                'datetime',

            'confirmed_at' =>
                'datetime',

            'processing_at' =>
                'datetime',

            'out_for_delivery_at' =>
                'datetime',

            'deliver_by' =>
                'datetime',

            'delivered_at' =>
                'datetime',

            'cancelled_at' =>
                'datetime',

            'paid_at' =>
                'datetime',

            'payment_failed_at' =>
                'datetime',

            'refunded_at' =>
                'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(
            Listing::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            OrderItem::class
        );
    }

    public function statusEvents(): HasMany
    {
        return $this
            ->hasMany(
                OrderStatusEvent::class
            )
            ->orderBy(
                'occurred_at'
            )
            ->orderBy('id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(
            Dispute::class
        );
    }

    public function isCancellable(): bool
    {
        /*
         * Until refund support exists, a paid
         * order must not be cancelled directly.
         */
        return $this->status
                === OrderStatus::New
            && $this->payment_status
                !== PaymentStatus::Paid;
    }

    private function recordStatusEvent(
        ?OrderStatus $fromStatus,
        OrderStatus $toStatus
    ): void {
        $occurredAt = match (
            $toStatus
        ) {
            OrderStatus::New =>
                $this->placed_at
                ?? $this->created_at
                ?? now(),

            OrderStatus::InTransit =>
                $this->out_for_delivery_at
                ?? now(),

            OrderStatus::Delivered =>
                $this->delivered_at
                ?? now(),

            OrderStatus::Cancelled =>
                $this->cancelled_at
                ?? now(),
        };

        /*
         * The currently-authenticated API user is
         * the actor for HTTP-driven changes.
         *
         * Console jobs or other system processes
         * naturally produce a null actor.
         */
        $actorId = auth('api')->id();

        $this
            ->statusEvents()
            ->create([
                'from_status' =>
                    $fromStatus,

                'to_status' =>
                    $toStatus,

                'changed_by_user_id' =>
                    $actorId,

                'note' =>
                    null,

                'occurred_at' =>
                    $occurredAt,
            ]);
    }
}