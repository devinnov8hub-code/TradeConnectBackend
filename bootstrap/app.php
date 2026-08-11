<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // API-only app — never redirect guests to a web login route.
        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

        $exceptions->render(function (UnauthorizedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Unauthenticated.'], 401);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Forbidden.',
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Forbidden.',
            ], 403);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            if ($previous instanceof ModelNotFoundException) {
                $message = match (class_basename($previous->getModel())) {
                    'Farmer' => 'Farmer not found.',
                    'Category' => 'Category not found.',
                    'Produce' => 'Produce not found.',
                    'Listing' => 'Listing not found.',
                    'Order' => 'Order not found.',
                    'Dispute' => 'Dispute not found.',
                    'User' => 'User not found.',
                    default => 'Resource not found.',
                };
            } else {
                $message = apiResourceNotFoundMessage($request) ?? 'Resource not found.';
            }

            return response()->json(['message' => $message], 404);
        });
    })->create();

if (! function_exists('apiResourceNotFoundMessage')) {
    function apiResourceNotFoundMessage(Request $request): ?string
    {
        if (preg_match('#api/v1/admin/listings/\d+#', $request->path())) {
            return 'Listing not found.';
        }

        if (preg_match('#api/v1/admin/farmers(?:/|$)#', $request->path())) {
            return 'Farmer not found.';
        }

        if (preg_match('#api/v1/admin/categories(?:/|$)#', $request->path())) {
            return 'Category not found.';
        }

        if (preg_match('#api/v1/admin/produce(?:/|$)#', $request->path())) {
            return 'Produce not found.';
        }

        if (preg_match('#api/v1/orders(?:/|$)#', $request->path())) {
            return 'Order not found.';
        }

        if (preg_match('#api/v1/admin/orders(?:/|$)#', $request->path())) {
            return 'Order not found.';
        }

        if (preg_match('#api/v1/admin/users(?:/|$)#', $request->path())) {
            return 'User not found.';
        }

        if (preg_match('#api/v1/admin/buyers(?:/|$)#', $request->path())) {
            return 'Buyer not found.';
        }

        if (preg_match('#api/v1/(?:admin/)?disputes(?:/|$)#', $request->path())) {
            return 'Dispute not found.';
        }

        return null;
    }
}
