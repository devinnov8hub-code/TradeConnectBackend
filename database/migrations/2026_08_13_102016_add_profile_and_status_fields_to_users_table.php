<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table
                ->string('account_code', 20)
                ->nullable()
                ->unique()
                ->after('id');

            $table
                ->string('phone_number', 30)
                ->nullable()
                ->after('email');

            $table
                ->string('state')
                ->nullable()
                ->after('phone_number');

            $table
                ->string('lga')
                ->nullable()
                ->after('state');

            $table
                ->text('address')
                ->nullable()
                ->after('lga');

            $table
                ->string('avatar_path')
                ->nullable()
                ->after('address');

            $table
                ->string('status', 20)
                ->default('active')
                ->after('role');
        });

        /*
         * Existing accounts become active because we have
         * no historical information indicating otherwise.
         *
         * Generate stable codes based on the existing role.
         */
        DB::table('users')
            ->select([
                'id',
                'role',
            ])
            ->orderBy('id')
            ->chunkById(
                100,
                function ($users): void {
                    foreach ($users as $user) {
                        $prefix =
                            $user->role === 'admin'
                                ? 'ADM'
                                : 'BYR';

                        DB::table('users')
                            ->where(
                                'id',
                                $user->id
                            )
                            ->update([
                                'account_code' =>
                                    $prefix
                                    .'-'
                                    .str_pad(
                                        (string) $user->id,
                                        6,
                                        '0',
                                        STR_PAD_LEFT
                                    ),

                                'status' =>
                                    'active',
                            ]);
                    }
                },
                'id'
            );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'account_code',
                'phone_number',
                'state',
                'lga',
                'address',
                'avatar_path',
                'status',
            ]);
        });
    }
};