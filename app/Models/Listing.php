<?php

namespace App\Models;

use App\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'farmer_id',
    'produce_id',
    'price',
    'unit',
    'stock',
    'status',
])]
class Listing extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => ListingStatus::class,
        ];
    }

    /*
     * Legacy single-listing order relationship.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(Farmer::class);
    }

    public function produce(): BelongsTo
    {
        return $this->belongsTo(Produce::class);
    }
}