<?php

namespace App\Http\Requests\Order;

use App\Enums\ListingStatus;
use App\Http\Requests\ApiFormRequest;
use App\Models\Listing;

class StoreOrderRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'listing_id.required' => 'Listing is required.',
            'listing_id.exists' => 'Listing not found.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $listing = Listing::find($this->input('listing_id'));

            if (! $listing) {
                return;
            }

            if ($listing->status !== ListingStatus::Active) {
                $validator->errors()->add('listing_id', 'This listing is not available.');
            }

            if ($listing->stock < (int) $this->input('quantity')) {
                $validator->errors()->add('quantity', 'Insufficient stock for this listing.');
            }
        });
    }
}
