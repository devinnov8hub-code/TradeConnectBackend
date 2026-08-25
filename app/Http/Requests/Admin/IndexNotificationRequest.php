<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexNotificationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'all',
                    'unread',
                    'read',
                ]),
            ],

            'type' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'order',
                    'payment',
                    'dispute',
                    'farmer',
                    'listing',
                    'buyer',
                    'system',
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
            'status.in' =>
                'Status must be all, unread, or read.',

            'type.in' =>
                'Type must be order, payment, dispute, farmer, listing, buyer, or system.',

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