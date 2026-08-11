<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produce', function (Blueprint $table) {
            $table->longText('image')->nullable()->change();
            $table->string('image_mime')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('produce', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
            $table->dropColumn('image_mime');
        });
    }
};
