<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;

class ReorderListingImagesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image_ids' => [
                'required',
                'array',
                'min:1',
                'max:6',
            ],

            'image_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:listing_images,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image_ids.required' =>
                'Image order is required.',

            'image_ids.array' =>
                'Image order must be an array.',

            'image_ids.min' =>
                'At least one image is required.',

            'image_ids.*.distinct' =>
                'The same image cannot appear more than once.',

            'image_ids.*.exists' =>
                'One or more listing images could not be found.',
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                if (
                    $validator
                        ->errors()
                        ->isNotEmpty()
                ) {
                    return;
                }

                $listing =
                    $this->route(
                        'listing'
                    );

                $requestedIds =
                    collect(
                        $this->input(
                            'image_ids',
                            []
                        )
                    )
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->sort()
                        ->values();

                $listingIds =
                    $listing
                        ->images()
                        ->pluck('id')
                        ->map(
                            fn ($id) =>
                                (int) $id
                        )
                        ->sort()
                        ->values();

                /*
                 * Reordering must include every image
                 * belonging to this listing and no images
                 * belonging to another listing.
                 */
                if (
                    $requestedIds->all()
                    !== $listingIds->all()
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'image_ids',
                            'Image order must contain every image belonging to this listing exactly once.'
                        );
                }
            }
        );
    }
}