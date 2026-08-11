<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /*
         * These legacy relationships remain temporarily because
         * some older parts of the API still expect a single listing
         * attached directly to an order.
         */
        $legacyListing = $this->relationLoaded('listing')
            ? $this->listing
            : null;

        $legacyProduce = $legacyListing?->relationLoaded('produce')
            ? $legacyListing->produce
            : null;

        $legacyFarmer = $legacyListing?->relationLoaded('farmer')
            ? $legacyListing->farmer
            : null;

        return [
            'id' => $this->id,

            'order_number' => $this->order_number
                ?? 'ORD-'.str_pad(
                    (string) $this->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),

            'user_id' => $this->user_id,

            /*
             * Temporary compatibility fields.
             *
             * For new multi-item orders these correspond to the
             * first item only. New frontend code should use items[].
             */
            'listing_id' => $this->listing_id,
            'quantity' => $this->quantity,

            'subtotal' => $this->subtotal ?? $this->total,
            'delivery_fee' => $this->delivery_fee ?? '0.00',
            'total' => $this->total,

            'status' => $this->status->value,
            'payment_status' => $this->payment_status ?? 'pending',

            'delivery' => [
                'method' => $this->delivery_method,
                'name' => $this->delivery_name,
                'phone' => $this->delivery_phone,
                'state' => $this->delivery_state,
                'lga' => $this->delivery_lga,
                'address' => $this->delivery_address,
                'notes' => $this->delivery_notes,
            ],

            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),

            'buyer_name' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => $this->user->name,
            ),

            'buyer' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ),

            /*
             * Legacy single-item representation.
             *
             * Kept temporarily so older API consumers do not
             * immediately break.
             */
            'produce' => $this->when(
                $legacyProduce !== null,
                fn () => [
                    'id' => $legacyProduce->id,
                    'name' => $legacyProduce->name,
                    'image_url' => $legacyProduce->image_url,

                    'category' => [
                        'id' => $legacyProduce->category?->id,
                        'name' => $legacyProduce->category?->name,
                    ],
                ],
            ),

            'farmer' => $this->when(
                $legacyFarmer !== null,
                fn () => [
                    'id' => $legacyFarmer->id,
                    'name' => $legacyFarmer->name,
                    'state' => $legacyFarmer->state,
                    'lga' => $legacyFarmer->lga,
                    'phone_number' => $legacyFarmer->phone_number,
                ],
            ),

            'placed_at' => $this->placed_at,
            'confirmed_at' => $this->confirmed_at,
            'processing_at' => $this->processing_at,
            'out_for_delivery_at' => $this->out_for_delivery_at,
            'deliver_by' => $this->deliver_by,
            'delivered_at' => $this->delivered_at,
            'cancelled_at' => $this->cancelled_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}