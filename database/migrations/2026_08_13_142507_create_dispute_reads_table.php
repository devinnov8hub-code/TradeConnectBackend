<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'dispute_reads',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'dispute_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId(
                    'user_id'
                )
                    ->constrained()
                    ->cascadeOnDelete();

                $table->foreignId(
                    'last_read_message_id'
                )
                    ->nullable()
                    ->constrained(
                        'dispute_messages'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'read_at'
                )->nullable();

                /*
                 * Read state is independent for every
                 * participant/admin account.
                 */
                $table->unique([
                    'dispute_id',
                    'user_id',
                ]);

                $table->index([
                    'user_id',
                    'read_at',
                ]);
            }
        );

        /*
         * No historical backfill.
         *
         * We do not claim that an old message was read
         * when the old system never recorded that fact.
         */
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'dispute_reads'
        );
    }
};