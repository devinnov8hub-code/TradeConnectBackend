<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produce', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('image_mime');
        });

        DB::table('produce')
            ->select(['id', 'image', 'image_mime'])
            ->whereNotNull('image')
            ->whereNull('image_path')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $existingValue = (string) $row->image;

                    if ($existingValue === '') {
                        continue;
                    }

                    // If an older row already contains a public-disk path,
                    // keep that file and simply normalize the database shape.
                    if (Storage::disk('public')->exists($existingValue)) {
                        DB::table('produce')
                            ->where('id', $row->id)
                            ->update([
                                'image_path' => $existingValue,
                                'image' => null,
                            ]);

                        continue;
                    }

                    $binary = base64_decode($existingValue, true);

                    if ($binary === false) {
                        // Unknown legacy value: leave it untouched so the model's
                        // base64 compatibility fallback can continue to serve it.
                        continue;
                    }

                    $extension = $this->extensionForMime($row->image_mime);
                    $path = 'produce-images/legacy-'.$row->id.'.'.$extension;

                    if (! Storage::disk('public')->put($path, $binary)) {
                        throw new RuntimeException(
                            'Unable to migrate produce image for produce ID '.$row->id.'.'
                        );
                    }

                    DB::table('produce')
                        ->where('id', $row->id)
                        ->update([
                            'image_path' => $path,
                            'image' => null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        DB::table('produce')
            ->select(['id', 'image_path'])
            ->whereNotNull('image_path')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $path = (string) $row->image_path;

                    if ($path === '' || ! Storage::disk('public')->exists($path)) {
                        continue;
                    }

                    $binary = Storage::disk('public')->get($path);

                    DB::table('produce')
                        ->where('id', $row->id)
                        ->update([
                            'image' => base64_encode($binary),
                        ]);

                    Storage::disk('public')->delete($path);
                }
            });

        Schema::table('produce', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }

    private function extensionForMime(?string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg', 'image/jpg' => 'jpg',
            default => 'jpg',
        };
    }
};
