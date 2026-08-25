<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmerResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        /*
         * These are the fields that make up the complete
         * admin farmer profile.
         *
         * Zero is considered a supplied value for numeric
         * fields such as farm size or years experience.
         */
        $profileFields = [
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
                $this->date_of_birth,

            'farm_name' =>
                $this->farm_name,

            'farm_size_hectares' =>
                $this
                    ->farm_size_hectares,

            'farming_method' =>
                $this
                    ->farming_method,

            'years_experience' =>
                $this
                    ->years_experience,

            'farm_address' =>
                $this
                    ->farm_address,
        ];

        $missingFields =
            collect(
                $profileFields
            )
                ->filter(
                    function (
                        mixed $value
                    ): bool {
                        if (
                            $value === null
                        ) {
                            return true;
                        }

                        if (
                            is_string(
                                $value
                            )
                            && trim(
                                $value
                            ) === ''
                        ) {
                            return true;
                        }

                        return false;
                    }
                )
                ->keys()
                ->values();

        $totalFields =
            count(
                $profileFields
            );

        $completedFields =
            $totalFields
            - $missingFields
                ->count();

        $percentage =
            $totalFields > 0
                ? (int) round(
                    (
                        $completedFields
                        / $totalFields
                    )
                    * 100
                )
                : 100;

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
                $this
                    ->date_of_birth
                    ?->toDateString(),

            'status' =>
                $this
                    ->status
                    ->value,

            'verification_status' =>
                $this
                    ->verification_status
                    ->value,

            /*
             * Useful for enabling/disabling publication
             * controls in the admin frontend.
             */
            'can_publish_listings' =>
                $this
                    ->canPublishListings(),

            /*
             * This is informational only.
             *
             * It does not affect verification or
             * publication eligibility.
             */
            'profile_completeness' => [
                'percentage' =>
                    $percentage,

                'completed_fields' =>
                    $completedFields,

                'total_fields' =>
                    $totalFields,

                'missing_fields' =>
                    $missingFields
                        ->all(),
            ],

            'farm' => [
                'name' =>
                    $this->farm_name,

                'size_hectares' =>
                    $this
                        ->farm_size_hectares,

                'farming_method' =>
                    $this
                        ->farming_method,

                'years_experience' =>
                    $this
                        ->years_experience,

                'address' =>
                    $this
                        ->farm_address,
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