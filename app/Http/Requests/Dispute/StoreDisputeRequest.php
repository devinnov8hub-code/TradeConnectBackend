<?php

namespace App\Http\Requests\Dispute;

use App\Http\Requests\ApiFormRequest;

class StoreDisputeRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order is required.',
            'order_id.exists' => 'Order not found.',
            'subject.required' => 'Subject is required.',
            'message.required' => 'Message is required.',
        ];
    }
}
