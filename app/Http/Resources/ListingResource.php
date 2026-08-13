<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListingResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $discountAmount =
            '0.00';

        if (
            $this->original_price !== null
            && bccomp(
                (string) $this->original_price,
                (string) $this->price,
                2
            ) === 1
        ) {
            $discountAmount =
                bcsub(
                    (string) $this->original_price,
                    (string) $this->price,
                    2
                );
        }

        return [
            'id' =>
                $this->id,

            'farmer_id' =>
                $this->farmer_id,

            'produce_id' =>
                $this->produce_id,

            /*
             * Commercial information.
             */
            'price' =>
                $this->price,

            'original_price' =>
                $this->original_price,

            'discount_percent' =>
                $this->discount_percent,

            'discount_amount' =>
                $discountAmount,

            'unit' =>
                $this->unit,

            'stock' =>
                $this->stock,

            'minimum_order_quantity' =>
                $this->minimum_order_quantity,

            /*
             * Marketplace presentation.
             */
            'description' =>
                $this->description,

            'label' =>
                $this->label,

            'grade' =>
                $this->grade,

            'available_from' =>
                $this->available_from
                    ?->toDateString(),

            'is_available' =>
                $this->isAvailable(),

            /*
             * Legacy state remains temporarily while
             * publication_status becomes the preferred
             * frontend field.
             */
            'status' =>
                $this->status->value,

            'publication_status' =>
                $this
                    ->publication_status
                    ->value,

            'published_at' =>
                $this->published_at,

            'produce' => [
                'id' =>
                    $this->produce->id,

                'name' =>
                    $this->produce->name,

                /*
                 * This remains the existing global
                 * produce image for now.
                 *
                 * Listing-specific image storage will
                 * be the separate media sub-slice.
                 */
                'image_url' =>
                    $this->produce
                        ->image_url,

                'category' => [
                    'id' =>
                        $this
                            ->produce
                            ->category
                            ->id,

                    'name' =>
                        $this
                            ->produce
                            ->category
                            ->name,
                ],
            ],

            'farmer' => [
                'id' =>
                    $this->farmer->id,

                'name' =>
                    $this->farmer->name,

                'state' =>
                    $this->farmer->state,

                'lga' =>
                    $this->farmer->lga,
            ],

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}