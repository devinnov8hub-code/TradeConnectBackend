<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class MarketplaceSummaryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $visibleListings = Listing::query()
            ->where(
                'listings.publication_status',
                ListingPublicationStatus::Live->value
            )
            ->where(
                'listings.status',
                ListingStatus::Active->value
            )
            ->whereHas(
                'farmer',
                fn (Builder $query) => $query
                    ->where(
                        'status',
                        FarmerStatus::Active->value
                    )
                    ->where(
                        'verification_status',
                        FarmerVerificationStatus::Verified->value
                    )
            );

        $totalListings = (clone $visibleListings)->count();

        $farmers = (clone $visibleListings)
            ->distinct()
            ->count('listings.farmer_id');

        $lgas = (clone $visibleListings)
            ->join(
                'farmers',
                'farmers.id',
                '=',
                'listings.farmer_id'
            )
            ->whereNotNull('farmers.lga')
            ->where('farmers.lga', '<>', '')
            ->distinct()
            ->count('farmers.lga');

        return response()->json([
            'data' => [
                'listings' => $totalListings,
                'farmers' => $farmers,
                'lgas' => $lgas,
            ],
        ]);
    }
}