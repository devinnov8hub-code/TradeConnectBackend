<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'listing_images',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('listing_id')
                    ->constrained()
                    ->cascadeOnDelete();

                /*
                 * Store filesystem/object-storage paths,
                 * never image bytes in this table.
                 */
                $table->string('path');

                $table->string(
                    'original_name'
                )->nullable();

                $table->string(
                    'mime_type',
                    100
                )->nullable();

                $table->unsignedBigInteger(
                    'size'
                )->nullable();

                /*
                 * Lowest position appears first and is
                 * treated as the primary listing image.
                 */
                $table->unsignedSmallInteger(
                    'position'
                )->default(0);

                $table->timestamps();

                $table->index([
                    'listing_id',
                    'position',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'listing_images'
        );
    }
};