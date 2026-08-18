<?php

namespace Tests\Feature\Admin;

use App\Enums\FarmerStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return auth('api')->login($admin);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);
        $token = auth('api')->login($user);

        $response = $this->withToken($token)->getJson('/api/v1/admin/categories');

        $response->assertForbidden();
    }

    public function test_admin_can_manage_categories(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->postJson('/api/v1/admin/categories', [
            'name' => 'Vegetables',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Vegetables');

        $this->assertDatabaseHas('categories', ['name' => 'Vegetables']);
    }

    public function test_admin_can_add_produce_to_category(): void
    {
        Storage::fake('public');

        $token = $this->adminToken();
        $category = Category::create(['name' => 'Grains']);

        $response = $this->withToken($token)->post('/api/v1/admin/produce', [
            'category_id' => $category->id,
            'name' => 'Rice',
            'image' => UploadedFile::fake()->image('rice.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Rice')
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.image', null)
            ->assertJsonPath('data.image_mime', 'image/jpeg')
            ->assertJsonStructure(['data' => ['image', 'image_path', 'image_mime', 'image_url']]);

        $path = $response->json('data.image_path');

        $this->assertIsString($path);
        $this->assertStringStartsWith('produce-images/', $path);
        $this->assertSame(Storage::disk('public')->url($path), $response->json('data.image_url'));
        Storage::disk('public')->assertExists($path);

        $this->assertDatabaseHas('produce', [
            'id' => $response->json('data.id'),
            'image' => null,
            'image_path' => $path,
            'image_mime' => 'image/jpeg',
        ]);
    }

    public function test_produce_image_is_required(): void
    {
        $token = $this->adminToken();
        $category = Category::create(['name' => 'Grains']);

        $response = $this->withToken($token)->postJson('/api/v1/admin/produce', [
            'category_id' => $category->id,
            'name' => 'Rice',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.image.0', 'Produce image is required.');
    }

    public function test_admin_can_add_farmer(): void
    {
        $token = $this->adminToken();

        $response = $this->withToken($token)->postJson('/api/v1/admin/farmers', [
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active->value,
            'phone_number' => '08012345678',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Ibrahim Musa')
            ->assertJsonPath('data.state', 'Niger')
            ->assertJsonPath('data.lga', 'Bida')
            ->assertJsonPath('data.status', FarmerStatus::Active->value);
    }

    public function test_deleting_category_removes_its_produce(): void
    {
        $token = $this->adminToken();
        $category = Category::create(['name' => 'Legumes']);
        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => 'Beans',
            'image' => base64_encode('beans'),
            'image_mime' => 'image/jpeg',
        ]);

        $this->withToken($token)
            ->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertOk();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('produce', ['id' => $produce->id]);
    }
}
