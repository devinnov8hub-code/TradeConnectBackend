<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Validator;

class StoreListingImagesRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => [
                'required',
                'array',
                'min:1',
                'max:6',
            ],

            'images.*' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' =>
                'At least one listing image is required.',

            'images.array' =>
                'Images must be an array.',

            'images.min' =>
                'At least one listing image is required.',

            'images.max' =>
                'A maximum of 6 images can be uploaded at once.',

            'images.*.image' =>
                'Each uploaded file must be an image.',

            'images.*.mimes' =>
                'Listing images must be JPEG, PNG, or WebP.',

            'images.*.max' =>
                'Each listing image cannot exceed 5 MB.',
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
                        ->has('images')
                ) {
                    return;
                }

                $listing =
                    $this->route(
                        'listing'
                    );

                if (! $listing) {
                    return;
                }

                $existingCount =
                    $listing
                        ->images()
                        ->count();

                $incomingCount =
                    count(
                        $this->file(
                            'images',
                            []
                        )
                    );

                if (
                    $existingCount
                    + $incomingCount
                    > 6
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'images',
                            'A listing cannot have more than 6 images.'
                        );
                }
            }
        );
    }
}