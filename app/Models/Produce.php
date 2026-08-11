<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'name', 'image', 'image_mime'])]
class Produce extends Model
{
    protected $table = 'produce';

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image || ! $this->image_mime) {
            return null;
        }

        return 'data:'.$this->image_mime.';base64,'.$this->image;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
