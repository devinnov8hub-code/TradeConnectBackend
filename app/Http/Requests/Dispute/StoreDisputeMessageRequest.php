<?php

namespace App\Http\Requests\Dispute;

use App\Http\Requests\ApiFormRequest;

class StoreDisputeMessageRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Message is required.',
        ];
    }
}
