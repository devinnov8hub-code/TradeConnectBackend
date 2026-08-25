<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class IndexOrderRequest extends ApiFormRequest
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

            'status' => [
                'sometimes',
                'nullable',
                Rule::enum(OrderStatus::class),
            ],

            'payment_status' => [
                'sometimes',
                'nullable',
                Rule::enum(PaymentStatus::class),
            ],

            'farmer_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:farmers,id',
            ],

            'sort' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    'created_at',
                    'order_number',
                    'total',
                    'status',
                    'payment_status',
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
                'Status must be new, in_transit, cancelled, or delivered.',

            'payment_status.enum' =>
                'Payment status must be pending, paid, failed, or refunded.',

            'farmer_id.exists' =>
                'Farmer not found.',

            'sort.in' =>
                'Sort must be one of: created_at, order_number, total, status, payment_status.',

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