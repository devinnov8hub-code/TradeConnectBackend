<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerIndexTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $admin = User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);

        return auth('api')->login(
            $admin
        );
    }

    public function test_admin_can_get_paginated_buyers(): void
    {
        $token = $this->adminToken();

        $this->createBuyer(
            'Ibrahim Musa',
            'ibrahim@example.com',
            'Niger',
            'Bida'
        );

        $this->createBuyer(
            'Ada Okoro',
            'ada@example.com',
            'Lagos',
            'Ikeja'
        );

        /*
         * Admin accounts must not appear
         * in the buyer list.
         */
        User::factory()->create([
            'role' =>
                UserRole::Admin,

            'email' =>
                'another-admin@example.com',
        ]);

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/buyers'
                .'?per_page=1'
                .'&page=1'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'meta.current_page',
                1
            )
            ->assertJsonPath(
                'meta.per_page',
                1
            )
            ->assertJsonPath(
                'meta.last_page',
                2
            )
            ->assertJsonPath(
                'meta.total',
                2
            )
            ->assertJsonPath(
                'data.0.role',
                'user'
            )
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'account_code',
                        'name',
                        'email',
                        'phone_number',
                        'state',
                        'lga',
                        'address',
                        'avatar_path',
                        'role',
                        'status',
                        'orders_count',
                        'created_at',
                        'updated_at',
                    ],
                ],

                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],

                'meta' => [
                    'current_page',
                    'from',
                    'last_page',
                    'links',
                    'path',
                    'per_page',
                    'to',
                    'total',
                ],
            ]);
    }

    public function test_admin_can_search_buyers(): void
    {
        $token = $this->adminToken();

        $ibrahim = $this->createBuyer(
            'Ibrahim Musa',
            'ibrahim@example.com',
            'Niger',
            'Bida'
        );

        $this->createBuyer(
            'Ada Okoro',
            'ada@example.com',
            'Lagos',
            'Ikeja'
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/buyers'
                .'?search=Ibrahim'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $ibrahim->id
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/buyers'
                .'?search='
                .$ibrahim->account_code
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $ibrahim->id
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/buyers'
                .'?search=ibrahim@example.com'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $ibrahim->id
            );
    }

    public function test_admin_can_filter_buyers_by_location_and_status(): void
    {
        $token = $this->adminToken();

        $expected = $this->createBuyer(
            'Ada Okoro',
            'ada@example.com',
            'Lagos',
            'Ikeja',
            UserStatus::Active
        );

        $this->createBuyer(
            'Inactive Lagos Buyer',
            'inactive@example.com',
            'Lagos',
            'Ikeja',
            UserStatus::Inactive
        );

        $this->createBuyer(
            'Niger Buyer',
            'niger@example.com',
            'Niger',
            'Bida',
            UserStatus::Active
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/buyers'
                .'?state=Lagos'
                .'&lga=Ikeja'
                .'&status=active'
            );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.id',
                $expected->id
            )
            ->assertJsonPath(
                'data.0.status',
                'active'
            );
    }

    public function test_buyer_pagination_preserves_filters(): void
    {
        $token = $this->adminToken();

        $this->createBuyer(
            'Ada Okoro',
            'ada@example.com',
            'Lagos',
            'Ikeja'
        );

        $this->createBuyer(
            'Joy Smith',
            'joy@example.com',
            'Lagos',
            'Ikeja'
        );

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/buyers'
                .'?state=Lagos'
                .'&status=active'
                .'&sort=name'
                .'&order=asc'
                .'&per_page=1'
            );

        $response->assertOk();

        $next = $response->json(
            'links.next'
        );

        $this->assertNotNull(
            $next
        );

        $this->assertStringContainsString(
            'state=Lagos',
            $next
        );

        $this->assertStringContainsString(
            'status=active',
            $next
        );

        $this->assertStringContainsString(
            'sort=name',
            $next
        );

        $this->assertStringContainsString(
            'order=asc',
            $next
        );

        $this->assertStringContainsString(
            'per_page=1',
            $next
        );
    }

    public function test_invalid_buyer_filters_are_rejected(): void
    {
        $token = $this->adminToken();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/admin/buyers'
                .'?status=suspended'
                .'&sort=unknown'
                .'&per_page=101'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
                'sort',
                'per_page',
            ]);
    }

    public function test_admin_can_deactivate_and_reactivate_buyer(): void
    {
        $token = $this->adminToken();

        $buyer = $this->createBuyer(
            'Ada Okoro',
            'ada@example.com',
            'Lagos',
            'Ikeja'
        );

        /*
         * Deactivate the buyer.
         */
        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/buyers/{$buyer->id}/status",
                [
                    'status' =>
                        UserStatus::Inactive->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $buyer->id
            )
            ->assertJsonPath(
                'data.status',
                'inactive'
            );

        $buyer->refresh();

        $this->assertSame(
            UserStatus::Inactive,
            $buyer->status
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' =>
                    $buyer->id,

                'status' =>
                    UserStatus::Inactive->value,
            ]
        );

        /*
         * Reactivate the same buyer using the
         * same authenticated administrator.
         */
        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/buyers/{$buyer->id}/status",
                [
                    'status' =>
                        UserStatus::Active->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.id',
                $buyer->id
            )
            ->assertJsonPath(
                'data.status',
                'active'
            );

        $buyer->refresh();

        $this->assertSame(
            UserStatus::Active,
            $buyer->status
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' =>
                    $buyer->id,

                'status' =>
                    UserStatus::Active->value,
            ]
        );
    }

    public function test_admin_account_cannot_be_changed_through_buyer_status_endpoint(): void
    {
        $token = $this->adminToken();

        $targetAdmin = User::factory()->create([
            'role' =>
                UserRole::Admin,
        ]);

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/buyers/{$targetAdmin->id}/status",
                [
                    'status' =>
                        UserStatus::Inactive->value,
                ]
            )
            ->assertNotFound()
            ->assertJson([
                'message' =>
                    'Buyer not found.',
            ]);

        $targetAdmin->refresh();

        $this->assertSame(
            UserStatus::Active,
            $targetAdmin->status
        );
    }

    public function test_buyer_cannot_change_another_buyers_status(): void
    {
        $buyer = $this->createBuyer(
            'Buyer One',
            'buyer-one@example.com',
            'Lagos',
            'Ikeja'
        );

        $target = $this->createBuyer(
            'Buyer Two',
            'buyer-two@example.com',
            'Niger',
            'Bida'
        );

        $token = auth('api')->login(
            $buyer
        );

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/buyers/{$target->id}/status",
                [
                    'status' =>
                        UserStatus::Inactive->value,
                ]
            )
            ->assertForbidden();

        $target->refresh();

        $this->assertSame(
            UserStatus::Active,
            $target->status
        );
    }

    private function createBuyer(
        string $name,
        string $email,
        string $state,
        string $lga,
        UserStatus $status = UserStatus::Active
    ): User {
        $buyer = User::factory()->create([
            'name' =>
                $name,

            'email' =>
                $email,

            'role' =>
                UserRole::User,

            'phone_number' =>
                '08012345678',

            'state' =>
                $state,

            'lga' =>
                $lga,

            'address' =>
                '14 Market Road',
        ]);

        if (
            $status
            !== UserStatus::Active
        ) {
            $buyer->forceFill([
                'status' =>
                    $status,
            ])->save();

            $buyer->refresh();
        }

        return $buyer;
    }
}