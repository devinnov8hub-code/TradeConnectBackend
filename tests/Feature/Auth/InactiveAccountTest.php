<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactiveAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_receive_account_codes_and_active_status(): void
    {
        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $admin = User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);

        $this->assertMatchesRegularExpression(
            '/^BYR-\d{6}$/',
            $buyer->account_code
        );

        $this->assertMatchesRegularExpression(
            '/^ADM-\d{6}$/',
            $admin->account_code
        );

        $this->assertSame(
            UserStatus::Active,
            $buyer->status
        );

        $this->assertSame(
            UserStatus::Active,
            $admin->status
        );

        $this->assertNotSame(
            $buyer->account_code,
            $admin->account_code
        );
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'name' =>
                'Inactive Buyer',

            'email' =>
                'inactive@example.com',

            'password' =>
                'password',

            'role' =>
                UserRole::User,
        ]);

        $user->forceFill([
            'status' =>
                UserStatus::Inactive,
        ])->save();

        $response = $this->postJson(
            '/api/v1/login',
            [
                'email' =>
                    'inactive@example.com',

                'password' =>
                    'password',
            ]
        );

        $response
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'Account is inactive.',
            ])
            ->assertJsonMissingPath(
                'access_token'
            );
    }

    public function test_existing_token_cannot_access_protected_routes_after_account_is_deactivated(): void
    {
        $user = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        /*
         * Token was issued while the account
         * was still active.
         */
        $token = auth('api')->login(
            $user
        );

        $user->forceFill([
            'status' =>
                UserStatus::Inactive,
        ])->save();

        $this
            ->withToken($token)
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'Account is inactive.',
            ]);
    }

    public function test_active_user_can_still_login_and_use_protected_routes(): void
    {
        $user = User::factory()->create([
            'name' =>
                'Active Buyer',

            'email' =>
                'active@example.com',

            'password' =>
                'password',

            'role' =>
                UserRole::User,
        ]);

        $login = $this->postJson(
            '/api/v1/login',
            [
                'email' =>
                    'active@example.com',

                'password' =>
                    'password',
            ]
        );

        $login
            ->assertOk()
            ->assertJsonPath(
                'user.id',
                $user->id
            )
            ->assertJsonPath(
                'user.status',
                'active'
            )
            ->assertJsonPath(
                'user.account_code',
                $user->account_code
            );

        $token = $login->json(
            'access_token'
        );

        $this
            ->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $user->id
            )
            ->assertJsonPath(
                'data.status',
                'active'
            );
    }

    public function test_inactive_admin_cannot_use_admin_routes(): void
    {
        $admin = User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);

        /*
         * Simulate a token issued before
         * the account was deactivated.
         */
        $token = auth('api')->login(
            $admin
        );

        $admin->forceFill([
            'status' =>
                UserStatus::Inactive,
        ])->save();

        $this
            ->withToken($token)
            ->getJson('/api/v1/admin/buyers')
            ->assertForbidden()
            ->assertJson([
                'message' =>
                    'Account is inactive.',
            ]);
    }
}