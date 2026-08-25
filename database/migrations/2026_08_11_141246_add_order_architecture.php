<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number')
                ->nullable()
                ->unique()
                ->after('id');

            $table->decimal('subtotal', 12, 2)
                ->nullable()
                ->after('quantity');

            $table->decimal('delivery_fee', 12, 2)
                ->default(0)
                ->after('subtotal');

            $table->string('payment_status')
                ->default('pending')
                ->after('status');

            $table->string('delivery_method')
                ->nullable()
                ->after('payment_status');

            $table->string('delivery_name')
                ->nullable()
                ->after('delivery_method');

            $table->string('delivery_phone')
                ->nullable()
                ->after('delivery_name');

            $table->string('delivery_state')
                ->nullable()
                ->after('delivery_phone');

            $table->string('delivery_lga')
                ->nullable()
                ->after('delivery_state');

            $table->text('delivery_address')
                ->nullable()
                ->after('delivery_lga');

            $table->text('delivery_notes')
                ->nullable()
                ->after('delivery_address');

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('deliver_by')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('listing_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('farmer_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('produce_id')
                ->nullable()
                ->constrained('produce')
                ->nullOnDelete();

            /*
             * Snapshot fields.
             *
             * These preserve what the buyer actually purchased even if the
             * listing, produce, category, farmer, or price changes later.
             */
            $table->string('produce_name');
            $table->string('category_name')->nullable();
            $table->string('unit')->nullable();

            $table->unsignedInteger('quantity');

            $table->decimal('unit_price', 12, 2);

            $table->decimal('discount_amount', 12, 2)
                ->default(0);

            $table->decimal('line_total', 12, 2);

            $table->timestamps();
        });

        /*
         * Backfill orders that already exist.
         *
         * The old system stored one listing directly on each order.
         * We convert each of those legacy orders into one OrderItem while
         * leaving listing_id and quantity on orders in place for now.
         */
        DB::table('orders')
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $listing = DB::table('listings')
                        ->join(
                            'produce',
                            'produce.id',
                            '=',
                            'listings.produce_id'
                        )
                        ->leftJoin(
                            'categories',
                            'categories.id',
                            '=',
                            'produce.category_id'
                        )
                        ->where('listings.id', $order->listing_id)
                        ->select([
                            'listings.farmer_id',
                            'listings.produce_id',
                            'produce.name as produce_name',
                            'categories.name as category_name',
                        ])
                        ->first();

                    if (! $listing) {
                        continue;
                    }

                    $quantity = max((int) $order->quantity, 1);

                    /*
                     * Derive the historical unit price from the order total,
                     * rather than trusting today's listing price.
                     */
                    $unitPrice = number_format(
                        ((float) $order->total) / $quantity,
                        2,
                        '.',
                        ''
                    );

                    DB::table('order_items')->insert([
                        'order_id' => $order->id,
                        'listing_id' => $order->listing_id,
                        'farmer_id' => $listing->farmer_id,
                        'produce_id' => $listing->produce_id,
                        'produce_name' => $listing->produce_name,
                        'category_name' => $listing->category_name,
                        'unit' => null,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_amount' => 0,
                        'line_total' => $order->total,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ]);

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'order_number' => 'ORD-'.str_pad(
                                (string) $order->id,
                                6,
                                '0',
                                STR_PAD_LEFT
                            ),
                            'subtotal' => $order->total,
                            'placed_at' => $order->created_at,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_order_number_unique');

            $table->dropColumn([
                'order_number',
                'subtotal',
                'delivery_fee',
                'payment_status',
                'delivery_method',
                'delivery_name',
                'delivery_phone',
                'delivery_state',
                'delivery_lga',
                'delivery_address',
                'delivery_notes',
                'placed_at',
                'confirmed_at',
                'processing_at',
                'out_for_delivery_at',
                'deliver_by',
                'delivered_at',
                'cancelled_at',
            ]);
        });
    }
};