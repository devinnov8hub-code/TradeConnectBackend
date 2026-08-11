<?php

namespace App\Models;

use App\Enums\ListingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['farmer_id', 'produce_id', 'price', 'stock', 'status'])]
class Listing extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => ListingStatus::class,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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
