<?php

namespace App\Http\Requests\Order;

use App\Enums\DeliveryMethod;
use App\Enums\ListingPublicationStatus;
use App\Enums\ListingStatus;
use App\Http\Requests\ApiFormRequest;
use App\Models\Listing;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'items.*.listing_id' => [
                'required',
                'integer',
                'distinct',
                'exists:listings,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            /*
             * Checkout delivery information.
             */
            'delivery_method' => [
                'required',
                Rule::enum(
                    DeliveryMethod::class
                ),
            ],

            'delivery_name' => [
                'required',
                'string',
                'max:255',
            ],

            'delivery_phone' => [
                'required',
                'string',
                'max:30',
            ],

            'delivery_state' => [
                'required',
                'string',
                'max:100',
            ],

            'delivery_lga' => [
                'required',
                'string',
                'max:100',
            ],

            'delivery_address' => [
                'required',
                'string',
                'max:1000',
            ],

            'delivery_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
             * Server-owned order values.
             */
            'listing_id' => [
                'prohibited',
            ],

            'quantity' => [
                'prohibited',
            ],

            'subtotal' => [
                'prohibited',
            ],

            'delivery_fee' => [
                'prohibited',
            ],

            'total' => [
                'prohibited',
            ],

            'status' => [
                'prohibited',
            ],

            'payment_status' => [
                'prohibited',
            ],

            'items.*.unit_price' => [
                'prohibited',
            ],

            'items.*.discount_amount' => [
                'prohibited',
            ],

            'items.*.line_total' => [
                'prohibited',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' =>
                'At least one order item is required.',

            'items.array' =>
                'Items must be an array.',

            'items.min' =>
                'At least one order item is required.',

            'items.max' =>
                'An order cannot contain more than 50 items.',

            'items.*.listing_id.required' =>
                'Listing is required for each order item.',

            'items.*.listing_id.exists' =>
                'One or more listings could not be found.',

            'items.*.listing_id.distinct' =>
                'The same listing cannot appear more than once in an order.',

            'items.*.quantity.required' =>
                'Quantity is required for each order item.',

            'items.*.quantity.min' =>
                'Quantity must be at least 1.',

            'delivery_method.required' =>
                'Delivery method is required.',

            'delivery_method.enum' =>
                'Delivery method must be standard or express.',

            'delivery_name.required' =>
                'Delivery name is required.',

            'delivery_phone.required' =>
                'Delivery phone number is required.',

            'delivery_state.required' =>
                'Delivery state is required.',

            'delivery_lga.required' =>
                'Delivery LGA is required.',

            'delivery_address.required' =>
                'Delivery address is required.',

            'listing_id.prohibited' =>
                'Use the items array to create an order.',

            'quantity.prohibited' =>
                'Use the items array to create an order.',

            'subtotal.prohibited' =>
                'Subtotal is calculated by the server.',

            'delivery_fee.prohibited' =>
                'Delivery fee is calculated by the server.',

            'total.prohibited' =>
                'Total is calculated by the server.',

            'status.prohibited' =>
                'Order status cannot be assigned by the client.',

            'payment_status.prohibited' =>
                'Payment status cannot be assigned by the client.',

            'items.*.unit_price.prohibited' =>
                'Unit price is calculated by the server.',

            'items.*.discount_amount.prohibited' =>
                'Discount amount is calculated by the server.',

            'items.*.line_total.prohibited' =>
                'Line total is calculated by the server.',
        ];
    }

    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                /*
                 * Do not perform database-dependent
                 * validation if the item structure itself
                 * is already invalid.
                 */
                if (
                    $validator
                        ->errors()
                        ->has('items')
                ) {
                    return;
                }

                $items = collect(
                    $this->input(
                        'items',
                        []
                    )
                );

                /*
                 * Individual wildcard validation errors
                 * can leave an item incomplete.
                 */
                if (
                    $items->contains(
                        fn ($item) =>
                            ! is_array($item)
                            || ! isset(
                                $item[
                                    'listing_id'
                                ]
                            )
                            || ! isset(
                                $item[
                                    'quantity'
                                ]
                            )
                    )
                ) {
                    return;
                }

                $listingIds = $items
                    ->pluck(
                        'listing_id'
                    )
                    ->map(
                        fn ($id) =>
                            (int) $id
                    )
                    ->unique()
                    ->values();

                $listings = Listing::query()
                    ->whereIn(
                        'id',
                        $listingIds
                    )
                    ->get()
                    ->keyBy('id');

                foreach (
                    $items
                    as $index => $item
                ) {
                    $listing =
                        $listings->get(
                            (int) $item[
                                'listing_id'
                            ]
                        );

                    if (! $listing) {
                        continue;
                    }

                    $quantity =
                        (int) $item[
                            'quantity'
                        ];

                    /*
                     * Both states are checked while the
                     * legacy status field remains in the
                     * application.
                     */
                    if (
                        $listing
                            ->publication_status
                        !== ListingPublicationStatus::Live
                        || $listing->status
                        !== ListingStatus::Active
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "items.{$index}.listing_id",
                                'This listing is not available.'
                            );

                        continue;
                    }

                    /*
                     * A live listing may be visible before
                     * its harvest/availability date, but it
                     * cannot be ordered yet.
                     */
                    if (
                        $listing->available_from
                        !== null
                        && $listing
                            ->available_from
                            ->gt(
                                now()
                                    ->startOfDay()
                            )
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "items.{$index}.listing_id",
                                'This listing is not available for ordering yet.'
                            );

                        continue;
                    }

                    $minimumQuantity =
                        max(
                            1,
                            (int) ceil(
                                (float) $listing
                                    ->minimum_order_quantity
                            )
                        );

                    if (
                        $quantity
                        < $minimumQuantity
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "items.{$index}.quantity",
                                "Minimum order quantity for this listing is {$minimumQuantity}."
                            );
                    }

                    if (
                        $listing->stock
                        < $quantity
                    ) {
                        $validator
                            ->errors()
                            ->add(
                                "items.{$index}.quantity",
                                'Insufficient stock for this listing.'
                            );
                    }
                }
            }
        );
    }
}