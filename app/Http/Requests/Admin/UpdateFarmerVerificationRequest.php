<?php

namespace App\Http\Requests\Admin;

use App\Enums\FarmerVerificationStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmerVerificationRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_status' => [
                'required',
                Rule::enum(
                    FarmerVerificationStatus::class
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'verification_status.required' =>
                'Verification status is required.',

            'verification_status.enum' =>
                'Verification status must be pending, verified, or rejected.',
        ];
    }
}