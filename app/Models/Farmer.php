<?php

namespace App\Models;

use App\Enums\FarmerStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'state', 'lga', 'status', 'phone_number'])]
class Farmer extends Model
{
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Listing::class);
    }

    protected function casts(): array
    {
        return [
            'status' => FarmerStatus::class,
        ];
    }
}
