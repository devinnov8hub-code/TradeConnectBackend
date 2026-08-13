<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = auth('api')->user();

        if (
            $user
            && $user->status
                === UserStatus::Inactive
        ) {
            return response()->json([
                'message' =>
                    'Account is inactive.',
            ], 403);
        }

        return $next($request);
    }
}