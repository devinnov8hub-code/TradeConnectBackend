<?php

namespace App\Http\Requests\Admin;

use App\Enums\DisputeStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateDisputeStatusRequest extends ApiFormRequest
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
                Rule::enum(DisputeStatus::class),
                Rule::notIn([DisputeStatus::Open->value]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required.',
            'status.enum' => 'Status must be resolved or closed.',
            'status.not_in' => 'Use resolved or closed to update a dispute.',
        ];
    }
}
