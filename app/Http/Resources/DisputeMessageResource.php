<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisputeMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dispute_id' => $this->dispute_id,
            'body' => $this->body,
            'sender' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'role' => $this->user->role->value,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
