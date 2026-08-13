<?php

namespace App\Models;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable([
    'name',
    'email',
    'state',
    'lga',
    'phone_number',
    'address',
    'gender',
    'date_of_birth',
    'farm_name',
    'farm_size_hectares',
    'farming_method',
    'years_experience',
    'farm_address',
    'status',
    'verification_status',
    'verified_at',
    'suspended_at',
])]
class Farmer extends Model
{
    protected $attributes = [
        'verification_status' =>
            'pending',
    ];

    protected static function booted(): void
    {
        static::created(
            function (
                Farmer $farmer
            ): void {
                /*
                 * Generate the public farmer code from the
                 * database ID so concurrent farmer creation
                 * cannot generate the same code.
                 */
                if (! $farmer->farmer_code) {
                    $farmer->forceFill([
                        'farmer_code' =>
                            'FAR-'
                            .str_pad(
                                (string) $farmer->id,
                                6,
                                '0',
                                STR_PAD_LEFT
                            ),
                    ])->saveQuietly();
                }
            }
        );
    }

    protected function casts(): array
    {
        return [
            'status' =>
                FarmerStatus::class,

            'verification_status' =>
                FarmerVerificationStatus::class,

            'date_of_birth' =>
                'date',

            'farm_size_hectares' =>
                'decimal:2',

            'years_experience' =>
                'integer',

            'verified_at' =>
                'datetime',

            'suspended_at' =>
                'datetime',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(
            Listing::class
        );
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(
            OrderItem::class
        );
    }

    /*
     * Legacy relationship.
     *
     * New multi-item order reporting should use
     * orderItems rather than relying on orders.listing_id.
     */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(
            Order::class,
            Listing::class
        );
    }

    /*
     * A farmer must satisfy both operational state and
     * identity verification before a marketplace listing
     * can become live.
     */
    public function canPublishListings(): bool
    {
        return $this->status
                === FarmerStatus::Active
            && $this->verification_status
                === FarmerVerificationStatus::Verified;
    }
}