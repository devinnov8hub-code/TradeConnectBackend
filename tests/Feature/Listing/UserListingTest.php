<?php

namespace Tests\Feature\Listing;

use App\Enums\FarmerStatus;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Farmer;
use App\Models\Listing;
use App\Models\Produce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_fetch_active_listings(): void
    {
        $farmer = Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => '08012345678',
        ]);
        $category = Category::create(['name' => 'Grains']);
        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => 'Rice',
            'image' => base64_encode('rice'),
            'image_mime' => 'image/jpeg',
        ]);
        Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => 45000,
            'stock' => 50,
            'status' => ListingStatus::Active,
        ]);
        $beans = Produce::create([
            'category_id' => $category->id,
            'name' => 'Beans',
            'image' => base64_encode('beans'),
            'image_mime' => 'image/jpeg',
        ]);
        Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $beans->id,
            'price' => 40000,
            'stock' => 0,
            'status' => ListingStatus::Inactive,
        ]);

        $response = $this->getJson('/api/v1/listings');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.produce.name', 'Rice')
            ->assertJsonPath('data.0.farmer.name', 'Ibrahim Musa')
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'price',
                        'stock',
                        'status',
                        'produce' => ['id', 'name', 'image_url', 'category' => ['id', 'name']],
                        'farmer' => ['id', 'name', 'state', 'lga'],
                    ],
                ],
            ]);
    }

    public function test_public_can_get_one_active_listing(): void
    {
        [$listing] = $this->seedListings();

        $this->getJson("/api/v1/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.produce.name', 'Rice');
    }

    public function test_inactive_listing_returns_not_found(): void
    {
        $farmer = Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => '08012345678',
        ]);
        $category = Category::create(['name' => 'Grains']);
        $produce = Produce::create([
            'category_id' => $category->id,
            'name' => 'Rice',
            'image' => base64_encode('rice'),
            'image_mime' => 'image/jpeg',
        ]);
        $listing = Listing::create([
            'farmer_id' => $farmer->id,
            'produce_id' => $produce->id,
            'price' => 45000,
            'stock' => 50,
            'status' => ListingStatus::Inactive,
        ]);

        $this->getJson("/api/v1/listings/{$listing->id}")
            ->assertNotFound()
            ->assertJson(['message' => 'Listing not found.']);
    }

    public function test_public_can_search_and_filter_listings(): void
    {
        [$riceListing, $tomatoListing] = $this->seedListings();

        $this->getJson('/api/v1/listings?search=Rice')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $riceListing->id);

        $this->getJson("/api/v1/listings?category_id={$tomatoListing->produce->category_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tomatoListing->id);

        $this->getJson("/api/v1/listings?farmer_id={$riceListing->farmer_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $riceListing->id);
    }

    public function test_public_can_sort_listings_by_price_and_category(): void
    {
        $this->seedListings();

        $byPrice = $this->getJson('/api/v1/listings?sort=price&order=asc');
        $byPrice->assertOk();
        $this->assertTrue($byPrice->json('data.0.price') <= $byPrice->json('data.1.price'));

        $byCategory = $this->getJson('/api/v1/listings?sort=category&order=asc');
        $byCategory->assertOk()
            ->assertJsonPath('data.0.produce.category.name', 'Grains')
            ->assertJsonPath('data.1.produce.category.name', 'Vegetables');
    }

    /**
     * @return array{0: Listing, 1: Listing}
     */
    private function seedListings(): array
    {
        $grainFarmer = Farmer::create([
            'name' => 'Ibrahim Musa',
            'state' => 'Niger',
            'lga' => 'Bida',
            'status' => FarmerStatus::Active,
            'phone_number' => '08012345678',
        ]);
        $vegFarmer = Farmer::create([
            'name' => 'Ada Okoro',
            'state' => 'Lagos',
            'lga' => 'Ikeja',
            'status' => FarmerStatus::Active,
            'phone_number' => '08087654321',
        ]);

        $grains = Category::create(['name' => 'Grains']);
        $vegetables = Category::create(['name' => 'Vegetables']);

        $rice = Produce::create([
            'category_id' => $grains->id,
            'name' => 'Rice',
            'image' => base64_encode('rice'),
            'image_mime' => 'image/jpeg',
        ]);
        $tomato = Produce::create([
            'category_id' => $vegetables->id,
            'name' => 'Tomato',
            'image' => base64_encode('tomato'),
            'image_mime' => 'image/jpeg',
        ]);

        $riceListing = Listing::create([
            'farmer_id' => $grainFarmer->id,
            'produce_id' => $rice->id,
            'price' => 45000,
            'stock' => 50,
            'status' => ListingStatus::Active,
        ]);
        $tomatoListing = Listing::create([
            'farmer_id' => $vegFarmer->id,
            'produce_id' => $tomato->id,
            'price' => 12000,
            'stock' => 80,
            'status' => ListingStatus::Active,
        ]);

        $tomatoListing->load('produce.category');

        return [$riceListing, $tomatoListing];
    }
}
