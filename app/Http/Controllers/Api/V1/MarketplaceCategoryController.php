<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class MarketplaceCategoryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $categories = Category::query()
            ->withCount([
                'listings as listing_count' => function (Builder $query): void {
                    $query
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
                            fn (Builder $farmerQuery) =>
                                $farmerQuery
                                    ->where(
                                        'status',
                                        FarmerStatus::Active->value
                                    )
                                    ->where(
                                        'verification_status',
                                        FarmerVerificationStatus::Verified->value
                                    )
                        );
                },
            ])
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        return response()->json([
            'data' => $categories,
        ]);
    }
}