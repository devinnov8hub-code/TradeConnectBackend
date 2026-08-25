<?php

namespace Tests\Feature\Listing;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_categories_include_visible_listing_counts(): void
    {
        $verifiedFarmer = $this->farmer(
            'Verified Farmer',
            FarmerVerificationStatus::Verified
        );

        $pendingFarmer = $this->farmer(
            'Pending Farmer',
            FarmerVerificationStatus::Pending
        );

        $grains = Category::create([
            'name' => 'Grains & Cereals',
        ]);

        $fruits = Category::create([
            'name' => 'Fruits',
        ]);

        $poultry = Category::create([
            'name' => 'Poultry & Eggs',
        ]);

        // Two marketplace-visible grain listings.
        $this->listing(
            $verifiedFarmer,
            $grains,
            'Rice',
            ListingPublicationStatus::Live
        );

        $this->listing(
            $verifiedFarmer,
            $grains,
            'Maize',
            ListingPublicationStatus::Live
        );

        // One marketplace-visible fruit listing.
        $this->listing(
            $verifiedFarmer,
            $fruits,
            'Orange',
            ListingPublicationStatus::Live
        );

        // Exists in the database but is not publicly visible.
        $this->listing(
            $pendingFarmer,
            $poultry,
            'Eggs',
            ListingPublicationStatus::Live
        );

        // Also exists but is not published/live.
        $this->listing(
            $verifiedFarmer,
            $grains,
            'Millet',
            ListingPublicationStatus::Pending
        );

        $this
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath(
                'data.0.name',
                'Fruits'
            )
            ->assertJsonPath(
                'data.0.listing_count',
                1
            )
            ->assertJsonPath(
                'data.1.name',
                'Grains & Cereals'
            )
            ->assertJsonPath(
                'data.1.listing_count',
                2
            )
            ->assertJsonPath(
                'data.2.name',
                'Poultry & Eggs'
            )
            ->assertJsonPath(
                'data.2.listing_count',
                0
            );
    }

    private function farmer(
        string $name,
        FarmerVerificationStatus $verification
    ): Farmer {
        return Farmer::create([
            'name' => $name,
            'state' => 'Kaduna',
            'lga' => 'Kagarko',
            'phone_number' =>
                fake()->unique()->numerify('080########'),
            'status' => FarmerStatus::Active,
            'verification_status' => $verification,
        ]);
    }

    private function listing(
        Farmer $farmer,
        Category $category,
        string $name,
        ListingPublicationStatus $publicationStatus
    ): Listing {
        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => $name,
        ]);

        return Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => '1000.00',
            'unit' => 'kg',
            'stock' => 20,
            'publication_status' => $publicationStatus,
        ]);
    }
}