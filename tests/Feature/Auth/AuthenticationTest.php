<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_is_assigned_user_role(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email', 'role'],
            ])
            ->assertJsonPath('user.role', UserRole::User->value);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'role' => UserRole::User->value,
        ]);
    }

    public function test_public_registration_cannot_assign_admin_role(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Sneaky Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => UserRole::Admin->value,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', [
            'email' => 'admin@example.com',
        ]);
    }

    public function test_user_can_login_and_receive_jwt(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('user.role', UserRole::Admin->value)
            ->assertJsonStructure(['access_token']);
    }

    public function test_authenticated_user_can_get_their_profile(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::User,
            'email' => 'jane@example.com',
        ]);

        $token = auth('api')->login($user);

        $response = $this->withToken($token)->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', 'jane@example.com')
            ->assertJsonPath('data.role', UserRole::User->value);
    }

    public function test_authenticated_admin_can_get_their_profile(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $token = auth('api')->login($admin);

        $response = $this->withToken($token)->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.role', UserRole::Admin->value);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized()
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);
    }
}