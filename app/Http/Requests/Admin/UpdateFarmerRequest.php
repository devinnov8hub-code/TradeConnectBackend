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
        /** @var \App\Models\Farmer $farmer */
        $farmer = $this->route('farmer');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'string',
                'email',
                'max:255',

                Rule::unique(
                    'farmers',
                    'email'
                )->ignore($farmer),
            ],

            'phone_number' => [
                'required',
                'string',
                'max:20',
            ],

            'state' => [
                'required',
                'string',
                'max:255',
            ],

            'lga' => [
                'required',
                'string',
                'max:255',
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'gender' => [
                'nullable',
                'string',
                'max:30',
            ],

            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'farm_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'farm_size_hectares' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'farming_method' => [
                'nullable',
                'string',
                'max:255',
            ],

            'years_experience' => [
                'nullable',
                'integer',
                'min:0',
                'max:65535',
            ],

            'farm_address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'status' => [
                'required',
                Rule::enum(
                    FarmerStatus::class
                ),
            ],

            'farmer_code' => [
                'prohibited',
            ],

            'verification_status' => [
                'prohibited',
            ],

            'verified_at' => [
                'prohibited',
            ],

            'suspended_at' => [
                'prohibited',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' =>
                'Farmer name is required.',

            'email.email' =>
                'Email must be a valid email address.',

            'email.unique' =>
                'This email is already assigned to a farmer.',

            'state.required' =>
                'State is required.',

            'lga.required' =>
                'LGA is required.',

            'status.required' =>
                'Status is required.',

            'status.enum' =>
                'Status must be active or inactive.',

            'phone_number.required' =>
                'Phone number is required.',

            'date_of_birth.before_or_equal' =>
                'Date of birth cannot be in the future.',

            'farm_size_hectares.min' =>
                'Farm size cannot be negative.',

            'years_experience.min' =>
                'Years of experience cannot be negative.',

            'farmer_code.prohibited' =>
                'Farmer code cannot be changed.',

            'verification_status.prohibited' =>
                'Use the farmer verification workflow to change verification status.',
        ];
    }

    public function attributes(): array
    {
        return [
            'lga' =>
                'LGA',

            'phone_number' =>
                'phone number',

            'farm_size_hectares' =>
                'farm size',

            'years_experience' =>
                'years of experience',
        ];
    }
}