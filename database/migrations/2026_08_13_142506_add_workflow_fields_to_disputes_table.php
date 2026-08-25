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
            'disputes',
            function (Blueprint $table): void {
                /*
                 * Optional on purpose.
                 *
                 * Null means an order-wide issue.
                 * A value means the dispute concerns one
                 * specific purchased item.
                 */
                $table->foreignId(
                    'order_item_id'
                )
                    ->nullable()
                    ->after('order_id')
                    ->constrained(
                        'order_items'
                    )
                    ->nullOnDelete();

                /*
                 * Figma-facing state begins at
                 * under_review rather than legacy open.
                 */
                $table->string(
                    'status'
                )
                    ->default(
                        'under_review'
                    )
                    ->change();

                $table->timestamp(
                    'under_review_at'
                )
                    ->nullable()
                    ->after('status');

                $table->timestamp(
                    'resolved_at'
                )
                    ->nullable()
                    ->after(
                        'under_review_at'
                    );

                $table->foreignId(
                    'resolved_by_user_id'
                )
                    ->nullable()
                    ->after(
                        'resolved_at'
                    )
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamp(
                    'closed_at'
                )
                    ->nullable()
                    ->after(
                        'resolved_by_user_id'
                    );

                $table->foreignId(
                    'closed_by_user_id'
                )
                    ->nullable()
                    ->after(
                        'closed_at'
                    )
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->text(
                    'resolution_note'
                )
                    ->nullable()
                    ->after(
                        'closed_by_user_id'
                    );

                $table->index([
                    'status',
                    'created_at',
                ]);
            }
        );

        /*
         * Legacy "open" means the same thing as the new
         * Figma-facing "under_review".
         */
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

        /*
         * Every historical dispute began in the old
         * "open" state when it was created, so its
         * creation time is a valid under-review start.
         *
         * We deliberately DO NOT fabricate resolved_at
         * or closed_at for historical rows because the
         * old schema did not record those transition
         * times.
         */
        DB::table(
            'disputes'
        )
            ->whereNull(
                'under_review_at'
            )
            ->update([
                'under_review_at' =>
                    DB::raw(
                        'created_at'
                    ),
            ]);
    }

    public function down(): void
    {
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
            function (Blueprint $table): void {
                $table->dropIndex([
                    'status',
                    'created_at',
                ]);

                $table->dropConstrainedForeignId(
                    'order_item_id'
                );

                $table->dropConstrainedForeignId(
                    'resolved_by_user_id'
                );

                $table->dropConstrainedForeignId(
                    'closed_by_user_id'
                );

                $table->dropColumn([
                    'under_review_at',
                    'resolved_at',
                    'closed_at',
                    'resolution_note',
                ]);

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
};