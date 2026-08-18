<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('purpose', 32);
            $table->string('code_hash');
            $table->string('reset_token_hash')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'email',
                'purpose',
            ]);

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_otps');
    }
};
