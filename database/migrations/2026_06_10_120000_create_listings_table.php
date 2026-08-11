<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produce_id')->constrained('produce')->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('stock');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['farmer_id', 'produce_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
