<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusEventResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' =>
                $this->id,

            'from_status' =>
                $this->from_status?->value,

            'to_status' =>
                $this->to_status->value,

            'changed_by' =>
                $this->when(
                    $this->relationLoaded(
                        'changedBy'
                    ),
                    function () {
                        if (
                            ! $this->changedBy
                        ) {
                            return null;
                        }

                        return [
                            'id' =>
                                $this
                                    ->changedBy
                                    ->id,

                            'account_code' =>
                                $this
                                    ->changedBy
                                    ->account_code,

                            'name' =>
                                $this
                                    ->changedBy
                                    ->name,

                            'role' =>
                                $this
                                    ->changedBy
                                    ->role
                                    ->value,
                        ];
                    }
                ),

            'note' =>
                $this->note,

            'occurred_at' =>
                $this->occurred_at,
        ];
    }
}