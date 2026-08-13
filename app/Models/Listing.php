<?php

namespace App\Models;

use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'farmer_id',
    'produce_id',

    'price',
    'original_price',
    'discount_percent',

    'unit',
    'stock',
    'minimum_order_quantity',

    'description',
    'label',
    'grade',
    'available_from',

    /*
     * status remains temporarily for legacy
     * active/inactive compatibility.
     */
    'status',

    'publication_status',
])]
class Listing extends Model
{
    /*
     * Listing images are part of the standard listing
     * representation. Eager loading here prevents an
     * N+1 problem across marketplace/admin pages.
     */
    protected $with = [
        'images',
    ];

    protected static function booted(): void
    {
        static::saving(
            function (
                Listing $listing
            ): void {
                $listing
                    ->synchronisePublicationState();

                $listing
                    ->synchroniseDiscount();
            }
        );

        /*
         * Database cascading removes image rows, but
         * the actual files live outside the database.
         *
         * Delete child models explicitly so their
         * filesystem cleanup event runs.
         */
        static::deleting(
            function (
                Listing $listing
            ): void {
                $listing
                    ->images()
                    ->get()
                    ->each(
                        fn (
                            ListingImage $image
                        ) =>
                            $image->delete()
                    );
            }
        );
    }

    protected function casts(): array
    {
        return [
            'price' =>
                'decimal:2',

            'original_price' =>
                'decimal:2',

            'discount_percent' =>
                'decimal:2',

            'minimum_order_quantity' =>
                'decimal:2',

            'available_from' =>
                'date',

            'status' =>
                ListingStatus::class,

            'publication_status' =>
                ListingPublicationStatus::class,

            'published_at' =>
                'datetime',
        ];
    }

    /*
     * Legacy single-listing order relationship.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(
            Order::class
        );
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(
            OrderItem::class
        );
    }

    public function images(): HasMany
    {
        return $this
            ->hasMany(
                ListingImage::class
            )
            ->orderBy(
                'position'
            )
            ->orderBy('id');
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(
            Farmer::class
        );
    }

    public function produce(): BelongsTo
    {
        return $this->belongsTo(
            Produce::class
        );
    }

    public function isAvailable(): bool
    {
        if (
            $this->publication_status
            !== ListingPublicationStatus::Live
        ) {
            return false;
        }

        if (
            $this->status
            !== ListingStatus::Active
        ) {
            return false;
        }

        if (
            $this->available_from !== null
            && $this
                ->available_from
                ->gt(
                    now()
                        ->startOfDay()
                )
        ) {
            return false;
        }

        return (float) $this->stock
            >= (float) $this
                ->minimum_order_quantity;
    }

    private function synchronisePublicationState(): void
    {
        if (! $this->exists) {
            $attributes =
                $this->getAttributes();

            $publicationProvided =
                array_key_exists(
                    'publication_status',
                    $attributes
                )
                && $attributes[
                    'publication_status'
                ] !== null;

            if ($publicationProvided) {
                $this
                    ->synchroniseLegacyStatusFromPublication();
            } elseif (
                array_key_exists(
                    'status',
                    $attributes
                )
                && $attributes[
                    'status'
                ] !== null
            ) {
                $this
                    ->synchronisePublicationFromLegacyStatus();
            } else {
                $this->publication_status =
                    ListingPublicationStatus::Pending;

                $this->status =
                    ListingStatus::Inactive;
            }
        } elseif (
            $this->isDirty(
                'publication_status'
            )
        ) {
            $this
                ->synchroniseLegacyStatusFromPublication();
        } elseif (
            $this->isDirty(
                'status'
            )
        ) {
            $this
                ->synchronisePublicationFromLegacyStatus();
        }

        if (
            $this->publication_status
                === ListingPublicationStatus::Live
            && $this->published_at
                === null
        ) {
            $this->published_at =
                now();
        }
    }

    private function synchroniseLegacyStatusFromPublication(): void
    {
        $this->status =
            $this->publication_status
                === ListingPublicationStatus::Live
                    ? ListingStatus::Active
                    : ListingStatus::Inactive;
    }

    private function synchronisePublicationFromLegacyStatus(): void
    {
        $this->publication_status =
            $this->status
                === ListingStatus::Active
                    ? ListingPublicationStatus::Live
                    : ListingPublicationStatus::Inactive;
    }

    private function synchroniseDiscount(): void
    {
        if (
            $this->original_price === null
        ) {
            $this->discount_percent =
                null;

            return;
        }

        $originalPrice =
            (float) $this
                ->original_price;

        $currentPrice =
            (float) $this->price;

        if (
            $originalPrice <= 0
        ) {
            $this->discount_percent =
                '0.00';

            return;
        }

        $discount =
            (
                (
                    $originalPrice
                    - $currentPrice
                )
                / $originalPrice
            )
            * 100;

        $this->discount_percent =
            number_format(
                max(
                    0,
                    round(
                        $discount,
                        2
                    )
                ),
                2,
                '.',
                ''
            );
    }
}