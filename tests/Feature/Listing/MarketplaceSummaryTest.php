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

class MarketplaceSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_summary_counts_only_publicly_visible_inventory(): void
    {
        $visibleFarmer = $this->farmer(
            'Visible Farmer',
            'Kaduna',
            'Kagarko',
            FarmerVerificationStatus::Verified
        );

        $otherVisibleFarmer = $this->farmer(
            'Second Farmer',
            'Kaduna',
            'Kajuru',
            FarmerVerificationStatus::Verified
        );

        $pendingFarmer = $this->farmer(
            'Pending Farmer',
            'Kaduna',
            'Kachia',
            FarmerVerificationStatus::Pending
        );

        $category = Category::create([
            'name' => 'Grains',
        ]);

        foreach (
            [
                [$visibleFarmer, 'Rice'],
                [$visibleFarmer, 'Maize'],
                [$otherVisibleFarmer, 'Millet'],
                [$pendingFarmer, 'Sorghum'],
            ] as [$farmer, $name]
        ) {
            $produce = Produce::create([
                'category_id' => $category->id,
                'name' => $name,
            ]);

            Listing::create([
                'farmer_id' => $farmer->id,
                'produce_id' => $produce->id,
                'price' => '1000.00',
                'unit' => 'kg',
                'stock' => 20,
                'publication_status' =>
                    ListingPublicationStatus::Live,
            ]);
        }

        $this
            ->getJson('/api/v1/marketplace/summary')
            ->assertOk()
            ->assertJsonPath('data.listings', 3)
            ->assertJsonPath('data.farmers', 2)
            ->assertJsonPath('data.lgas', 2);
    }

    private function farmer(
        string $name,
        string $state,
        string $lga,
        FarmerVerificationStatus $verification
    ): Farmer {
        return Farmer::create([
            'name' => $name,
            'state' => $state,
            'lga' => $lga,
            'phone_number' =>
                fake()->unique()->numerify('080########'),
            'status' => FarmerStatus::Active,
            'verification_status' => $verification,
        ]);
    }
}