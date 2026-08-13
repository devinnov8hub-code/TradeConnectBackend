<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farmer_id' => $this->farmer_id,
            'produce_id' => $this->produce_id,

            'price' => $this->price,
            'unit' => $this->unit,
            'stock' => $this->stock,

            'status' => $this->status->value,

            'produce' => [
                'id' => $this->produce->id,
                'name' => $this->produce->name,
                'image_url' => $this->produce->image_url,

                'category' => [
                    'id' => $this->produce->category->id,
                    'name' => $this->produce->category->name,
                ],
            ],

            'farmer' => [
                'id' => $this->farmer->id,
                'name' => $this->farmer->name,
                'state' => $this->farmer->state,
                'lga' => $this->farmer->lga,
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}