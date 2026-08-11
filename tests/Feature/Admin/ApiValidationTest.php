<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiValidationTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return auth('api')->login($admin);
    }

    public function test_showing_missing_farmer_returns_not_found(): void
    {
        $response = $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/farmers/99999');

        $response->assertNotFound()
            ->assertJson(['message' => 'Farmer not found.']);
    }

    public function test_updating_missing_farmer_returns_not_found(): void
    {
        $response = $this->withToken($this->adminToken())
            ->putJson('/api/v1/admin/farmers/99999', [
                'name' => 'Test Farmer',
                'state' => 'Niger',
                'lga' => 'Bida',
                'status' => 'active',
                'phone_number' => '08012345678',
            ]);

        $response->assertNotFound()
            ->assertJson(['message' => 'Farmer not found.']);
    }

    public function test_deleting_missing_farmer_returns_not_found(): void
    {
        $response = $this->withToken($this->adminToken())
            ->deleteJson('/api/v1/admin/farmers/99999');

        $response->assertNotFound()
            ->assertJson(['message' => 'Farmer not found.']);
    }

    public function test_showing_missing_category_returns_not_found(): void
    {
        $response = $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/categories/99999');

        $response->assertNotFound()
            ->assertJson(['message' => 'Category not found.']);
    }

    public function test_showing_missing_produce_returns_not_found(): void
    {
        $response = $this->withToken($this->adminToken())
            ->getJson('/api/v1/admin/produce/99999');

        $response->assertNotFound()
            ->assertJson(['message' => 'Produce not found.']);
    }

    public function test_creating_farmer_without_required_fields_returns_validation_errors(): void
    {
        $response = $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/farmers', []);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['name', 'state', 'lga', 'status', 'phone_number'],
            ]);
    }

    public function test_creating_produce_with_invalid_category_returns_validation_error(): void
    {
        $response = $this->withToken($this->adminToken())
            ->postJson('/api/v1/admin/produce', [
                'category_id' => 99999,
                'name' => 'Rice',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.category_id.0', 'Category not found.');
    }

    public function test_admin_route_without_token_returns_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/admin/farmers');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_admin_route_without_accept_header_returns_unauthenticated_json(): void
    {
        $response = $this->get('/api/v1/admin/categories');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_user_token_on_admin_route_returns_forbidden(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $token = auth('api')->login($user);

        $this->withToken($token)
            ->getJson('/api/v1/admin/categories')
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden. Admin access required.']);
    }
}
