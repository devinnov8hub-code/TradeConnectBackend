<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'listing_id',
    'path',
    'original_name',
    'mime_type',
    'size',
    'position',
])]
class ListingImage extends Model
{
    protected static function booted(): void
    {
        static::deleted(
            function (
                ListingImage $image
            ): void {
                if (
                    $image->path
                    && Storage::disk(
                        'public'
                    )->exists(
                        $image->path
                    )
                ) {
                    Storage::disk(
                        'public'
                    )->delete(
                        $image->path
                    );
                }
            }
        );
    }

    protected function casts(): array
    {
        return [
            'size' =>
                'integer',

            'position' =>
                'integer',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(
            Listing::class
        );
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk(
            'public'
        )->url(
            $this->path
        );
    }
}