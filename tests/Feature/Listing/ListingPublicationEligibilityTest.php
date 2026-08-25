<?php

namespace Tests\Feature\Listing;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingPublicationEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_farmer_can_create_pending_listing(): void
    {
        $farmer =
            $this->createFarmer(
                FarmerStatus::Active,
                FarmerVerificationStatus::Pending
            );

        $produce =
            $this->createProduce(
                'Rice'
            );

        $this
            ->withToken(
                $this->adminToken()
            )
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                $this->listingPayload(
                    $produce,
                    [
                        'publication_status' =>
                            ListingPublicationStatus::Pending
                                ->value,
                    ]
                )
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.publication_status',
                'pending'
            )
            ->assertJsonPath(
                'data.status',
                'inactive'
            );
    }

    public function test_pending_farmer_cannot_publish_listing(): void
    {
        $farmer =
            $this->createFarmer(
                FarmerStatus::Active,
                FarmerVerificationStatus::Pending
            );

        $produce =
            $this->createProduce(
                'Rice'
            );

        $this
            ->withToken(
                $this->adminToken()
            )
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                $this->listingPayload(
                    $produce,
                    [
                        'publication_status' =>
                            ListingPublicationStatus::Live
                                ->value,
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'publication_status',
            ])
            ->assertJsonFragment([
                'Listing cannot be published because the farmer must be active and verified.',
            ]);

        $this->assertDatabaseCount(
            'listings',
            0
        );
    }

    public function test_legacy_active_status_cannot_bypass_verification(): void
    {
        $farmer =
            $this->createFarmer(
                FarmerStatus::Active,
                FarmerVerificationStatus::Pending
            );

        $produce =
            $this->createProduce(
                'Rice'
            );

        $this
            ->withToken(
                $this->adminToken()
            )
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                $this->listingPayload(
                    $produce,
                    [
                        'status' =>
                            ListingStatus::Active
                                ->value,
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->assertDatabaseCount(
            'listings',
            0
        );
    }

    public function test_inactive_verified_farmer_cannot_publish_listing(): void
    {
        $farmer =
            $this->createFarmer(
                FarmerStatus::Inactive,
                FarmerVerificationStatus::Verified
            );

        $produce =
            $this->createProduce(
                'Rice'
            );

        $this
            ->withToken(
                $this->adminToken()
            )
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                $this->listingPayload(
                    $produce,
                    [
                        'publication_status' =>
                            ListingPublicationStatus::Live
                                ->value,
                    ]
                )
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'publication_status',
            ]);
    }

    public function test_active_verified_farmer_can_publish_listing(): void
    {
        $farmer =
            $this->createFarmer(
                FarmerStatus::Active,
                FarmerVerificationStatus::Verified
            );

        $produce =
            $this->createProduce(
                'Rice'
            );

        $this
            ->withToken(
                $this->adminToken()
            )
            ->postJson(
                "/api/v1/admin/farmers/{$farmer->id}/listings",
                $this->listingPayload(
                    $produce,
                    [
                        'publication_status' =>
                            ListingPublicationStatus::Live
                                ->value,
                    ]
                )
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.publication_status',
                'live'
            )
            ->assertJsonPath(
                'data.status',
                'active'
            );
    }

    public function test_farmer_can_publish_existing_listing_after_verification(): void
    {
        $farmer =
            $this->createFarmer(
                FarmerStatus::Active,
                FarmerVerificationStatus::Pending
            );

        $produce =
            $this->createProduce(
                'Rice'
            );

        $listing =
            Listing::create([
                'farmer_id' =>
                    $farmer->id,

                'produce_id' =>
                    $produce->id,

                'price' =>
                    45000,

                'unit' =>
                    'bag',

                'stock' =>
                    100,

                'publication_status' =>
                    ListingPublicationStatus::Pending,
            ]);

        $token =
            $this->adminToken();

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
            ->assertOk()
            ->assertJsonPath(
                'data.verification_status',
                'verified'
            );

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/listings/{$listing->id}",
                [
                    'publication_status' =>
                        ListingPublicationStatus::Live
                            ->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.publication_status',
                'live'
            )
            ->assertJsonPath(
                'data.status',
                'active'
            );
    }

    public function test_deactivating_farmer_unpublishes_live_listings(): void
    {
        $farmer =
            $this->createFarmer(
                FarmerStatus::Active,
                FarmerVerificationStatus::Verified
            );

        $listing =
            $this->createLiveListing(
                $farmer,
                'Rice'
            );

        $this
            ->withToken(
                $this->adminToken()
            )
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/status",
                [
                    'status' =>
                        FarmerStatus::Inactive
                            ->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'inactive'
            );

        $this->assertDatabaseHas(
            'listings',
            [
                'id' =>
                    $listing->id,

                'publication_status' =>
                    'inactive',

                'status' =>
                    'inactive',
            ]
        );

        $this
            ->getJson(
                "/api/v1/listings/{$listing->id}"
            )
            ->assertNotFound();
    }

    public function test_rejecting_farmer_unpublishes_listings_and_reverification_does_not_republish_them(): void
    {
        $farmer =
            $this->createFarmer(
                FarmerStatus::Active,
                FarmerVerificationStatus::Verified
            );

        $listing =
            $this->createLiveListing(
                $farmer,
                'Rice'
            );

        $token =
            $this->adminToken();

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/farmers/{$farmer->id}/verification",
                [
                    'verification_status' =>
                        FarmerVerificationStatus::Rejected
                            ->value,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.verification_status',
                'rejected'
            );

        $this->assertDatabaseHas(
            'listings',
            [
                'id' =>
                    $listing->id,

                'publication_status' =>
                    'inactive',

                'status' =>
                    'inactive',
            ]
        );

        /*
         * Restoring farmer eligibility must not silently
         * republish previously disabled marketplace data.
         */
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
            ->assertOk()
            ->assertJsonPath(
                'data.verification_status',
                'verified'
            );

        $listing->refresh();

        $this->assertSame(
            ListingPublicationStatus::Inactive,
            $listing->publication_status
        );

        $this->assertSame(
            ListingStatus::Inactive,
            $listing->status
        );
    }

    private function adminToken(): string
    {
        $admin =
            User::factory()->create([
                'role' =>
                    UserRole::Admin,
            ]);

        return auth('api')->login(
            $admin
        );
    }

    private function createFarmer(
        FarmerStatus $status,
        FarmerVerificationStatus $verificationStatus
    ): Farmer {
        return Farmer::create([
            'name' =>
                fake()->name(),

            'state' =>
                'Niger',

            'lga' =>
                'Bida',

            'status' =>
                $status,

            'verification_status' =>
                $verificationStatus,

            'phone_number' =>
                fake()
                    ->unique()
                    ->numerify(
                        '080########'
                    ),

            'verified_at' =>
                $verificationStatus
                === FarmerVerificationStatus::Verified
                    ? now()
                    : null,
        ]);
    }

    private function createProduce(
        string $name
    ): Produce {
        $category =
            Category::firstOrCreate([
                'name' =>
                    'Grains',
            ]);

        return Produce::create([
            'category_id' =>
                $category->id,

            'name' =>
                $name,

            'image' =>
                base64_encode(
                    strtolower($name)
                ),

            'image_mime' =>
                'image/jpeg',
        ]);
    }

    private function createLiveListing(
        Farmer $farmer,
        string $produceName
    ): Listing {
        $produce =
            $this->createProduce(
                $produceName
            );

        return Listing::create([
            'farmer_id' =>
                $farmer->id,

            'produce_id' =>
                $produce->id,

            'price' =>
                45000,

            'unit' =>
                'bag',

            'stock' =>
                100,

            'minimum_order_quantity' =>
                1,

            'available_from' =>
                now()
                    ->subDay()
                    ->toDateString(),

            'publication_status' =>
                ListingPublicationStatus::Live,
        ]);
    }

    private function listingPayload(
        Produce $produce,
        array $overrides = []
    ): array {
        return array_merge(
            [
                'produce_id' =>
                    $produce->id,

                'price' =>
                    45000,

                'unit' =>
                    'bag',

                'stock' =>
                    100,

                'minimum_order_quantity' =>
                    1,
            ],
            $overrides
        );
    }
}