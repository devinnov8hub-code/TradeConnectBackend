<?php

namespace App\Http\Requests\Admin;

use App\Enums\OrderStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::enum(OrderStatus::class),
                Rule::notIn([OrderStatus::New->value]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.enum' => 'Status must be in_transit, cancelled, or delivered.',
            'status.not_in' => 'Use the default new status when creating an order.',
        ];
    }
}
