<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexActivityRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityController extends Controller
{
    public function __invoke(
        IndexActivityRequest $request
    ): JsonResponse {
        $limit =
            (int) (
                $request->validated(
                    'limit',
                    10
                )
                ?? 10
            );

        $type =
            $request->validated(
                'type'
            );

        $activities =
            collect();

        if (
            $type === null
            || $type === 'order'
        ) {
            $activities =
                $activities->concat(
                    $this->orderActivities(
                        $limit
                    )
                );
        }

        if (
            $type === null
            || $type === 'dispute'
        ) {
            $activities =
                $activities->concat(
                    $this->disputeActivities(
                        $limit
                    )
                );
        }

        if (
            $type === null
            || $type === 'farmer'
        ) {
            $activities =
                $activities->concat(
                    $this->farmerActivities(
                        $limit
                    )
                );
        }

        if (
            $type === null
            || $type === 'listing'
        ) {
            $activities =
                $activities->concat(
                    $this->listingActivities(
                        $limit
                    )
                );
        }

        if (
            $type === null
            || $type === 'buyer'
        ) {
            $activities =
                $activities->concat(
                    $this->buyerActivities(
                        $limit
                    )
                );
        }

        $activities =
            $activities
                ->sortByDesc(
                    '_sort'
                )
                ->take(
                    $limit
                )
                ->values()
                ->map(
                    function (
                        array $activity
                    ): array {
                        unset(
                            $activity[
                                '_sort'
                            ]
                        );

                        return $activity;
                    }
                );

        return response()->json([
            'data' =>
                $activities
                    ->all(),

            'meta' => [
                'type' =>
                    $type
                    ?? 'all',

                'limit' =>
                    $limit,
            ],
        ]);
    }

    private function orderActivities(
        int $limit
    ): Collection {
        return DB::table(
            'order_status_events as event'
        )
            ->join(
                'orders as orders',
                'orders.id',
                '=',
                'event.order_id'
            )
            ->join(
                'users as buyer',
                'buyer.id',
                '=',
                'orders.user_id'
            )
            ->leftJoin(
                'users as actor',
                'actor.id',
                '=',
                'event.changed_by_user_id'
            )
            ->whereNotNull(
                'event.occurred_at'
            )
            ->select([
                'event.id',
                'event.order_id',
                'event.from_status',
                'event.to_status',
                'event.changed_by_user_id',

                'event.occurred_at',

                'orders.order_number',

                'buyer.id as buyer_id',
                'buyer.name as buyer_name',

                'actor.name as actor_name',
            ])
            ->orderByDesc(
                'event.occurred_at'
            )
            ->orderByDesc(
                'event.id'
            )
            ->limit(
                $limit
            )
            ->get()
            ->map(
                function (
                    object $row
                ): array {
                    $orderNumber =
                        $this->orderNumber(
                            $row->order_number,
                            (int)
                                $row->order_id
                        );

                    $isCreation =
                        $row->from_status
                        === null;

                    $action =
                        $isCreation
                            ? 'created'
                            : 'status_changed';

                    $title =
                        $isCreation
                            ? "Order {$orderNumber} placed"
                            : "Order {$orderNumber} moved to "
                                .$this->humanize(
                                    $row->to_status
                                );

                    $description =
                        $isCreation
                            ? $row->buyer_name
                                .' placed an order.'
                            : 'Order status changed from '
                                .$this->humanize(
                                    $row->from_status
                                )
                                .' to '
                                .$this->humanize(
                                    $row->to_status
                                )
                                .'.';

                    $actor =
                        $row
                            ->changed_by_user_id
                            ? [
                                'id' =>
                                    (int)
                                        $row
                                            ->changed_by_user_id,

                                'name' =>
                                    $row
                                        ->actor_name,
                            ]
                            : null;

                    return $this->activity([
                        'id' =>
                            'order-status:'
                            .$row->id,

                        'type' =>
                            'order',

                        'action' =>
                            $action,

                        'title' =>
                            $title,

                        'description' =>
                            $description,

                        'status' =>
                            $row
                                ->to_status,

                        'actor' =>
                            $actor,

                        'entity' => [
                            'type' =>
                                'order',

                            'id' =>
                                (int)
                                    $row
                                        ->order_id,

                            'code' =>
                                $orderNumber,

                            'url' =>
                                '/api/v1/admin/orders/'
                                .$row
                                    ->order_id,
                        ],

                        'meta' => [
                            'buyer_id' =>
                                (int)
                                    $row
                                        ->buyer_id,

                            'buyer_name' =>
                                $row
                                    ->buyer_name,

                            'from_status' =>
                                $row
                                    ->from_status,

                            'to_status' =>
                                $row
                                    ->to_status,
                        ],

                        'occurred_at' =>
                            $row
                                ->occurred_at,
                    ]);
                }
            );
    }

    private function disputeActivities(
        int $limit
    ): Collection {
        $disputes =
            DB::table(
                'disputes as dispute'
            )
                ->join(
                    'orders as orders',
                    'orders.id',
                    '=',
                    'dispute.order_id'
                )
                ->join(
                    'users as buyer',
                    'buyer.id',
                    '=',
                    'dispute.user_id'
                )
                ->leftJoin(
                    'users as resolver',
                    'resolver.id',
                    '=',
                    'dispute.resolved_by_user_id'
                )
                ->leftJoin(
                    'users as closer',
                    'closer.id',
                    '=',
                    'dispute.closed_by_user_id'
                )
                ->select([
                    'dispute.id',
                    'dispute.order_id',
                    'dispute.subject',
                    'dispute.status',

                    'dispute.under_review_at',
                    'dispute.resolved_at',
                    'dispute.resolved_by_user_id',
                    'dispute.closed_at',
                    'dispute.closed_by_user_id',

                    'dispute.created_at',
                    'dispute.updated_at',

                    'orders.order_number',

                    'buyer.id as buyer_id',
                    'buyer.name as buyer_name',

                    'resolver.name as resolver_name',
                    'closer.name as closer_name',
                ])
                ->orderByDesc(
                    'dispute.updated_at'
                )
                ->limit(
                    $limit
                )
                ->get();

        return $disputes
            ->flatMap(
                function (
                    object $row
                ): array {
                    $events = [];

                    $orderNumber =
                        $this->orderNumber(
                            $row->order_number,
                            (int)
                                $row->order_id
                        );

                    $openedAt =
                        $row
                            ->under_review_at
                        ?? $row
                            ->created_at;

                    if (
                        $openedAt
                    ) {
                        $events[] =
                            $this->activity([
                                'id' =>
                                    'dispute-opened:'
                                    .$row->id,

                                'type' =>
                                    'dispute',

                                'action' =>
                                    'opened',

                                'title' =>
                                    'Dispute opened: '
                                    .$row
                                        ->subject,

                                'description' =>
                                    $row
                                        ->buyer_name
                                    .' opened a dispute for order '
                                    .$orderNumber
                                    .'.',

                                'status' =>
                                    'under_review',

                                'actor' => [
                                    'id' =>
                                        (int)
                                            $row
                                                ->buyer_id,

                                    'name' =>
                                        $row
                                            ->buyer_name,
                                ],

                                'entity' => [
                                    'type' =>
                                        'dispute',

                                    'id' =>
                                        (int)
                                            $row
                                                ->id,

                                    'url' =>
                                        '/api/v1/admin/disputes/'
                                        .$row
                                            ->id,
                                ],

                                'meta' => [
                                    'order_id' =>
                                        (int)
                                            $row
                                                ->order_id,

                                    'order_number' =>
                                        $orderNumber,
                                ],

                                'occurred_at' =>
                                    $openedAt,
                            ]);
                    }

                    if (
                        $row->resolved_at
                    ) {
                        $events[] =
                            $this->activity([
                                'id' =>
                                    'dispute-resolved:'
                                    .$row->id,

                                'type' =>
                                    'dispute',

                                'action' =>
                                    'resolved',

                                'title' =>
                                    'Dispute resolved: '
                                    .$row
                                        ->subject,

                                'description' =>
                                    'The dispute for order '
                                    .$orderNumber
                                    .' was resolved.',

                                'status' =>
                                    'resolved',

                                'actor' =>
                                    $row
                                        ->resolved_by_user_id
                                        ? [
                                            'id' =>
                                                (int)
                                                    $row
                                                        ->resolved_by_user_id,

                                            'name' =>
                                                $row
                                                    ->resolver_name,
                                        ]
                                        : null,

                                'entity' => [
                                    'type' =>
                                        'dispute',

                                    'id' =>
                                        (int)
                                            $row
                                                ->id,

                                    'url' =>
                                        '/api/v1/admin/disputes/'
                                        .$row
                                            ->id,
                                ],

                                'meta' => [
                                    'order_id' =>
                                        (int)
                                            $row
                                                ->order_id,

                                    'order_number' =>
                                        $orderNumber,
                                ],

                                'occurred_at' =>
                                    $row
                                        ->resolved_at,
                            ]);
                    }

                    if (
                        $row->closed_at
                    ) {
                        $events[] =
                            $this->activity([
                                'id' =>
                                    'dispute-closed:'
                                    .$row->id,

                                'type' =>
                                    'dispute',

                                'action' =>
                                    'closed',

                                'title' =>
                                    'Dispute closed: '
                                    .$row
                                        ->subject,

                                'description' =>
                                    'The dispute for order '
                                    .$orderNumber
                                    .' was closed.',

                                'status' =>
                                    'closed',

                                'actor' =>
                                    $row
                                        ->closed_by_user_id
                                        ? [
                                            'id' =>
                                                (int)
                                                    $row
                                                        ->closed_by_user_id,

                                            'name' =>
                                                $row
                                                    ->closer_name,
                                        ]
                                        : null,

                                'entity' => [
                                    'type' =>
                                        'dispute',

                                    'id' =>
                                        (int)
                                            $row
                                                ->id,

                                    'url' =>
                                        '/api/v1/admin/disputes/'
                                        .$row
                                            ->id,
                                ],

                                'meta' => [
                                    'order_id' =>
                                        (int)
                                            $row
                                                ->order_id,

                                    'order_number' =>
                                        $orderNumber,
                                ],

                                'occurred_at' =>
                                    $row
                                        ->closed_at,
                            ]);
                    }

                    return $events;
                }
            );
    }

    private function farmerActivities(
        int $limit
    ): Collection {
        return DB::table(
            'farmers'
        )
            ->select([
                'id',
                'farmer_code',
                'name',
                'status',
                'verification_status',
                'created_at',
            ])
            ->orderByDesc(
                'created_at'
            )
            ->limit(
                $limit
            )
            ->get()
            ->map(
                function (
                    object $row
                ): array {
                    return $this->activity([
                        'id' =>
                            'farmer-created:'
                            .$row->id,

                        'type' =>
                            'farmer',

                        'action' =>
                            'created',

                        'title' =>
                            'Farmer profile created',

                        'description' =>
                            $row->name
                            .' was added as a farmer.',

                        'status' =>
                            $row
                                ->status,

                        'actor' =>
                            null,

                        'entity' => [
                            'type' =>
                                'farmer',

                            'id' =>
                                (int)
                                    $row->id,

                            'code' =>
                                $row
                                    ->farmer_code,

                            'url' =>
                                '/api/v1/admin/farmers/'
                                .$row->id,
                        ],

                        'meta' => [
                            'name' =>
                                $row
                                    ->name,

                            'verification_status' =>
                                $row
                                    ->verification_status,
                        ],

                        'occurred_at' =>
                            $row
                                ->created_at,
                    ]);
                }
            );
    }

    private function listingActivities(
        int $limit
    ): Collection {
        return DB::table(
            'listings as listing'
        )
            ->join(
                'farmers as farmer',
                'farmer.id',
                '=',
                'listing.farmer_id'
            )
            ->join(
                'produce as produce',
                'produce.id',
                '=',
                'listing.produce_id'
            )
            ->select([
                'listing.id',
                'listing.publication_status',
                'listing.created_at',

                'farmer.id as farmer_id',
                'farmer.name as farmer_name',

                'produce.id as produce_id',
                'produce.name as produce_name',
            ])
            ->orderByDesc(
                'listing.created_at'
            )
            ->limit(
                $limit
            )
            ->get()
            ->map(
                function (
                    object $row
                ): array {
                    return $this->activity([
                        'id' =>
                            'listing-created:'
                            .$row->id,

                        'type' =>
                            'listing',

                        'action' =>
                            'created',

                        'title' =>
                            'Listing created: '
                            .$row
                                ->produce_name,

                        'description' =>
                            $row
                                ->farmer_name
                            .' added a '
                            .$row
                                ->produce_name
                            .' listing.',

                        'status' =>
                            $row
                                ->publication_status,

                        'actor' =>
                            null,

                        'entity' => [
                            'type' =>
                                'listing',

                            'id' =>
                                (int)
                                    $row->id,

                            'url' =>
                                '/api/v1/admin/listings/'
                                .$row->id,
                        ],

                        'meta' => [
                            'produce_id' =>
                                (int)
                                    $row
                                        ->produce_id,

                            'produce_name' =>
                                $row
                                    ->produce_name,

                            'farmer_id' =>
                                (int)
                                    $row
                                        ->farmer_id,

                            'farmer_name' =>
                                $row
                                    ->farmer_name,
                        ],

                        'occurred_at' =>
                            $row
                                ->created_at,
                    ]);
                }
            );
    }

    private function buyerActivities(
        int $limit
    ): Collection {
        return DB::table(
            'users'
        )
            ->where(
                'role',
                UserRole::User->value
            )
            ->select([
                'id',
                'account_code',
                'name',
                'email',
                'status',
                'created_at',
            ])
            ->orderByDesc(
                'created_at'
            )
            ->limit(
                $limit
            )
            ->get()
            ->map(
                function (
                    object $row
                ): array {
                    return $this->activity([
                        'id' =>
                            'buyer-created:'
                            .$row->id,

                        'type' =>
                            'buyer',

                        'action' =>
                            'created',

                        'title' =>
                            'Buyer account created',

                        'description' =>
                            $row->name
                            .' joined as a buyer.',

                        'status' =>
                            $row
                                ->status,

                        'actor' =>
                            null,

                        'entity' => [
                            'type' =>
                                'buyer',

                            'id' =>
                                (int)
                                    $row->id,

                            'code' =>
                                $row
                                    ->account_code,

                            'url' =>
                                '/api/v1/admin/buyers/'
                                .$row->id,
                        ],

                        'meta' => [
                            'name' =>
                                $row
                                    ->name,

                            'email' =>
                                $row
                                    ->email,
                        ],

                        'occurred_at' =>
                            $row
                                ->created_at,
                    ]);
                }
            );
    }

    private function activity(
        array $activity
    ): array {
        $occurredAt =
            Carbon::parse(
                $activity[
                    'occurred_at'
                ]
            );

        $activity[
            'occurred_at'
        ] =
            $occurredAt
                ->toISOString();

        $activity[
            '_sort'
        ] =
            $occurredAt
                ->getTimestamp();

        return $activity;
    }

    private function humanize(
        ?string $value
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return 'Unknown';
        }

        return Str::headline(
            $value
        );
    }

    private function orderNumber(
        ?string $orderNumber,
        int $orderId
    ): string {
        return $orderNumber
            ?? 'ORD-'
                .str_pad(
                    (string)
                        $orderId,
                    6,
                    '0',
                    STR_PAD_LEFT
                );
    }
}