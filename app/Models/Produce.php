<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['category_id', 'name', 'image', 'image_path', 'image_mime'])]
class Produce extends Model
{
    protected $table = 'produce';

    protected $appends = ['image_url'];

    protected static function booted(): void
    {
        static::deleted(function (Produce $produce): void {
            if (
                $produce->image_path
                && Storage::disk('public')->exists($produce->image_path)
            ) {
                Storage::disk('public')->delete($produce->image_path);
            }
        });
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        // Backward-compatible fallback for legacy rows that have not yet
        // been migrated away from base64 storage.
        if ($this->image && $this->image_mime) {
            return 'data:'.$this->image_mime.';base64,'.$this->image;
        }

        return null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
