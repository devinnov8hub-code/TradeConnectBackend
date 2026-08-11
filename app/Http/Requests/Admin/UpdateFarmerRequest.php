<?php

namespace App\Http\Requests\Admin;

use App\Enums\FarmerStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmerRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:255'],
            'lga' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(FarmerStatus::class)],
            'phone_number' => ['required', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Farmer name is required.',
            'state.required' => 'State is required.',
            'lga.required' => 'LGA is required.',
            'status.required' => 'Status is required.',
            'status.enum' => 'Status must be active or inactive.',
            'phone_number.required' => 'Phone number is required.',
        ];
    }

    public function attributes(): array
    {
        return [
            'lga' => 'LGA',
            'phone_number' => 'phone number',
        ];
    }
}
