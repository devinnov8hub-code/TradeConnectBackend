<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisputeResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        $unreadCount =
            (int) (
                $this->unread_count
                ?? 0
            );

        $legacyProduce =
            null;

        /*
         * Preserve the original order.produce projection for
         * genuine single-item orders.
         *
         * The order-items migration backfilled old single-item
         * orders with one OrderItem while deliberately keeping
         * orders.listing_id. Those migrated orders are still
         * legacy single-item orders and should therefore keep
         * order.produce.
         *
         * Modern multi-item orders continue to omit this legacy
         * projection so we never pretend one product represents
         * the whole order.
         */
        if (
            $this->relationLoaded(
                'order'
            )
            && $this->order
            && $this
                ->order
                ->relationLoaded(
                    'items'
                )
            && $this
                ->order
                ->relationLoaded(
                    'listing'
                )
            && $this
                ->order
                ->listing
            && $this
                ->order
                ->listing
                ->relationLoaded(
                    'produce'
                )
            && $this
                ->order
                ->listing
                ->produce
        ) {
            $items =
                $this
                    ->order
                    ->items;

            $isLegacySingleItem =
                $items->isEmpty()
                || (
                    $items->count() === 1
                    && (int) $items
                        ->first()
                        ->listing_id
                        === (int) $this
                            ->order
                            ->listing_id
                );

            if (
                $isLegacySingleItem
            ) {
                $legacyProduce =
                    $this
                        ->order
                        ->listing
                        ->produce;
            }
        }

        return [
            'id' =>
                $this->id,

            'order_id' =>
                $this->order_id,

            'order_item_id' =>
                $this->order_item_id,

            'user_id' =>
                $this->user_id,

            'subject' =>
                $this->subject,

            /*
             * Canonical v1 field stays backward-compatible.
             *
             * Under-review disputes therefore expose "open"
             * here, matching the original API.
             */
            'status' =>
                $this
                    ->status
                    ->value,

            /*
             * Additive enhanced workflow value for the newer UI.
             */
            'workflow_status' =>
                $this
                    ->status
                    ->workflowValue(),

            'unread_count' =>
                $unreadCount,

            'is_unread' =>
                $unreadCount > 0,

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

                        'account_code' =>
                            $this
                                ->user
                                ->account_code,

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

            'affected_farmer' =>
                $this->when(
                    $this->relationLoaded(
                        'affectedOrderItem'
                    ),
                    function () {
                        $item =
                            $this
                                ->affectedOrderItem;

                        if (
                            ! $item
                            || ! $item
                                ->relationLoaded(
                                    'farmer'
                                )
                            || ! $item
                                ->farmer
                        ) {
                            return null;
                        }

                        return [
                            'id' =>
                                $item
                                    ->farmer
                                    ->id,

                            'farmer_code' =>
                                $item
                                    ->farmer
                                    ->farmer_code,

                            'name' =>
                                $item
                                    ->farmer
                                    ->name,

                            'state' =>
                                $item
                                    ->farmer
                                    ->state,

                            'lga' =>
                                $item
                                    ->farmer
                                    ->lga,
                        ];
                    }
                ),

            'affected_item' =>
                $this->when(
                    $this->relationLoaded(
                        'affectedOrderItem'
                    ),
                    fn () =>
                        $this
                            ->affectedOrderItem
                            ? new OrderItemResource(
                                $this
                                    ->affectedOrderItem
                            )
                            : null
                ),

            'order' =>
                $this->when(
                    $this->relationLoaded(
                        'order'
                    )
                    && $this->order,

                    fn () => [
                        'id' =>
                            $this
                                ->order
                                ->id,

                        'order_number' =>
                            $this
                                ->order
                                ->order_number,

                        'quantity' =>
                            $this
                                ->order
                                ->quantity,

                        'total' =>
                            $this
                                ->order
                                ->total,

                        'status' =>
                            $this
                                ->order
                                ->status
                                ->value,

                        'payment_status' =>
                            $this
                                ->order
                                ->payment_status
                                ?->value,

                        'items' =>
                            $this->when(
                                $this
                                    ->order
                                    ->relationLoaded(
                                        'items'
                                    ),
                                fn () =>
                                    OrderItemResource::collection(
                                        $this
                                            ->order
                                            ->items
                                    )
                            ),

                        /*
                         * Original single-item projection.
                         *
                         * Kept for truly single-item orders,
                         * including migrated legacy orders that
                         * now have exactly one order_items row.
                         */
                        'produce' =>
                            $this->when(
                                $legacyProduce
                                !== null,
                                fn () => [
                                    'id' =>
                                        $legacyProduce
                                            ->id,

                                    'name' =>
                                        $legacyProduce
                                            ->name,

                                    'image_url' =>
                                        $legacyProduce
                                            ->image_url,
                                ]
                            ),
                    ]
                ),

            'last_message' =>
                $this->when(
                    $this->relationLoaded(
                        'lastMessage'
                    ),
                    fn () =>
                        $this
                            ->lastMessage
                            ? new DisputeMessageResource(
                                $this
                                    ->lastMessage
                            )
                            : null
                ),

            'messages' =>
                DisputeMessageResource::collection(
                    $this->whenLoaded(
                        'messages'
                    )
                ),

            'messages_count' =>
                $this->whenCounted(
                    'messages'
                ),

            'under_review_at' =>
                $this
                    ->under_review_at,

            'resolved_at' =>
                $this
                    ->resolved_at,

            'resolved_by' =>
                $this->when(
                    $this->relationLoaded(
                        'resolvedBy'
                    ),
                    fn () =>
                        $this->resolvedBy
                            ? [
                                'id' =>
                                    $this
                                        ->resolvedBy
                                        ->id,

                                'account_code' =>
                                    $this
                                        ->resolvedBy
                                        ->account_code,

                                'name' =>
                                    $this
                                        ->resolvedBy
                                        ->name,

                                'role' =>
                                    $this
                                        ->resolvedBy
                                        ->role
                                        ->value,
                            ]
                            : null
                ),

            'closed_at' =>
                $this
                    ->closed_at,

            'closed_by' =>
                $this->when(
                    $this->relationLoaded(
                        'closedBy'
                    ),
                    fn () =>
                        $this->closedBy
                            ? [
                                'id' =>
                                    $this
                                        ->closedBy
                                        ->id,

                                'account_code' =>
                                    $this
                                        ->closedBy
                                        ->account_code,

                                'name' =>
                                    $this
                                        ->closedBy
                                        ->name,

                                'role' =>
                                    $this
                                        ->closedBy
                                        ->role
                                        ->value,
                            ]
                            : null
                ),

            'resolution_note' =>
                $this
                    ->resolution_note,

            'created_at' =>
                $this->created_at,

            'updated_at' =>
                $this->updated_at,
        ];
    }
}