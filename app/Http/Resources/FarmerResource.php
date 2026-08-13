<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmerResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'farmer_code' =>
                $this->farmer_code,

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

            'gender' =>
                $this->gender,

            'date_of_birth' =>
                $this->date_of_birth
                    ?->toDateString(),

            'status' =>
                $this->status->value,

            'verification_status' =>
                $this
                    ->verification_status
                    ->value,

            'farm' => [
                'name' =>
                    $this->farm_name,

                'size_hectares' =>
                    $this->farm_size_hectares,

                'farming_method' =>
                    $this->farming_method,

                'years_experience' =>
                    $this->years_experience,

                'address' =>
                    $this->farm_address,
            ],

            'listings_count' =>
                $this->whenCounted(
                    'listings'
                ),

            'verified_at' =>
                $this->verified_at,

            'suspended_at' =>
                $this->suspended_at,

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}