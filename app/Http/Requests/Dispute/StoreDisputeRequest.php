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
             * Optional for order-wide issues.
             *
             * The controller additionally verifies that
             * the selected item belongs to order_id.
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
        ];
    }
}