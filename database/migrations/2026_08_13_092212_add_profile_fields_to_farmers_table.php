<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->string('farmer_code')
                ->nullable()
                ->unique()
                ->after('id');

            $table->string('email')
                ->nullable()
                ->unique()
                ->after('name');

            $table->text('address')
                ->nullable()
                ->after('phone_number');

            $table->string('gender', 30)
                ->nullable()
                ->after('address');

            $table->date('date_of_birth')
                ->nullable()
                ->after('gender');

            $table->string('farm_name')
                ->nullable()
                ->after('date_of_birth');

            $table->decimal(
                'farm_size_hectares',
                10,
                2
            )
                ->nullable()
                ->after('farm_name');

            $table->string('farming_method')
                ->nullable()
                ->after('farm_size_hectares');

            $table->unsignedSmallInteger(
                'years_experience'
            )
                ->nullable()
                ->after('farming_method');

            $table->text('farm_address')
                ->nullable()
                ->after('years_experience');

            $table->string(
                'verification_status'
            )
                ->default('pending')
                ->after('status');

            $table->timestamp('verified_at')
                ->nullable()
                ->after('verification_status');

            $table->timestamp('suspended_at')
                ->nullable()
                ->after('verified_at');
        });

        /*
         * Existing farmers already have active marketplace
         * data, so treat them as verified during the backfill.
         *
         * We do not invent a verified_at timestamp because
         * the historical verification date is unknown.
         */
        DB::table('farmers')
            ->select('id')
            ->orderBy('id')
            ->chunkById(
                500,
                function ($farmers): void {
                    foreach ($farmers as $farmer) {
                        DB::table('farmers')
                            ->where(
                                'id',
                                $farmer->id
                            )
                            ->update([
                                'farmer_code' =>
                                    'FAR-'
                                    .str_pad(
                                        (string) $farmer->id,
                                        6,
                                        '0',
                                        STR_PAD_LEFT
                                    ),

                                'verification_status' =>
                                    'verified',
                            ]);
                    }
                }
            );
    }

    public function down(): void
    {
        Schema::table('farmers', function (Blueprint $table) {
            $table->dropUnique([
                'farmer_code',
            ]);

            $table->dropUnique([
                'email',
            ]);

            $table->dropColumn([
                'farmer_code',
                'email',
                'address',
                'gender',
                'date_of_birth',
                'farm_name',
                'farm_size_hectares',
                'farming_method',
                'years_experience',
                'farm_address',
                'verification_status',
                'verified_at',
                'suspended_at',
            ]);
        });
    }
};