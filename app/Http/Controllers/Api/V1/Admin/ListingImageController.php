<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderListingImagesRequest;
use App\Http\Requests\Admin\StoreListingImagesRequest;
use App\Http\Resources\ListingImageResource;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListingImageController extends Controller
{
    public function store(
        StoreListingImagesRequest $request,
        Listing $listing
    ): JsonResponse {
        $storedPaths = [];

        try {
            DB::transaction(
                function () use (
                    $request,
                    $listing,
                    &$storedPaths
                ): void {
                    $nextPosition =
                        ((int) $listing
                            ->images()
                            ->max(
                                'position'
                            ))
                        + 1;

                    foreach (
                        $request->file(
                            'images',
                            []
                        )
                        as $file
                    ) {
                        $path =
                            $file->store(
                                'listing-images/'
                                .$listing->id,
                                'public'
                            );

                        $storedPaths[] =
                            $path;

                        $listing
                            ->images()
                            ->create([
                                'path' =>
                                    $path,

                                'original_name' =>
                                    $file
                                        ->getClientOriginalName(),

                                'mime_type' =>
                                    $file
                                        ->getMimeType(),

                                'size' =>
                                    $file
                                        ->getSize(),

                                'position' =>
                                    $nextPosition,
                            ]);

                        $nextPosition++;
                    }
                }
            );
        } catch (Throwable $exception) {
            foreach (
                $storedPaths
                as $path
            ) {
                Storage::disk(
                    'public'
                )->delete(
                    $path
                );
            }

            throw $exception;
        }

        $listing->load(
            'images'
        );

        return response()->json([
            'data' =>
                ListingImageResource::collection(
                    $listing->images
                ),
        ], 201);
    }

    public function reorder(
        ReorderListingImagesRequest $request,
        Listing $listing
    ): JsonResponse {
        $imageIds =
            $request->validated(
                'image_ids'
            );

        DB::transaction(
            function () use (
                $listing,
                $imageIds
            ): void {
                foreach (
                    $imageIds
                    as $index => $imageId
                ) {
                    ListingImage::query()
                        ->where(
                            'listing_id',
                            $listing->id
                        )
                        ->whereKey(
                            $imageId
                        )
                        ->update([
                            'position' =>
                                $index + 1,
                        ]);
                }
            }
        );

        $listing->load(
            'images'
        );

        return response()->json([
            'data' =>
                ListingImageResource::collection(
                    $listing->images
                ),
        ]);
    }

    public function destroy(
        Listing $listing,
        ListingImage $listingImage
    ): JsonResponse {
        if (
            $listingImage->listing_id
            !== $listing->id
        ) {
            return response()->json([
                'message' =>
                    'Listing image not found.',
            ], 404);
        }

        $listingImage->delete();

        /*
         * Compact remaining positions so the first
         * image consistently remains position 1.
         */
        $remaining =
            $listing
                ->images()
                ->get();

        foreach (
            $remaining
            as $index => $image
        ) {
            if (
                $image->position
                !== $index + 1
            ) {
                $image->update([
                    'position' =>
                        $index + 1,
                ]);
            }
        }

        return response()->json([
            'message' =>
                'Listing image deleted.',
        ]);
    }
}