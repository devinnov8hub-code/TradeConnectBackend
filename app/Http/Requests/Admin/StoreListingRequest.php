<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Http\Requests\ApiFormRequest;
use App\Models\Farmer;
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
        /** @var Farmer $farmer */
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
                 * Pricing validation is independent from
                 * publication eligibility.
                 */
                $pricingHasErrors =
                    $validator
                        ->errors()
                        ->has('price')
                    || $validator
                        ->errors()
                        ->has(
                            'original_price'
                        )
                    || $validator
                        ->errors()
                        ->has(
                            'discount_percent'
                        );

                if (! $pricingHasErrors) {
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

                $this
                    ->validatePublicationEligibility(
                        $validator
                    );
            }
        );
    }

    private function validatePublicationEligibility(
        Validator $validator
    ): void {
        /*
         * Leave malformed enum values to their normal
         * Laravel validation rules.
         */
        if (
            $validator
                ->errors()
                ->has(
                    'publication_status'
                )
            || $validator
                ->errors()
                ->has('status')
        ) {
            return;
        }

        $targetStatus =
            $this->targetPublicationStatus();

        if (
            $targetStatus
            !== ListingPublicationStatus::Live
        ) {
            return;
        }

        /** @var Farmer $farmer */
        $farmer =
            $this->route('farmer');

        if (
            $farmer
                ->canPublishListings()
        ) {
            return;
        }

        /*
         * Attach the error to whichever API contract
         * attempted to publish the listing.
         */
        $field =
            $this->has(
                'publication_status'
            )
                ? 'publication_status'
                : 'status';

        $validator
            ->errors()
            ->add(
                $field,
                'Listing cannot be published because the farmer must be active and verified.'
            );
    }

    private function targetPublicationStatus():
        ListingPublicationStatus
    {
        /*
         * New publication state wins when both new and
         * legacy fields are supplied.
         */
        if (
            $this->has(
                'publication_status'
            )
        ) {
            return ListingPublicationStatus::from(
                (string) $this->input(
                    'publication_status'
                )
            );
        }

        if ($this->has('status')) {
            $legacyStatus =
                ListingStatus::from(
                    (string) $this->input(
                        'status'
                    )
                );

            return $legacyStatus
                === ListingStatus::Active
                    ? ListingPublicationStatus::Live
                    : ListingPublicationStatus::Inactive;
        }

        /*
         * New listings default to pending.
         */
        return ListingPublicationStatus::Pending;
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