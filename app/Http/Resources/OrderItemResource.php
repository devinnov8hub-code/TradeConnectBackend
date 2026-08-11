<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $produce = $this->relationLoaded('produce')
            ? $this->produce
            : null;

        $farmer = $this->relationLoaded('farmer')
            ? $this->farmer
            : null;

        return [
            'id' => $this->id,

            'listing_id' => $this->listing_id,
            'farmer_id' => $this->farmer_id,
            'produce_id' => $this->produce_id,

            /*
             * Snapshot information.
             *
             * These values describe what was purchased at
             * the moment the order was created.
             */
            'produce_name' => $this->produce_name,
            'category_name' => $this->category_name,
            'unit' => $this->unit,

            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'line_total' => $this->line_total,

            'produce' => [
                'id' => $this->produce_id,
                'name' => $this->produce_name,
                'image_url' => $produce?->image_url,

                'category' => [
                    'id' => $produce?->category?->id,
                    'name' => $this->category_name,
                ],
            ],

            'farmer' => $farmer
                ? [
                    'id' => $farmer->id,
                    'name' => $farmer->name,
                    'state' => $farmer->state,
                    'lga' => $farmer->lga,
                    'phone_number' => $farmer->phone_number,
                ]
                : null,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}