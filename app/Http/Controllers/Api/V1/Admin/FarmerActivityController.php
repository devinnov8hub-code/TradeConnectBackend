<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexFarmerActivityRequest;
use App\Models\Farmer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FarmerActivityController extends Controller
{
    public function index(
        IndexFarmerActivityRequest $request,
        Farmer $farmer
    ): JsonResponse {
        $validated = $request->validated();

        $limit = (int) (
            $validated['limit']
            ?? 50
        );

        $search = trim(
            (string) (
                $validated['search']
                ?? ''
            )
        );

        $activities = collect()
            ->concat(
                $this->farmerActivities(
                    $farmer
                )
            )
            ->concat(
                $this->listingActivities(
                    $farmer,
                    $limit
                )
            )
            ->concat(
                $this->orderActivities(
                    $farmer,
                    $limit
                )
            )
            ->concat(
                $this->disputeActivities(
                    $farmer,
                    $limit
                )
            );

        if ($search !== '') {
            $needle = mb_strtolower(
                $search
            );

            $activities =
                $activities->filter(
                    function (
                        array $activity
                    ) use ($needle): bool {
                        $encoded =
                            json_encode(
                                $activity,
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                            );

                        return str_contains(
                            mb_strtolower(
                                $encoded ?: ''
                            ),
                            $needle
                        );
                    }
                );
        }

        $activities =
            $activities
                ->sortByDesc(
                    '_sort_at'
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
                            $activity['_sort_at']
                        );

                        return $activity;
                    }
                );

        return response()->json([
            'data' =>
                $activities,
        ]);
    }

    private function farmerActivities(
        Farmer $farmer
    ): Collection {
        if (
            $farmer->created_at === null
        ) {
            return collect();
        }

        return collect([
            $this->activity([
                'id' =>
                    'farmer-created:'
                    .$farmer->id,

                'type' =>
                    'farmer',

                'action' =>
                    'created',

                'title' =>
                    'Farmer profile created',

                'description' =>
                    $farmer->name
                    .' was added as a farmer.',

                'status' =>
                    $this->enumValue(
                        $farmer->status
                    ),

                'actor' =>
                    null,

                'entity' => [
                    'type' =>
                        'farmer',

                    'id' =>
                        (int) $farmer->id,

                    'code' =>
                        $farmer->farmer_code,

                    'url' =>
                        '/api/v1/admin/farmers/'
                        .$farmer->id,
                ],

                'meta' => [
                    'name' =>
                        $farmer->name,

                    'verification_status' =>
                        $this->enumValue(
                            $farmer
                                ->verification_status
                        ),
                ],

                'occurred_at' =>
                    $farmer->created_at,
            ]),
        ]);
    }

    private function listingActivities(
        Farmer $farmer,
        int $limit
    ): Collection {
        return DB::table(
            'listings as listing'
        )
            ->join(
                'produce as produce',
                'produce.id',
                '=',
                'listing.produce_id'
            )
            ->where(
                'listing.farmer_id',
                $farmer->id
            )
            ->select([
                'listing.id',
                'listing.publication_status',
                'listing.created_at',
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
                ) use ($farmer): array {
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
                            .$row->produce_name,

                        'description' =>
                            $farmer->name
                            .' added a '
                            .$row->produce_name
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
                                (int) $row->id,

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
                                    $farmer->id,

                            'farmer_name' =>
                                $farmer->name,
                        ],

                        'occurred_at' =>
                            $row->created_at,
                    ]);
                }
            );
    }

    private function orderActivities(
        Farmer $farmer,
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
            ->whereExists(
                function (
                    $query
                ) use ($farmer): void {
                    $query
                        ->selectRaw('1')
                        ->from(
                            'order_items as farmer_item'
                        )
                        ->whereColumn(
                            'farmer_item.order_id',
                            'event.order_id'
                        )
                        ->where(
                            'farmer_item.farmer_id',
                            $farmer->id
                        );
                }
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
                ) use ($farmer): array {
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
                            'farmer_id' =>
                                (int)
                                    $farmer->id,

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
        Farmer $farmer,
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
                ->where(
                    function (
                        $query
                    ) use ($farmer): void {
                        $query
                            ->where(
                                function (
                                    $itemSpecific
                                ) use ($farmer): void {
                                    $itemSpecific
                                        ->whereNotNull(
                                            'dispute.order_item_id'
                                        )
                                        ->whereExists(
                                            function (
                                                $subquery
                                            ) use ($farmer): void {
                                                $subquery
                                                    ->selectRaw('1')
                                                    ->from(
                                                        'order_items as affected_item'
                                                    )
                                                    ->whereColumn(
                                                        'affected_item.id',
                                                        'dispute.order_item_id'
                                                    )
                                                    ->where(
                                                        'affected_item.farmer_id',
                                                        $farmer->id
                                                    );
                                            }
                                        );
                                }
                            )
                            ->orWhere(
                                function (
                                    $orderWide
                                ) use ($farmer): void {
                                    $orderWide
                                        ->whereNull(
                                            'dispute.order_item_id'
                                        )
                                        ->whereExists(
                                            function (
                                                $subquery
                                            ) use ($farmer): void {
                                                $subquery
                                                    ->selectRaw('1')
                                                    ->from(
                                                        'order_items as dispute_item'
                                                    )
                                                    ->whereColumn(
                                                        'dispute_item.order_id',
                                                        'dispute.order_id'
                                                    )
                                                    ->where(
                                                        'dispute_item.farmer_id',
                                                        $farmer->id
                                                    );
                                            }
                                        );
                                }
                            );
                    }
                )
                ->select([
                    'dispute.id',
                    'dispute.order_id',
                    'dispute.order_item_id',
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
                ) use ($farmer): array {
                    $events = [];

                    $orderNumber =
                        $this->orderNumber(
                            $row->order_number,
                            (int)
                                $row->order_id
                        );

                    $baseMeta = [
                        'farmer_id' =>
                            (int)
                                $farmer->id,

                        'order_id' =>
                            (int)
                                $row->order_id,

                        'order_number' =>
                            $orderNumber,

                        'order_item_id' =>
                            $row->order_item_id
                                !== null
                                    ? (int)
                                        $row
                                            ->order_item_id
                                    : null,
                    ];

                    $openedAt =
                        $row->under_review_at
                        ?? $row->created_at;

                    if ($openedAt) {
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
                                    .$row->subject,

                                'description' =>
                                    $row->buyer_name
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
                                            $row->id,

                                    'url' =>
                                        '/api/v1/admin/disputes/'
                                        .$row->id,
                                ],

                                'meta' =>
                                    $baseMeta,

                                'occurred_at' =>
                                    $openedAt,
                            ]);
                    }

                    if ($row->resolved_at) {
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
                                    .$row->subject,

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
                                            $row->id,

                                    'url' =>
                                        '/api/v1/admin/disputes/'
                                        .$row->id,
                                ],

                                'meta' =>
                                    $baseMeta,

                                'occurred_at' =>
                                    $row
                                        ->resolved_at,
                            ]);
                    }

                    if ($row->closed_at) {
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
                                    .$row->subject,

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
                                            $row->id,

                                    'url' =>
                                        '/api/v1/admin/disputes/'
                                        .$row->id,
                                ],

                                'meta' =>
                                    $baseMeta,

                                'occurred_at' =>
                                    $row
                                        ->closed_at,
                            ]);
                    }

                    return $events;
                }
            );
    }

    private function activity(
        array $activity
    ): array {
        $occurredAt =
            Carbon::parse(
                $activity['occurred_at']
            );

        $activity['occurred_at'] =
            $occurredAt
                ->toISOString();

        $activity['_sort_at'] =
            $occurredAt
                ->getTimestamp();

        return $activity;
    }

    private function humanize(
        ?string $value
    ): string {
        if ($value === null) {
            return 'unknown';
        }

        return ucfirst(
            str_replace(
                '_',
                ' ',
                $value
            )
        );
    }

    private function orderNumber(
        ?string $orderNumber,
        int $orderId
    ): string {
        return $orderNumber
            ?: 'Order #'.$orderId;
    }

    private function enumValue(
        mixed $value
    ): mixed {
        return $value instanceof \BackedEnum
            ? $value->value
            : $value;
    }
}