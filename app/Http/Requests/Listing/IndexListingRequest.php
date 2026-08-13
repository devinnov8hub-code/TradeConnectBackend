<?php

namespace App\Http\Requests\Listing;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class IndexListingRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:categories,id',
            ],

            'farmer_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:farmers,id',
            ],

            'state' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'lga' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
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

            'availability' => [
                'sometimes',
                'nullable',
                'string',

                Rule::in([
                    'available',
                    'upcoming',
                    'out_of_stock',
                ]),
            ],

            'min_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'max_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
            ],

            'sort' => [
                'sometimes',
                'nullable',
                'string',

                Rule::in([
                    'price',
                    'stock',
                    'created_at',
                    'produce',
                    'farmer',
                    'category',
                ]),
            ],

            'order' => [
                'sometimes',
                'nullable',
                'string',

                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'page' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' =>
                'Category not found.',

            'farmer_id.exists' =>
                'Farmer not found.',

            'state.max' =>
                'State cannot exceed 100 characters.',

            'lga.max' =>
                'LGA cannot exceed 100 characters.',

            'label.in' =>
                'Label must be fresh, organic, or seasonal.',

            'availability.in' =>
                'Availability must be available, upcoming, or out_of_stock.',

            'min_price.min' =>
                'Minimum price must be at least 0.',

            'max_price.min' =>
                'Maximum price must be at least 0.',

            'sort.in' =>
                'Sort must be one of: price, stock, created_at, produce, farmer, category.',

            'order.in' =>
                'Order must be asc or desc.',

            'page.integer' =>
                'Page must be an integer.',

            'page.min' =>
                'Page must be at least 1.',

            'per_page.integer' =>
                'Per page must be an integer.',

            'per_page.min' =>
                'Per page must be at least 1.',

            'per_page.max' =>
                'Per page cannot exceed 100.',
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
                        ->has('min_price')
                    || $validator
                        ->errors()
                        ->has('max_price')
                ) {
                    return;
                }

                if (
                    ! $this->filled(
                        'min_price'
                    )
                    || ! $this->filled(
                        'max_price'
                    )
                ) {
                    return;
                }

                $minimum =
                    (float) $this->input(
                        'min_price'
                    );

                $maximum =
                    (float) $this->input(
                        'max_price'
                    );

                if (
                    $maximum < $minimum
                ) {
                    $validator
                        ->errors()
                        ->add(
                            'max_price',
                            'Maximum price cannot be less than minimum price.'
                        );
                }
            }
        );
    }
}