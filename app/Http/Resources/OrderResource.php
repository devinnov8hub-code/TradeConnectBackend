<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'listing_id' => $this->listing_id,
            'quantity' => $this->quantity,
            'total' => $this->total,
            'status' => $this->status->value,
            'buyer_name' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => $this->user->name,
            ),
            'produce' => [
                'id' => $this->listing->produce->id,
                'name' => $this->listing->produce->name,
                'image_url' => $this->listing->produce->image_url,
                'category' => [
                    'id' => $this->listing->produce->category->id,
                    'name' => $this->listing->produce->category->name,
                ],
            ],
            'buyer' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ),
            'farmer' => $this->when(
                $this->relationLoaded('listing')
                    && $this->listing->relationLoaded('farmer')
                    && $this->listing->farmer,
                fn () => [
                    'id' => $this->listing->farmer->id,
                    'name' => $this->listing->farmer->name,
                    'state' => $this->listing->farmer->state,
                    'lga' => $this->listing->farmer->lga,
                    'phone_number' => $this->listing->farmer->phone_number,
                ],
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
