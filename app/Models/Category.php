<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name'])]
class Category extends Model
{
    public function produce(): HasMany
    {
        return $this->hasMany(Produce::class);
    }

    public function listings(): HasManyThrough
{
    return $this->hasManyThrough(
        Listing::class,
        Produce::class,
        'category_id',
        'produce_id'
    );
}
}
