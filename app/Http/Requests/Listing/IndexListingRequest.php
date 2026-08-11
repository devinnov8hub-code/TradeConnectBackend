<?php

namespace App\Http\Requests\Listing;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexListingRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'farmer_id' => ['sometimes', 'nullable', 'integer', 'exists:farmers,id'],
            'sort' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(['price', 'stock', 'created_at', 'produce', 'farmer', 'category']),
            ],
            'order' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.exists' => 'Category not found.',
            'farmer_id.exists' => 'Farmer not found.',
            'sort.in' => 'Sort must be one of: price, stock, created_at, produce, farmer, category.',
            'order.in' => 'Order must be asc or desc.',
        ];
    }
}
