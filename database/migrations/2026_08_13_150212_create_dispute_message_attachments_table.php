<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'dispute_message_attachments',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'dispute_message_id'
                )
                    ->constrained(
                        'dispute_messages'
                    )
                    ->cascadeOnDelete();

                /*
                 * File bytes live on the private local
                 * filesystem/object storage.
                 *
                 * Only the storage path is persisted.
                 */
                $table->string(
                    'path'
                );

                $table->string(
                    'original_name'
                );

                $table->string(
                    'mime_type',
                    100
                );

                $table->unsignedBigInteger(
                    'size_bytes'
                );

                $table->timestamps();

                $table->index([
                    'dispute_message_id',
                    'created_at',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'dispute_message_attachments'
        );
    }
};