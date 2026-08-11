<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return auth('api')->login($admin);
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->create(['role' => UserRole::User, 'email' => 'buyer@example.com']);

        $response = $this->withToken($this->adminToken())->getJson('/api/v1/admin/users');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role', 'created_at', 'updated_at']]])
            ->assertJsonMissing(['password']);
    }

    public function test_non_admin_cannot_list_users(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $token = auth('api')->login($user);

        $this->withToken($token)->getJson('/api/v1/admin/users')->assertForbidden();
    }
}
