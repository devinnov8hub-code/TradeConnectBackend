<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'order_id',
    'order_item_id',
    'user_id',
    'subject',
    'status',
])]
class Dispute extends Model
{
   protected $attributes = [
    'status' =>
        'open',
];

    protected static function booted(): void
    {
        static::creating(
            function (
                Dispute $dispute
            ): void {
                if (
                    $dispute->status
                    === DisputeStatus::UnderReview
                    && $dispute
                        ->under_review_at
                        === null
                ) {
                    $dispute->under_review_at =
                        now();
                }
            }
        );
    }

    protected function casts(): array
    {
        return [
            'status' =>
                DisputeStatus::class,

            'under_review_at' =>
                'datetime',

            'resolved_at' =>
                'datetime',

            'closed_at' =>
                'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class
        );
    }

    public function affectedOrderItem(): BelongsTo
    {
        return $this->belongsTo(
            OrderItem::class,
            'order_item_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by_user_id'
        );
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by_user_id'
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(
            DisputeMessage::class
        );
    }

    public function lastMessage(): HasOne
    {
        return $this
            ->hasOne(
                DisputeMessage::class
            )
            ->latestOfMany();
    }

    public function reads(): HasMany
    {
        return $this->hasMany(
            DisputeRead::class
        );
    }

    /*
     * Compatibility helper.
     *
     * The old "open" concept now maps to the
     * Figma-facing under-review state.
     */
    public function isOpen(): bool
    {
        return $this->status
            === DisputeStatus::UnderReview;
    }

    public function canReceiveMessages(): bool
    {
        return $this->status
            === DisputeStatus::UnderReview;
    }

    public function canTransitionTo(
        DisputeStatus $target
    ): bool {
        return match (
            $this->status
        ) {
            DisputeStatus::UnderReview =>
                in_array(
                    $target,
                    [
                        DisputeStatus::Resolved,
                        DisputeStatus::Closed,
                    ],
                    true
                ),

            DisputeStatus::Resolved =>
                $target
                === DisputeStatus::Closed,

            DisputeStatus::Closed =>
                false,
        };
    }

    public function markReadBy(
        User $user,
        ?DisputeMessage $throughMessage = null
    ): DisputeRead {
        $throughMessage ??=
            $this
                ->messages()
                ->orderByDesc('id')
                ->first();

        return $this
            ->reads()
            ->updateOrCreate(
                [
                    'user_id' =>
                        $user->id,
                ],
                [
                    'last_read_message_id' =>
                        $throughMessage?->id,

                    'read_at' =>
                        now(),
                ]
            );
    }

    public function unreadCountFor(
        User|int $user
    ): int {
        $userId =
            $this->resolveUserId(
                $user
            );

        /*
         * messages() returns a HasMany relationship.
         *
         * constrainUnreadMessages() works on an
         * Eloquent Builder, so explicitly obtain the
         * underlying builder before applying the shared
         * unread constraints.
         */
        $query =
            $this
                ->messages()
                ->getQuery();

        self::constrainUnreadMessages(
            $query,
            $userId
        );

        return $query->count();
    }

    public function scopeWithUnreadCountFor(
        Builder $query,
        User|int $user
    ): Builder {
        $userId =
            $this->resolveUserId(
                $user
            );

        return $query->withCount([
            'messages as unread_count' =>
                function (
                    Builder $messageQuery
                ) use ($userId): void {
                    self::constrainUnreadMessages(
                        $messageQuery,
                        $userId
                    );
                },
        ]);
    }

    public function scopeWhereUnreadFor(
        Builder $query,
        User|int $user
    ): Builder {
        $userId =
            $this->resolveUserId(
                $user
            );

        return $query->whereHas(
            'messages',
            function (
                Builder $messageQuery
            ) use ($userId): void {
                self::constrainUnreadMessages(
                    $messageQuery,
                    $userId
                );
            }
        );
    }

    private function resolveUserId(
        User|int $user
    ): int {
        return $user instanceof User
            ? $user->id
            : $user;
    }

    private static function constrainUnreadMessages(
        Builder $query,
        int $userId
    ): void {
        /*
         * A user's own messages never count as unread.
         *
         * If no dispute_reads row exists, the scalar
         * subquery returns NULL and COALESCE treats the
         * read boundary as zero.
         */
        $query
            ->where(
                'dispute_messages.user_id',
                '!=',
                $userId
            )
            ->whereRaw(
                '
                dispute_messages.id >
                COALESCE(
                    (
                        SELECT dispute_reads.last_read_message_id
                        FROM dispute_reads
                        WHERE dispute_reads.dispute_id =
                            dispute_messages.dispute_id
                        AND dispute_reads.user_id = ?
                        LIMIT 1
                    ),
                    0
                )
                ',
                [
                    $userId,
                ]
            );
    }
}