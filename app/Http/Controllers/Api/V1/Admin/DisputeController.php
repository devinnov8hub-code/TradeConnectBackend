<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDisputeStatusRequest;
use App\Http\Requests\Dispute\StoreDisputeMessageRequest;
use App\Http\Resources\DisputeMessageResource;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use Illuminate\Http\JsonResponse;

class DisputeController extends Controller
{
    public function index(): JsonResponse
    {
        $disputes = Dispute::query()
            ->with(['user', 'order.listing.produce'])
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => DisputeResource::collection($disputes),
        ]);
    }

    public function show(Dispute $dispute): JsonResponse
    {
        $dispute->load([
            'user',
            'order.listing.produce',
            'messages' => fn ($query) => $query->with('user')->orderBy('created_at'),
        ])->loadCount('messages');

        return response()->json([
            'data' => new DisputeResource($dispute),
        ]);
    }

    public function storeMessage(StoreDisputeMessageRequest $request, Dispute $dispute): JsonResponse
    {
        if (! $dispute->isOpen()) {
            return response()->json([
                'message' => 'Messages can only be sent on open disputes.',
            ], 422);
        }

        $message = $dispute->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('message'),
        ]);

        $message->load('user');

        return response()->json([
            'data' => new DisputeMessageResource($message),
        ], 201);
    }

    public function update(UpdateDisputeStatusRequest $request, Dispute $dispute): JsonResponse
    {
        $dispute->update([
            'status' => $request->validated('status'),
        ]);

        $dispute->load([
            'user',
            'order.listing.produce',
            'messages' => fn ($query) => $query->with('user')->orderBy('created_at'),
        ])->loadCount('messages');

        return response()->json([
            'data' => new DisputeResource($dispute),
        ]);
    }
}
