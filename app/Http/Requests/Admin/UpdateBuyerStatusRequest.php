<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateBuyerStatusRequest extends ApiFormRequest
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
                Rule::enum(UserStatus::class),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' =>
                'Status is required.',

            'status.enum' =>
                'Status must be active or inactive.',
        ];
    }
}