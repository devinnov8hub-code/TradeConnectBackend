<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexDashboardRevenueRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'week',
                    'month',
                    'year',
                ]),
            ],

            'farmer_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:farmers,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' =>
                'Period must be week, month, or year.',

            'farmer_id.integer' =>
                'Farmer must be a valid integer ID.',

            'farmer_id.exists' =>
                'Farmer not found.',
        ];
    }
}