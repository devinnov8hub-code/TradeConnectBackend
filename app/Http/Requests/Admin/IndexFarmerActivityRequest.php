<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;

class IndexFarmerActivityRequest extends ApiFormRequest
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

            'limit' => [
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
            'search.string' =>
                'Search must be a string.',

            'search.max' =>
                'Search cannot exceed 255 characters.',

            'limit.integer' =>
                'Limit must be an integer.',

            'limit.min' =>
                'Limit must be at least 1.',

            'limit.max' =>
                'Limit cannot exceed 100.',
        ];
    }
}