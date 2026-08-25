<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_access_code')
                ->nullable()
                ->after('payment_reference');

            $table->text('payment_authorization_url')
                ->nullable()
                ->after('payment_access_code');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_access_code',
                'payment_authorization_url',
            ]);
        });
    }
};