<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'order_status_events',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('order_id')
                    ->constrained()
                    ->cascadeOnDelete();

                /*
                 * Null means this is the first known
                 * status for the order.
                 */
                $table->string('from_status')
                    ->nullable();

                $table->string('to_status');

                /*
                 * Keep the event even if the actor is
                 * eventually removed.
                 */
                $table->foreignId(
                    'changed_by_user_id'
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->text('note')
                    ->nullable();

                /*
                 * Nullable only because some historical
                 * orders do not contain enough evidence
                 * to reconstruct the exact transition time.
                 *
                 * All new events created by the application
                 * will have occurred_at populated.
                 */
                $table->timestamp('occurred_at')
                    ->nullable();

                $table->index([
                    'order_id',
                    'occurred_at',
                ]);

                $table->index('to_status');
            }
        );

        /*
         * Historical orders get ONE baseline event.
         *
         * We deliberately do not fabricate an entire
         * timeline for old orders.
         */
        DB::table('orders')
            ->select([
                'id',
                'status',
                'placed_at',
                'out_for_delivery_at',
                'delivered_at',
                'cancelled_at',
                'created_at',
            ])
            ->orderBy('id')
            ->chunkById(
                100,
                function ($orders): void {
                    foreach ($orders as $order) {
                        $occurredAt = match (
                            $order->status
                        ) {
                            'new' =>
                                $order->placed_at
                                ?? $order->created_at,

                            'in_transit' =>
                                $order->out_for_delivery_at,

                            'delivered' =>
                                $order->delivered_at,

                            'cancelled' =>
                                $order->cancelled_at,

                            default =>
                                null,
                        };

                        DB::table(
                            'order_status_events'
                        )->insert([
                            'order_id' =>
                                $order->id,

                            'from_status' =>
                                null,

                            'to_status' =>
                                $order->status,

                            'changed_by_user_id' =>
                                null,

                            'note' =>
                                null,

                            'occurred_at' =>
                                $occurredAt,
                        ]);
                    }
                }
            );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'order_status_events'
        );
    }
};