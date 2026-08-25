<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexFarmerListingRequest extends ApiFormRequest
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

            'status' => [
                'sometimes',
                'nullable',
                Rule::enum(ListingStatus::class),
            ],

            'sort' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'created_at',
                    'price',
                    'stock',
                    'produce',
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

            'status.enum' =>
                'Status must be active or inactive.',

            'sort.in' =>
                'Sort must be one of: created_at, price, stock, produce, category.',

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
}