<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisputeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'buyer' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ),
            'order' => $this->when(
                $this->relationLoaded('order') && $this->order,
                fn () => [
                    'id' => $this->order->id,
                    'quantity' => $this->order->quantity,
                    'total' => $this->order->total,
                    'status' => $this->order->status->value,
                    'produce' => $this->when(
                        $this->order->relationLoaded('listing')
                            && $this->order->listing
                            && $this->order->listing->relationLoaded('produce')
                            && $this->order->listing->produce,
                        fn () => [
                            'id' => $this->order->listing->produce->id,
                            'name' => $this->order->listing->produce->name,
                            'image_url' => $this->order->listing->produce->image_url,
                        ],
                    ),
                ],
            ),
            'messages' => DisputeMessageResource::collection($this->whenLoaded('messages')),
            'messages_count' => $this->whenCounted('messages'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
