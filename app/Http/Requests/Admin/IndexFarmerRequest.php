<?php

namespace App\Http\Requests\Admin;

use App\Enums\FarmerStatus;
use App\Enums\FarmerVerificationStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexFarmerRequest extends ApiFormRequest
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

            'state' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'lga' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'status' => [
                'sometimes',
                'nullable',
                Rule::enum(FarmerStatus::class),
            ],

            'verification_status' => [
                'sometimes',
                'nullable',
                Rule::enum(
                    FarmerVerificationStatus::class
                ),
            ],

            'sort' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'name',
                    'farmer_code',
                    'created_at',
                    'listings_count',
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
            'status.enum' =>
                'Status must be active or inactive.',

            'verification_status.enum' =>
                'Verification status must be pending, verified, or rejected.',

            'sort.in' =>
                'Sort must be one of: name, farmer_code, created_at, listings_count.',

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