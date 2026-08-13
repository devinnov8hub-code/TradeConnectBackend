<?php

namespace Tests\Feature\Listing;

use App\Enums\FarmerStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Produce;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(
            'public'
        );
    }

    public function test_admin_can_upload_multiple_listing_images(): void
    {
        $listing =
            $this->createListing();

        $token =
            $this->adminToken();

        $first =
            UploadedFile::fake()
                ->image(
                    'front.jpg',
                    800,
                    600
                );

        $second =
            UploadedFile::fake()
                ->image(
                    'side.png',
                    800,
                    600
                );

        $response = $this
            ->withToken($token)
            ->post(
                "/api/v1/admin/listings/{$listing->id}/images",
                [
                    'images' => [
                        $first,
                        $second,
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            );

        $response
            ->assertCreated()
            ->assertJsonCount(
                2,
                'data'
            )
            ->assertJsonPath(
                'data.0.position',
                1
            )
            ->assertJsonPath(
                'data.1.position',
                2
            )
            ->assertJsonPath(
                'data.0.original_name',
                'front.jpg'
            )
            ->assertJsonPath(
                'data.1.original_name',
                'side.png'
            );

        $this->assertDatabaseCount(
            'listing_images',
            2
        );

        $images =
            ListingImage::query()
                ->where(
                    'listing_id',
                    $listing->id
                )
                ->orderBy(
                    'position'
                )
                ->get();

        Storage::disk(
            'public'
        )->assertExists(
            $images[0]->path
        );

        Storage::disk(
            'public'
        )->assertExists(
            $images[1]->path
        );

        /*
         * Public listing response uses the first
         * listing-specific image as its primary image.
         */
        $public = $this
            ->getJson(
                "/api/v1/listings/{$listing->id}"
            );

        $public
            ->assertOk()
            ->assertJsonCount(
                2,
                'data.images'
            )
            ->assertJsonPath(
                'data.images.0.id',
                $images[0]->id
            )
            ->assertJsonPath(
                'data.images.1.id',
                $images[1]->id
            )
            ->assertJsonPath(
                'data.primary_image_url',
                $images[0]->url
            );
    }

    public function test_listing_uses_produce_image_as_fallback(): void
    {
        $listing =
            $this->createListing();

        $this
            ->getJson(
                "/api/v1/listings/{$listing->id}"
            )
            ->assertOk()
            ->assertJsonCount(
                0,
                'data.images'
            )
            ->assertJsonPath(
                'data.primary_image_url',
                $listing
                    ->produce
                    ->image_url
            );
    }

    public function test_admin_can_reorder_listing_images(): void
    {
        $listing =
            $this->createListing();

        $token =
            $this->adminToken();

        $first =
            $listing
                ->images()
                ->create([
                    'path' =>
                        'listing-images/'
                        .$listing->id
                        .'/first.jpg',

                    'original_name' =>
                        'first.jpg',

                    'mime_type' =>
                        'image/jpeg',

                    'size' =>
                        100,

                    'position' =>
                        1,
                ]);

        $second =
            $listing
                ->images()
                ->create([
                    'path' =>
                        'listing-images/'
                        .$listing->id
                        .'/second.jpg',

                    'original_name' =>
                        'second.jpg',

                    'mime_type' =>
                        'image/jpeg',

                    'size' =>
                        100,

                    'position' =>
                        2,
                ]);

        Storage::disk(
            'public'
        )->put(
            $first->path,
            'first'
        );

        Storage::disk(
            'public'
        )->put(
            $second->path,
            'second'
        );

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/listings/{$listing->id}/images/reorder",
                [
                    'image_ids' => [
                        $second->id,
                        $first->id,
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.0.id',
                $second->id
            )
            ->assertJsonPath(
                'data.0.position',
                1
            )
            ->assertJsonPath(
                'data.1.id',
                $first->id
            )
            ->assertJsonPath(
                'data.1.position',
                2
            );

        $this
            ->getJson(
                "/api/v1/listings/{$listing->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'data.images.0.id',
                $second->id
            )
            ->assertJsonPath(
                'data.primary_image_url',
                $second
                    ->fresh()
                    ->url
            );
    }

    public function test_reorder_rejects_image_from_another_listing(): void
    {
        $listing =
            $this->createListing(
                'Rice'
            );

        $otherListing =
            $this->createListing(
                'Maize'
            );

        $token =
            $this->adminToken();

        $ownImage =
            $listing
                ->images()
                ->create([
                    'path' =>
                        'own.jpg',

                    'position' =>
                        1,
                ]);

        $foreignImage =
            $otherListing
                ->images()
                ->create([
                    'path' =>
                        'foreign.jpg',

                    'position' =>
                        1,
                ]);

        $this
            ->withToken($token)
            ->patchJson(
                "/api/v1/admin/listings/{$listing->id}/images/reorder",
                [
                    'image_ids' => [
                        $ownImage->id,
                        $foreignImage->id,
                    ],
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'image_ids',
            ]);
    }

    public function test_listing_cannot_have_more_than_six_images(): void
    {
        $listing =
            $this->createListing();

        $token =
            $this->adminToken();

        for (
            $position = 1;
            $position <= 6;
            $position++
        ) {
            $listing
                ->images()
                ->create([
                    'path' =>
                        "existing-{$position}.jpg",

                    'position' =>
                        $position,
                ]);
        }

        $this
            ->withToken($token)
            ->post(
                "/api/v1/admin/listings/{$listing->id}/images",
                [
                    'images' => [
                        UploadedFile::fake()
                            ->image(
                                'seventh.jpg'
                            ),
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'images',
            ]);

        $this->assertDatabaseCount(
            'listing_images',
            6
        );
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $listing =
            $this->createListing();

        $token =
            $this->adminToken();

        $this
            ->withToken($token)
            ->post(
                "/api/v1/admin/listings/{$listing->id}/images",
                [
                    'images' => [
                        UploadedFile::fake()
                            ->create(
                                'document.pdf',
                                100,
                                'application/pdf'
                            ),
                    ],
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'images.0',
            ]);
    }

    public function test_deleting_listing_image_removes_stored_file(): void
    {
        $listing =
            $this->createListing();

        $token =
            $this->adminToken();

        $image =
            $listing
                ->images()
                ->create([
                    'path' =>
                        'listing-images/'
                        .$listing->id
                        .'/delete-me.jpg',

                    'position' =>
                        1,
                ]);

        Storage::disk(
            'public'
        )->put(
            $image->path,
            'image'
        );

        Storage::disk(
            'public'
        )->assertExists(
            $image->path
        );

        $this
            ->withToken($token)
            ->deleteJson(
                "/api/v1/admin/listings/{$listing->id}/images/{$image->id}"
            )
            ->assertOk()
            ->assertJson([
                'message' =>
                    'Listing image deleted.',
            ]);

        $this->assertDatabaseMissing(
            'listing_images',
            [
                'id' =>
                    $image->id,
            ]
        );

        Storage::disk(
            'public'
        )->assertMissing(
            $image->path
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

    private function createListing(
        string $produceName = 'Rice'
    ): Listing {
        $farmer =
            Farmer::firstOrCreate(
                [
                    'phone_number' =>
                        '08012345678',
                ],
                [
                    'name' =>
                        'Ibrahim Musa',

                    'state' =>
                        'Niger',

                    'lga' =>
                        'Bida',

                    'status' =>
                        FarmerStatus::Active,
                ]
            );

        $category =
            Category::firstOrCreate([
                'name' =>
                    'Grains',
            ]);

        $produce =
            Produce::firstOrCreate(
                [
                    'category_id' =>
                        $category->id,

                    'name' =>
                        $produceName,
                ],
                [
                    'image' =>
                        base64_encode(
                            strtolower(
                                $produceName
                            )
                        ),

                    'image_mime' =>
                        'image/jpeg',
                ]
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
}