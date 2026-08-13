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
            'order_id' => [
                'required',
                'integer',
                'exists:orders,id',
            ],

            /*
             * Null means an order-wide dispute.
             */
            'order_item_id' => [
                'nullable',
                'integer',
                'exists:order_items,id',
            ],

            'subject' => [
                'required',
                'string',
                'max:255',
            ],

            'message' => [
                'required',
                'string',
                'max:5000',
            ],

            'attachments' => [
                'sometimes',
                'array',
                'max:5',
            ],

            'attachments.*' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' =>
                'Order is required.',

            'order_id.exists' =>
                'Order not found.',

            'order_item_id.exists' =>
                'Order item not found.',

            'subject.required' =>
                'Subject is required.',

            'message.required' =>
                'Message is required.',

            'attachments.array' =>
                'Attachments must be an array.',

            'attachments.max' =>
                'A maximum of 5 attachments can be sent with one message.',

            'attachments.*.file' =>
                'Each attachment must be a valid file.',

            'attachments.*.mimes' =>
                'Attachments must be JPEG, PNG, WebP, or PDF files.',

            'attachments.*.max' =>
                'Each attachment cannot exceed 5 MB.',
        ];
    }
}