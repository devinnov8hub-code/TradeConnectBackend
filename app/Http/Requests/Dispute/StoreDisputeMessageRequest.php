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