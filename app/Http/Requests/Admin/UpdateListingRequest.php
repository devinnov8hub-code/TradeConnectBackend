<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Http\Requests\ApiFormRequest;
use App\Models\Listing;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateListingRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Listing $listing */
        $listing =
            $this->route('listing');

        return [
            'produce_id' => [
                'sometimes',
                'integer',
                'exists:produce,id',

                Rule::unique(
                    'listings',
                    'produce_id'
                )
                    ->where(
                        'farmer_id',
                        $listing->farmer_id
                    )
                    ->ignore(
                        $listing
                    ),
            ],

            'price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],

            'original_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'discount_percent' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'unit' => [
                'sometimes',
                'string',
                'max:50',
            ],

            'stock' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'minimum_order_quantity' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'label' => [
                'sometimes',
                'nullable',
                'string',

                Rule::in([
                    'fresh',
                    'organic',
                    'seasonal',
                ]),
            ],

            'grade' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'available_from' => [
                'sometimes',
                'nullable',
                'date',
            ],

            /*
             * Legacy compatibility state.
             */
            'status' => [
                'sometimes',
                Rule::enum(
                    ListingStatus::class
                ),
            ],

            /*
             * Preferred publication state.
             */
            'publication_status' => [
                'sometimes',
                Rule::enum(
                    ListingPublicationStatus::class
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'produce_id.exists' =>
                'Produce not found.',

            'produce_id.unique' =>
                'This farmer already has a listing for this produce.',

            'price.min' =>
                'Price must be at least 0.',

            'original_price.min' =>
                'Original price must be at least 0.',

            'discount_percent.min' =>
                'Discount percent must be at least 0.',

            'discount_percent.max' =>
                'Discount percent cannot exceed 100.',

            'unit.max' =>
                'Unit cannot exceed 50 characters.',

            'stock.min' =>
                'Stock must be at least 0.',

            'minimum_order_quantity.min' =>
                'Minimum order quantity must be at least 1.',

            'description.max' =>
                'Description cannot exceed 5000 characters.',

            'label.in' =>
                'Label must be fresh, organic, or seasonal.',

            'grade.max' =>
                'Grade cannot exceed 100 characters.',

            'status.enum' =>
                'Status must be active or inactive.',

            'publication_status.enum' =>
                'Publication status must be pending, live, or inactive.',
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                /*
                 * Do not suppress pricing validation merely
                 * because an unrelated listing field failed.
                 */
                if (
                    $validator
                        ->errors()
                        ->has('price')
                    || $validator
                        ->errors()
                        ->has('original_price')
                    || $validator
                        ->errors()
                        ->has('discount_percent')
                ) {
                    return;
                }

                /** @var Listing $listing */
                $listing =
                    $this->route(
                        'listing'
                    );

                $price =
                    $this->has('price')
                        ? (float) $this->input(
                            'price'
                        )
                        : (float) $listing
                            ->price;

                $originalPrice =
                    $this->has(
                        'original_price'
                    )
                        ? $this->input(
                            'original_price'
                        )
                        : $listing
                            ->original_price;

                /*
                 * Only validate a percentage supplied by
                 * the caller. Otherwise the Listing model
                 * derives it from the final prices.
                 */
                $discountPercent =
                    $this->has(
                        'discount_percent'
                    )
                        ? $this->input(
                            'discount_percent'
                        )
                        : null;

                $this->validatePricing(
                    $validator,
                    $price,
                    $originalPrice,
                    $discountPercent
                );
            }
        );
    }

    private function validatePricing(
        Validator $validator,
        float $price,
        mixed $originalPrice,
        mixed $discountPercent
    ): void {
        if (
            $originalPrice === null
        ) {
            if (
                $discountPercent !== null
            ) {
                $validator
                    ->errors()
                    ->add(
                        'original_price',
                        'Original price is required when a discount percent is supplied.'
                    );
            }

            return;
        }

        $originalPrice =
            (float) $originalPrice;

        if (
            $originalPrice < $price
        ) {
            $validator
                ->errors()
                ->add(
                    'original_price',
                    'Original price cannot be less than the current selling price.'
                );

            return;
        }

        if (
            $discountPercent === null
        ) {
            return;
        }

        $expectedDiscount =
            $originalPrice > 0
                ? round(
                    (
                        (
                            $originalPrice
                            - $price
                        )
                        / $originalPrice
                    )
                    * 100,
                    2
                )
                : 0.0;

        if (
            abs(
                (float) $discountPercent
                - $expectedDiscount
            ) > 0.01
        ) {
            $validator
                ->errors()
                ->add(
                    'discount_percent',
                    'Discount percent does not match the original and current prices.'
                );
        }
    }
}