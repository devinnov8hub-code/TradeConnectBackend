<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dispute\StoreDisputeMessageRequest;
use App\Http\Requests\Dispute\StoreDisputeRequest;
use App\Http\Resources\DisputeMessageResource;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $disputes = $request->user()
            ->disputes()
            ->with(['order.listing.produce', 'user'])
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => DisputeResource::collection($disputes),
        ]);
    }

    public function store(StoreDisputeRequest $request): JsonResponse
    {
        $order = Order::query()->findOrFail($request->validated('order_id'));

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        if ($order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages([
                'order_id' => ['Cancelled orders cannot be disputed.'],
            ]);
        }

        if ($order->dispute()->exists()) {
            throw ValidationException::withMessages([
                'order_id' => ['A dispute already exists for this order.'],
            ]);
        }

        $dispute = DB::transaction(function () use ($request, $order) {
            $dispute = Dispute::create([
                'order_id' => $order->id,
                'user_id' => $request->user()->id,
                'subject' => $request->validated('subject'),
                'status' => DisputeStatus::Open,
            ]);

            $dispute->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $request->validated('message'),
            ]);

            return $dispute->fresh([
                'user',
                'order.listing.produce',
                'messages.user',
            ]);
        });

        return response()->json([
            'data' => new DisputeResource($dispute->loadCount('messages')),
        ], 201);
    }

    public function show(Request $request, Dispute $dispute): JsonResponse
    {
        if ($dispute->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Dispute not found.'], 404);
        }

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
        if ($dispute->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Dispute not found.'], 404);
        }

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
}
