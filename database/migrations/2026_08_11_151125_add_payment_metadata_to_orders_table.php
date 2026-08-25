<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_provider')
                ->nullable()
                ->after('payment_status');

            $table->string('payment_reference')
                ->nullable()
                ->unique()
                ->after('payment_provider');

            $table->timestamp('paid_at')
                ->nullable()
                ->after('payment_reference');

            $table->timestamp('payment_failed_at')
                ->nullable()
                ->after('paid_at');

            $table->timestamp('refunded_at')
                ->nullable()
                ->after('payment_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(
                'orders_payment_reference_unique'
            );

            $table->dropColumn([
                'payment_provider',
                'payment_reference',
                'paid_at',
                'payment_failed_at',
                'refunded_at',
            ]);
        });
    }
};