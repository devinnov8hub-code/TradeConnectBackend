<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexActivityRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'order',
                    'dispute',
                    'farmer',
                    'listing',
                    'buyer',
                ]),
            ],

            'limit' => [
                'sometimes',
                'integer',
                'min:1',
                'max:50',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' =>
                'Activity type must be order, dispute, farmer, listing, or buyer.',

            'limit.integer' =>
                'Limit must be an integer.',

            'limit.min' =>
                'Limit must be at least 1.',

            'limit.max' =>
                'Limit cannot exceed 50.',
        ];
    }
}