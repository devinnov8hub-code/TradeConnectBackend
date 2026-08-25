<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'listings',
            function (Blueprint $table): void {
                $table->text('description')
                    ->nullable();

                $table->string(
                    'label',
                    30
                )->nullable();

                $table->string(
                    'grade',
                    100
                )->nullable();

                $table->date(
                    'available_from'
                )->nullable();

                /*
                 * Stored as decimal so the schema is ready
                 * for fractional quantities later.
                 *
                 * The current order API still accepts whole
                 * quantities only, so request validation
                 * remains integer-based for this slice.
                 */
                $table->decimal(
                    'minimum_order_quantity',
                    12,
                    2
                )->default(1);

                /*
                 * price = current selling price.
                 *
                 * original_price is optional and is used
                 * for struck-through pricing.
                 */
                $table->decimal(
                    'original_price',
                    12,
                    2
                )->nullable();

                /*
                 * Stored for easy API consumption, but the
                 * model recalculates it from price and
                 * original_price so it cannot drift.
                 */
                $table->decimal(
                    'discount_percent',
                    5,
                    2
                )->nullable();

                $table->string(
                    'publication_status',
                    20
                )->default('pending');

                $table->timestamp(
                    'published_at'
                )->nullable();

                $table->index(
                    'publication_status'
                );

                $table->index(
                    'label'
                );

                $table->index(
                    'available_from'
                );
            }
        );

        /*
         * Existing active marketplace listings were already
         * publicly visible, so preserve that meaning by
         * backfilling them as live.
         *
         * created_at is the best available publication
         * timestamp for these legacy records.
         */
        DB::table('listings')
            ->where(
                'status',
                'active'
            )
            ->update([
                'publication_status' =>
                    'live',

                'published_at' =>
                    DB::raw('created_at'),
            ]);

        /*
         * Existing inactive listings remain inactive.
         */
        DB::table('listings')
            ->where(
                'status',
                'inactive'
            )
            ->update([
                'publication_status' =>
                    'inactive',
            ]);
    }

    public function down(): void
    {
        Schema::table(
            'listings',
            function (Blueprint $table): void {
                $table->dropIndex([
                    'publication_status',
                ]);

                $table->dropIndex([
                    'label',
                ]);

                $table->dropIndex([
                    'available_from',
                ]);

                $table->dropColumn([
                    'description',
                    'label',
                    'grade',
                    'available_from',
                    'minimum_order_quantity',
                    'original_price',
                    'discount_percent',
                    'publication_status',
                    'published_at',
                ]);
            }
        );
    }
};