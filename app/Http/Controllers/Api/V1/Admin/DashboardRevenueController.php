<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexDashboardRevenueRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardRevenueController extends Controller
{
    public function __invoke(
        IndexDashboardRevenueRequest $request
    ): JsonResponse {
        $period =
            $request->validated(
                'period',
                'month'
            )
            ?? 'month';

$farmerId = $request->filled('farmer_id')
    ? (int) $request->validated('farmer_id')
    : null;

        [
            $start,
            $end,
            $previousStart,
            $previousEnd,
        ] = $this->ranges(
            $period
        );

        /*
         * Revenue is intentionally derived from paid
         * order-item snapshots.
         *
         * This is gross paid marketplace revenue attributable
         * to produce/order items. It is NOT being represented
         * as a farmer payout ledger.
         *
         * paid_at is the authoritative date for charting.
         * Paid records without paid_at are not assigned an
         * invented historical date.
         */
        $rows =
            DB::table(
                'order_items as item'
            )
                ->join(
                    'orders as orders',
                    'orders.id',
                    '=',
                    'item.order_id'
                )
                ->where(
                    'orders.payment_status',
                    PaymentStatus::Paid
                        ->value
                )
                ->whereNotNull(
                    'orders.paid_at'
                )
                ->whereBetween(
                    'orders.paid_at',
                    [
                        $previousStart,
                        $end,
                    ]
                )
                ->when(
                    $farmerId !== null,
                    fn ($query) =>
                        $query->where(
                            'item.farmer_id',
                            $farmerId
                        )
                )
                ->select([
                    'item.id',
                    'item.order_id',
                    'item.farmer_id',
                    'item.line_total',
                    'orders.paid_at',
                ])
                ->get();

        $currentRows =
            $this->rowsWithin(
                $rows,
                $start,
                $end
            );

        $previousRows =
            $this->rowsWithin(
                $rows,
                $previousStart,
                $previousEnd
            );

        $currentRevenue =
            $this->sumRevenue(
                $currentRows
            );

        $previousRevenue =
            $this->sumRevenue(
                $previousRows
            );

        $series =
            $this->series(
                $period,
                $start,
                $end,
                $currentRows
            );

        $unallocated =
            $this->unallocatedPaidRevenue(
                $farmerId
            );

        return response()->json([
            'data' => [
                /*
                 * Explicitly identify what the chart means.
                 *
                 * This prevents the frontend from labelling
                 * gross paid sales as actual payouts.
                 */
                'metric' =>
                    'gross_paid_order_item_revenue',

                'period' =>
                    $period,

                'farmer_id' =>
                    $farmerId,

                'range' => [
                    'start' =>
                        $start
                            ->toDateString(),

                    'end' =>
                        $end
                            ->toDateString(),

                    'previous_start' =>
                        $previousStart
                            ->toDateString(),

                    'previous_end' =>
                        $previousEnd
                            ->toDateString(),
                ],

                'summary' => [
                    'revenue' =>
                        $currentRevenue,

                    'previous_period_revenue' =>
                        $previousRevenue,

                    'change_percent' =>
                        $this->changePercent(
                            $currentRevenue,
                            $previousRevenue
                        ),

                    /*
                     * Distinct parent orders are counted here.
                     *
                     * A multi-farmer parent order therefore
                     * remains one order even when it contributes
                     * several order-item rows to revenue.
                     */
                    'paid_orders' =>
                        $currentRows
                            ->pluck(
                                'order_id'
                            )
                            ->unique()
                            ->count(),

                    'paid_order_items' =>
                        $currentRows
                            ->count(),

                    /*
                     * These records are known to be paid but
                     * have no reliable paid_at timestamp.
                     *
                     * They are reported separately instead of
                     * being placed into an invented chart date.
                     */
                    'unallocated_paid_revenue' =>
                        $unallocated[
                            'revenue'
                        ],

                    'unallocated_paid_order_items' =>
                        $unallocated[
                            'items'
                        ],
                ],

                'series' =>
                    $series,
            ],
        ]);
    }

    private function ranges(
        string $period
    ): array {
        $now =
            now();

        return match (
            $period
        ) {
            /*
             * Current calendar week.
             */
            'week' => [
                $now
                    ->copy()
                    ->startOfWeek()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->endOfWeek()
                    ->endOfDay(),

                $now
                    ->copy()
                    ->subWeek()
                    ->startOfWeek()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->subWeek()
                    ->endOfWeek()
                    ->endOfDay(),
            ],

            /*
             * Current calendar year.
             */
            'year' => [
                $now
                    ->copy()
                    ->startOfYear()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->endOfYear()
                    ->endOfDay(),

                $now
                    ->copy()
                    ->subYear()
                    ->startOfYear()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->subYear()
                    ->endOfYear()
                    ->endOfDay(),
            ],

            /*
             * Default: current calendar month.
             */
            default => [
                $now
                    ->copy()
                    ->startOfMonth()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->endOfMonth()
                    ->endOfDay(),

                $now
                    ->copy()
                    ->subMonthNoOverflow()
                    ->startOfMonth()
                    ->startOfDay(),

                $now
                    ->copy()
                    ->subMonthNoOverflow()
                    ->endOfMonth()
                    ->endOfDay(),
            ],
        };
    }

    private function rowsWithin(
        Collection $rows,
        Carbon $start,
        Carbon $end
    ): Collection {
        return $rows
            ->filter(
                function (
                    object $row
                ) use (
                    $start,
                    $end
                ): bool {
                    $paidAt =
                        Carbon::parse(
                            $row
                                ->paid_at
                        );

                    return $paidAt
                        ->betweenIncluded(
                            $start,
                            $end
                        );
                }
            )
            ->values();
    }

    private function series(
        string $period,
        Carbon $start,
        Carbon $end,
        Collection $rows
    ): array {
        if (
            $period === 'year'
        ) {
            return $this
                ->monthlySeries(
                    $start,
                    $end,
                    $rows
                );
        }

        return $this
            ->dailySeries(
                $start,
                $end,
                $rows
            );
    }

    private function dailySeries(
        Carbon $start,
        Carbon $end,
        Collection $rows
    ): array {
        $totals = [];

        foreach (
            $rows
            as $row
        ) {
            $key =
                Carbon::parse(
                    $row->paid_at
                )
                    ->toDateString();

            $totals[$key] =
                bcadd(
                    $totals[$key]
                    ?? '0.00',
                    (string)
                        $row->line_total,
                    2
                );
        }

        $series = [];

        $cursor =
            $start
                ->copy()
                ->startOfDay();

        $lastDay =
            $end
                ->copy()
                ->startOfDay();

        while (
            $cursor->lte(
                $lastDay
            )
        ) {
            $key =
                $cursor
                    ->toDateString();

            $series[] = [
                'key' =>
                    $key,

                'label' =>
                    $cursor
                        ->format(
                            'd M'
                        ),

                'revenue' =>
                    $totals[$key]
                    ?? '0.00',
            ];

            $cursor
                ->addDay();
        }

        return $series;
    }

    private function monthlySeries(
        Carbon $start,
        Carbon $end,
        Collection $rows
    ): array {
        $totals = [];

        foreach (
            $rows
            as $row
        ) {
            $key =
                Carbon::parse(
                    $row->paid_at
                )
                    ->format(
                        'Y-m'
                    );

            $totals[$key] =
                bcadd(
                    $totals[$key]
                    ?? '0.00',
                    (string)
                        $row->line_total,
                    2
                );
        }

        $series = [];

        $cursor =
            $start
                ->copy()
                ->startOfMonth();

        $lastMonth =
            $end
                ->copy()
                ->startOfMonth();

        while (
            $cursor->lte(
                $lastMonth
            )
        ) {
            $key =
                $cursor
                    ->format(
                        'Y-m'
                    );

            $series[] = [
                'key' =>
                    $key,

                'label' =>
                    $cursor
                        ->format(
                            'M'
                        ),

                'revenue' =>
                    $totals[$key]
                    ?? '0.00',
            ];

            $cursor
                ->addMonth();
        }

        return $series;
    }

    private function sumRevenue(
        Collection $rows
    ): string {
        return $rows
            ->reduce(
                fn (
                    string $total,
                    object $row
                ): string =>
                    bcadd(
                        $total,
                        (string)
                            $row
                                ->line_total,
                        2
                    ),
                '0.00'
            );
    }

    private function changePercent(
        string $current,
        string $previous
    ): ?float {
        /*
         * There is no meaningful percentage change from
         * zero to a positive number.
         *
         * Returning null is more honest than inventing
         * "100%" or infinity.
         */
        if (
            bccomp(
                $previous,
                '0.00',
                2
            ) === 0
        ) {
            return bccomp(
                $current,
                '0.00',
                2
            ) === 0
                ? 0.0
                : null;
        }

        $difference =
            bcsub(
                $current,
                $previous,
                2
            );

        $percentage =
            bcmul(
                bcdiv(
                    $difference,
                    $previous,
                    6
                ),
                '100',
                4
            );

        return round(
            (float)
                $percentage,
            2
        );
    }

    private function unallocatedPaidRevenue(
        ?int $farmerId
    ): array {
        $query =
            DB::table(
                'order_items as item'
            )
                ->join(
                    'orders as orders',
                    'orders.id',
                    '=',
                    'item.order_id'
                )
                ->where(
                    'orders.payment_status',
                    PaymentStatus::Paid
                        ->value
                )
                ->whereNull(
                    'orders.paid_at'
                )
                ->when(
                    $farmerId !== null,
                    fn ($query) =>
                        $query->where(
                            'item.farmer_id',
                            $farmerId
                        )
                );

        $rows =
            $query
                ->select([
                    'item.line_total',
                ])
                ->get();

        return [
            'revenue' =>
                $this->sumRevenue(
                    $rows
                ),

            'items' =>
                $rows
                    ->count(),
        ];
    }
}