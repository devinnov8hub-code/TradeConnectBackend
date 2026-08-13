<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreListingRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $farmer =
            $this->route('farmer');

        return [
            'produce_id' => [
                'required',
                'integer',
                'exists:produce,id',

                Rule::unique(
                    'listings',
                    'produce_id'
                )->where(
                    'farmer_id',
                    $farmer->id
                ),
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'original_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'discount_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            'unit' => [
                'required',
                'string',
                'max:50',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'minimum_order_quantity' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'label' => [
                'nullable',
                'string',

                Rule::in([
                    'fresh',
                    'organic',
                    'seasonal',
                ]),
            ],

            'grade' => [
                'nullable',
                'string',
                'max:100',
            ],

            'available_from' => [
                'nullable',
                'date',
            ],

            /*
             * Legacy client compatibility.
             */
            'status' => [
                'sometimes',
                Rule::enum(
                    ListingStatus::class
                ),
            ],

            /*
             * Preferred new publication state.
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
            'produce_id.required' =>
                'Produce is required.',

            'produce_id.exists' =>
                'Produce not found.',

            'produce_id.unique' =>
                'This farmer already has a listing for this produce.',

            'price.required' =>
                'Price is required.',

            'price.min' =>
                'Price must be at least 0.',

            'original_price.min' =>
                'Original price must be at least 0.',

            'discount_percent.min' =>
                'Discount percent must be at least 0.',

            'discount_percent.max' =>
                'Discount percent cannot exceed 100.',

            'unit.required' =>
                'Unit is required.',

            'unit.max' =>
                'Unit cannot exceed 50 characters.',

            'stock.required' =>
                'Stock is required.',

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
                 * An unrelated validation failure such as
                 * label must not prevent us from reporting
                 * pricing errors too.
                 *
                 * Only skip the custom pricing calculation
                 * when one of its own inputs failed normal
                 * validation.
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

                $this->validatePricing(
                    $validator,
                    (float) $this->input(
                        'price'
                    ),
                    $this->input(
                        'original_price'
                    ),
                    $this->input(
                        'discount_percent'
                    )
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