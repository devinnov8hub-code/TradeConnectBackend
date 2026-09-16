<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal(
                'delivery_fee_per_unit',
                12,
                2
            )
                ->nullable()
                ->after('minimum_order_quantity');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal(
                'delivery_fee_per_unit',
                12,
                2
            )
                ->nullable()
                ->after('unit_price');

            $table->decimal(
                'delivery_total',
                12,
                2
            )
                ->nullable()
                ->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_fee_per_unit',
                'delivery_total',
            ]);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(
                'delivery_fee_per_unit'
            );
        });
    }
};