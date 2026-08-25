<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuyerResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'account_code' =>
                $this->account_code,

            'name' =>
                $this->name,

            'email' =>
                $this->email,

            'phone_number' =>
                $this->phone_number,

            'state' =>
                $this->state,

            'lga' =>
                $this->lga,

            'address' =>
                $this->address,

            'avatar_path' =>
                $this->avatar_path,

            'role' =>
                $this->role->value,

            'status' =>
                $this->status->value,

            'orders_count' =>
                $this->whenCounted(
                    'orders'
                ),

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}