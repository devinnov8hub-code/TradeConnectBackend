<?php

namespace Tests\Feature\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\UserRole;
use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerStateTest extends TestCase
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

    public function test_admin_can_suspend_farmer(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $response = $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/status",
                [
                    'status' =>
                        FarmerStatus::Inactive->value,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'inactive'
            );

        $this->assertNotNull(
            $response->json(
                'data.suspended_at'
            )
        );

        $farmer->refresh();

        $this->assertSame(
            FarmerStatus::Inactive,
            $farmer->status
        );

        $this->assertNotNull(
            $farmer->suspended_at
        );
    }

    public function test_admin_can_reactivate_farmer(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $farmer->forceFill([
            'status' =>
                FarmerStatus::Inactive,

            'suspended_at' =>
                now(),
        ])->save();

        $response = $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/status",
                [
                    'status' =>
                        FarmerStatus::Active->value,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'active'
            )
            ->assertJsonPath(
                'data.suspended_at',
                null
            );

        $farmer->refresh();

        $this->assertSame(
            FarmerStatus::Active,
            $farmer->status
        );

        $this->assertNull(
            $farmer->suspended_at
        );
    }

    public function test_admin_can_verify_farmer(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $response = $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/verification",
                [
                    'verification_status' =>
                        FarmerVerificationStatus::Verified
                            ->value,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.verification_status',
                'verified'
            );

        $this->assertNotNull(
            $response->json(
                'data.verified_at'
            )
        );

        $farmer->refresh();

        $this->assertSame(
            FarmerVerificationStatus::Verified,
            $farmer->verification_status
        );

        $this->assertNotNull(
            $farmer->verified_at
        );
    }

    public function test_admin_can_reject_farmer_verification(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $farmer->forceFill([
            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'verified_at' =>
                now(),
        ])->save();

        $response = $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/verification",
                [
                    'verification_status' =>
                        FarmerVerificationStatus::Rejected
                            ->value,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.verification_status',
                'rejected'
            )
            ->assertJsonPath(
                'data.verified_at',
                null
            );

        $farmer->refresh();

        $this->assertSame(
            FarmerVerificationStatus::Rejected,
            $farmer->verification_status
        );

        $this->assertNull(
            $farmer->verified_at
        );
    }

    public function test_admin_can_return_farmer_verification_to_pending(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $farmer->forceFill([
            'verification_status' =>
                FarmerVerificationStatus::Verified,

            'verified_at' =>
                now(),
        ])->save();

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/verification",
                [
                    'verification_status' =>
                        FarmerVerificationStatus::Pending
                            ->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.verification_status',
                'pending'
            )
            ->assertJsonPath(
                'data.verified_at',
                null
            );
    }

    public function test_invalid_farmer_status_is_rejected(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/status",
                [
                    'status' =>
                        'suspended',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);
    }

    public function test_invalid_farmer_verification_status_is_rejected(): void
    {
        $token = $this->adminToken();

        $farmer = $this->createFarmer();

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/verification",
                [
                    'verification_status' =>
                        'approved',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'verification_status',
            ]);
    }

    public function test_buyer_cannot_change_farmer_state(): void
    {
        $buyer = User::factory()->create([
            'role' =>
                UserRole::User,
        ]);

        $token = auth('api')->login(
            $buyer
        );

        $farmer = $this->createFarmer();

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/status",
                [
                    'status' =>
                        FarmerStatus::Inactive->value,
                ]
            )
            ->assertForbidden();

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/verification",
                [
                    'verification_status' =>
                        FarmerVerificationStatus::Verified
                            ->value,
                ]
            )
            ->assertForbidden();
    }

    private function createFarmer(): Farmer
    {
        return Farmer::create([
            'name' =>
                'Ibrahim Musa',

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                FarmerStatus::Active,

            'verification_status' =>
                FarmerVerificationStatus::Pending,

            'phone_number' =>
                '08012345678',
        ]);
    }
}