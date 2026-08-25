<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Restore the original v1 persisted/API value.
         *
         * The enhanced workflow still treats this state as
         * "under review" semantically; only the canonical
         * stored value returns to the backwards-compatible
         * "open" value.
         */
        DB::table(
            'disputes'
        )
            ->where(
                'status',
                'under_review'
            )
            ->update([
                'status' =>
                    'open',
            ]);

        Schema::table(
            'disputes',
            function (
                Blueprint $table
            ): void {
                $table->string(
                    'status'
                )
                    ->default(
                        'open'
                    )
                    ->change();
            }
        );
    }

    public function down(): void
    {
        DB::table(
            'disputes'
        )
            ->where(
                'status',
                'open'
            )
            ->update([
                'status' =>
                    'under_review',
            ]);

        Schema::table(
            'disputes',
            function (
                Blueprint $table
            ): void {
                $table->string(
                    'status'
                )
                    ->default(
                        'under_review'
                    )
                    ->change();
            }
        );
    }
};