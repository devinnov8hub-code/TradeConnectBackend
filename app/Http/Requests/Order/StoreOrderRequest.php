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
    private bool $legacySingleItemPayload = false;

    protected function prepareForValidation(): void
    {
        $input = $this->all();

        $hasItems = array_key_exists(
            'items',
            $input
        );

        $hasLegacyListing = array_key_exists(
            'listing_id',
            $input
        );

        $hasLegacyQuantity = array_key_exists(
            'quantity',
            $input
        );

        /*
         * Original v1 checkout contract:
         *
         * {
         *     "listing_id": 1,
         *     "quantity": 2
         * }
         *
         * Keep accepting that request shape, but normalise it
         * into items[] so the application still uses the modern
         * parent-order + order-items architecture internally.
         *
         * If items[] is already present, this compatibility path
         * does not run. A request that mixes modern and legacy
         * checkout fields therefore remains invalid.
         */
        if (
            ! $hasItems
            && (
                $hasLegacyListing
                || $hasLegacyQuantity
            )
        ) {
            $this->legacySingleItemPayload = true;

            $input['items'] = [
                [
                    'listing_id' =>
                        $input['listing_id']
                        ?? null,

                    'quantity' =>
                        $input['quantity']
                        ?? null,
                ],
            ];

            /*
             * Delivery fields did not exist in the original
             * order-creation contract. The additive order
             * migration made these columns nullable, so do not
             * invent delivery history for legacy requests.
             *
             * Explicit delivery values supplied by a legacy
             * caller are still validated and stored.
             */
            foreach (
                [
                    'delivery_method',
                    'delivery_name',
                    'delivery_phone',
                    'delivery_state',
                    'delivery_lga',
                    'delivery_address',
                    'delivery_notes',
                ] as $field
            ) {
                if (
                    ! array_key_exists(
                        $field,
                        $input
                    )
                ) {
                    $input[$field] = null;
                }
            }

            $this->replace($input);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deliveryPresenceRule =
            $this->legacySingleItemPayload
                ? 'nullable'
                : 'required';

        $legacyListingRules =
            $this->legacySingleItemPayload
                ? [
                    'required',
                    'integer',
                    'exists:listings,id',
                ]
                : [
                    'prohibited',
                ];

        $legacyQuantityRules =
            $this->legacySingleItemPayload
                ? [
                    'required',
                    'integer',
                    'min:1',
                ]
                : [
                    'prohibited',
                ];

        $itemListingRules =
            $this->legacySingleItemPayload
                ? []
                : [
                    'required',
                    'integer',
                    'distinct',
                    'exists:listings,id',
                ];

        $itemQuantityRules =
            $this->legacySingleItemPayload
                ? []
                : [
                    'required',
                    'integer',
                    'min:1',
                ];

        return [
            /*
             * Original single-item request fields.
             *
             * They are accepted only when items[] was absent and
             * prepareForValidation() identified a legacy request.
             */
            'listing_id' =>
                $legacyListingRules,

            'quantity' =>
                $legacyQuantityRules,

            'items' => [
                'required',
                'array',
                'min:1',
                'max:50',
            ],

            'items.*.listing_id' =>
                $itemListingRules,

            'items.*.quantity' =>
                $itemQuantityRules,

            /*
             * Modern checkout requires a complete delivery
             * snapshot. Legacy checkout may omit it.
             */
            'delivery_method' => [
                $deliveryPresenceRule,
                Rule::enum(
                    DeliveryMethod::class
                ),
            ],

            'delivery_name' => [
                $deliveryPresenceRule,
                'string',
                'max:255',
            ],

            'delivery_phone' => [
                $deliveryPresenceRule,
                'string',
                'max:30',
            ],

            'delivery_state' => [
                $deliveryPresenceRule,
                'string',
                'max:100',
            ],

            'delivery_lga' => [
                $deliveryPresenceRule,
                'string',
                'max:100',
            ],

            'delivery_address' => [
                $deliveryPresenceRule,
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
            /*
             * Original v1 validation messages.
             */
            'listing_id.required' =>
                'Listing is required.',

            'listing_id.exists' =>
                'Listing not found.',

            'quantity.required' =>
                'Quantity is required.',

            'quantity.min' =>
                'Quantity must be at least 1.',

            /*
             * Modern items[] validation messages.
             */
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
                 * Preserve original validation-field names for
                 * the old listing_id / quantity contract.
                 */
                if (
                    $this->legacySingleItemPayload
                    && (
                        $validator
                            ->errors()
                            ->has('listing_id')
                        || $validator
                            ->errors()
                            ->has('quantity')
                    )
                ) {
                    return;
                }

                /*
                 * Do not perform database-dependent
                 * validation if the modern item structure
                 * itself is invalid.
                 */
                if (
                    ! $this->legacySingleItemPayload
                    && $validator
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
                 * Individual wildcard validation errors can
                 * leave a modern item incomplete.
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

                    $listingErrorKey =
                        $this->legacySingleItemPayload
                            ? 'listing_id'
                            : "items.{$index}.listing_id";

                    $quantityErrorKey =
                        $this->legacySingleItemPayload
                            ? 'quantity'
                            : "items.{$index}.quantity";

                    /*
                     * Both publication_status and legacy
                     * status continue to protect checkout.
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
                                $listingErrorKey,
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
                                $listingErrorKey,
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
                                $quantityErrorKey,
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
                                $quantityErrorKey,
                                'Insufficient stock for this listing.'
                            );
                    }
                }
            }
        );
    }
}