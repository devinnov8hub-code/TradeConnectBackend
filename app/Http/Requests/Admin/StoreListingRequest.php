<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class StoreListingRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $farmer = $this->route('farmer');

        return [
            'produce_id' => [
                'required',
                'integer',
                'exists:produce,id',

                Rule::unique(
                    'listings',
                    'produce_id'
                )->where(
                    'farmer_id',
                    $farmer->id
                ),
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'unit' => [
                'required',
                'string',
                'max:50',
            ],

            'stock' => [
                'required',
                'integer',
                'min:0',
            ],

            'status' => [
                'required',
                Rule::enum(
                    ListingStatus::class
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'produce_id.required' =>
                'Produce is required.',

            'produce_id.exists' =>
                'Produce not found.',

            'produce_id.unique' =>
                'This farmer already has a listing for this produce.',

            'price.required' =>
                'Price is required.',

            'price.min' =>
                'Price must be at least 0.',

            'unit.required' =>
                'Unit is required.',

            'unit.max' =>
                'Unit cannot exceed 50 characters.',

            'stock.required' =>
                'Stock is required.',

            'stock.min' =>
                'Stock must be at least 0.',

            'status.required' =>
                'Status is required.',

            'status.enum' =>
                'Status must be active or inactive.',
        ];
    }
}