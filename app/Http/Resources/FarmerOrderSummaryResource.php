<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmerOrderSummaryResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $farmerTotal =
            '0.00';

        if (
            $this->relationLoaded(
                'items'
            )
        ) {
            foreach (
                $this->items
                as $item
            ) {
                $farmerTotal =
                    bcadd(
                        $farmerTotal,
                        (string)
                            $item
                                ->line_total,
                        2
                    );
            }
        }

        return [
            'id' =>
                $this->id,

            'order_number' =>
                $this->order_number
                ?? 'ORD-'.str_pad(
                    (string) $this->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),

            'status' =>
                $this
                    ->status
                    ->value,

            'payment_status' =>
                $this
                    ->payment_status
                    ->value,

            /*
             * Retain total as the parent-order value
             * for compatibility with existing order
             * representations.
             */
            'total' =>
                $this->total,

            'parent_order_total' =>
                $this->total,

            /*
             * This is the value that should be used
             * when displaying the order from this
             * farmer's perspective.
             */
            'farmer_total' =>
                $farmerTotal,

            'farmer_items_count' =>
                $this->relationLoaded(
                    'items'
                )
                    ? $this
                        ->items
                        ->count()
                    : null,

            'buyer_name' =>
                $this->when(
                    $this->relationLoaded(
                        'user'
                    )
                    && $this->user,
                    fn () =>
                        $this
                            ->user
                            ->name
                ),

            'buyer' =>
                $this->when(
                    $this->relationLoaded(
                        'user'
                    )
                    && $this->user,
                    fn () => [
                        'id' =>
                            $this
                                ->user
                                ->id,

                        'name' =>
                            $this
                                ->user
                                ->name,

                        'email' =>
                            $this
                                ->user
                                ->email,
                    ]
                ),

            /*
             * Controller eager-loads only this farmer's
             * order items, even when the parent order
             * contains other farmers.
             */
            'items' =>
                OrderItemResource::collection(
                    $this->whenLoaded(
                        'items'
                    )
                ),

            'placed_at' =>
                $this->placed_at,

            'created_at' =>
                $this->created_at,
        ];
    }
}