<?php

use App\Http\Controllers\Api\V1\Admin\ActivityController;
use App\Http\Controllers\Api\V1\Admin\BuyerController;
use App\Http\Controllers\Api\V1\Admin\CategoryController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\DashboardRevenueController;
use App\Http\Controllers\Api\V1\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Api\V1\Admin\FarmerController;
use App\Http\Controllers\Api\V1\Admin\ListingController as AdminListingController;
use App\Http\Controllers\Api\V1\Admin\ListingImageController as AdminListingImageController;
use App\Http\Controllers\Api\V1\Admin\NotificationController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\ProduceController;
use App\Http\Controllers\Api\V1\MarketplaceSummaryController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AuthOtpController;
use App\Http\Controllers\Api\V1\DisputeController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => config('app.name'),
        ]);
    });

    Route::post(
        '/register',
        [AuthController::class, 'register']
    );

    Route::get(
    'marketplace/summary',
    MarketplaceSummaryController::class
);

    Route::post(
        '/login',
        [AuthController::class, 'login']
    );

    /*
     * Password recovery: issue OTP, verify OTP, then consume
     * the short-lived reset token.
     */
    Route::middleware('throttle:6,1')
        ->group(function () {
            Route::post(
                'password/forgot',
                [
                    AuthOtpController::class,
                    'forgotPassword',
                ]
            );

            Route::post(
                'password/verify-code',
                [
                    AuthOtpController::class,
                    'verifyPasswordCode',
                ]
            );

            Route::post(
                'password/reset',
                [
                    AuthOtpController::class,
                    'resetPassword',
                ]
            );
        });

    Route::get(
        'listings',
        [ListingController::class, 'index']
    );

    Route::get(
        'listings/{listing}',
        [ListingController::class, 'show']
    );

    Route::middleware([
        'auth:api',
        EnsureUserIsActive::class,
    ])
        ->group(function () {
            Route::get(
                'me',
                [AuthController::class, 'me']
            );

            Route::middleware('throttle:3,1')
                ->group(function () {
                    Route::post(
                        'email/verification/send',
                        [
                            AuthOtpController::class,
                            'sendEmailVerification',
                        ]
                    );

                    Route::post(
                        'email/verification/resend',
                        [
                            AuthOtpController::class,
                            'sendEmailVerification',
                        ]
                    );

                    Route::post(
                        'email/verification/verify',
                        [
                            AuthOtpController::class,
                            'verifyEmail',
                        ]
                    );
                });

            Route::get(
                'orders',
                [OrderController::class, 'index']
            );

            Route::post(
                'orders',
                [OrderController::class, 'store']
            );

            Route::get(
                'orders/{order}',
                [OrderController::class, 'show']
            );

            Route::patch(
                'orders/{order}/cancel',
                [OrderController::class, 'cancel']
            );

            Route::post(
                'orders/{order}/payment/initialize',
                [
                    PaymentController::class,
                    'initialize',
                ]
            );

            Route::post(
                'orders/{order}/payment/verify',
                [
                    PaymentController::class,
                    'verify',
                ]
            );

            /*
             * Buyer disputes.
             */
            Route::get(
                'disputes',
                [
                    DisputeController::class,
                    'index',
                ]
            );

            Route::post(
                'disputes',
                [
                    DisputeController::class,
                    'store',
                ]
            );

            Route::get(
                'disputes/{dispute}',
                [
                    DisputeController::class,
                    'show',
                ]
            );

            Route::patch(
                'disputes/{dispute}/read',
                [
                    DisputeController::class,
                    'markRead',
                ]
            );

            Route::post(
                'disputes/{dispute}/messages',
                [
                    DisputeController::class,
                    'storeMessage',
                ]
            );

            Route::get(
                'disputes/{dispute}/attachments/{attachment}',
                [
                    DisputeController::class,
                    'downloadAttachment',
                ]
            )
                ->name(
                    'disputes.attachments.download'
                );
        });

    Route::middleware([
        'auth:api',
        EnsureUserIsActive::class,
        'admin',
    ])
        ->prefix('admin')
        ->group(function () {
            Route::get(
                'dashboard',
                DashboardController::class
            );

            Route::get(
                'dashboard/revenue',
                DashboardRevenueController::class
            );

            Route::get(
                'activities',
                ActivityController::class
            );

            /*
             * Admin notifications.
             */
            Route::get(
                'notifications',
                [
                    NotificationController::class,
                    'index',
                ]
            );

            Route::patch(
                'notifications/read-all',
                [
                    NotificationController::class,
                    'markAllRead',
                ]
            );

            Route::patch(
                'notifications/{notification}/read',
                [
                    NotificationController::class,
                    'markRead',
                ]
            );

            Route::apiResource(
                'categories',
                CategoryController::class
            );

            Route::apiResource(
                'produce',
                ProduceController::class
            );

            Route::patch(
                'farmers/{farmer}/status',
                [
                    FarmerController::class,
                    'updateStatus',
                ]
            );

            Route::patch(
                'farmers/{farmer}/verification',
                [
                    FarmerController::class,
                    'updateVerification',
                ]
            );

            Route::apiResource(
                'farmers',
                FarmerController::class
            );

            Route::get(
                'listings',
                [
                    AdminListingController::class,
                    'all',
                ]
            );

            Route::post(
                'listings/{listing}/images',
                [
                    AdminListingImageController::class,
                    'store',
                ]
            );

            Route::patch(
                'listings/{listing}/images/reorder',
                [
                    AdminListingImageController::class,
                    'reorder',
                ]
            );

            Route::delete(
                'listings/{listing}/images/{listingImage}',
                [
                    AdminListingImageController::class,
                    'destroy',
                ]
            );

            Route::get(
                'listings/{listing}',
                [
                    AdminListingController::class,
                    'show',
                ]
            );

            Route::put(
                'listings/{listing}',
                [
                    AdminListingController::class,
                    'update',
                ]
            );

            Route::patch(
                'listings/{listing}',
                [
                    AdminListingController::class,
                    'update',
                ]
            );

            Route::delete(
                'listings/{listing}',
                [
                    AdminListingController::class,
                    'destroy',
                ]
            );

            Route::get(
                'farmers/{farmer}/listings',
                [
                    AdminListingController::class,
                    'index',
                ]
            );

            Route::post(
                'farmers/{farmer}/listings',
                [
                    AdminListingController::class,
                    'store',
                ]
            );

            Route::get(
                'farmers/{farmer}/orders',
                [
                    AdminOrderController::class,
                    'farmerIndex',
                ]
            );

            Route::get(
                'orders',
                [
                    AdminOrderController::class,
                    'index',
                ]
            );

            Route::get(
                'orders/{order}',
                [
                    AdminOrderController::class,
                    'show',
                ]
            );

            Route::patch(
                'orders/{order}',
                [
                    AdminOrderController::class,
                    'update',
                ]
            );

            Route::get(
                'users',
                [
                    UserController::class,
                    'index',
                ]
            );

            Route::get(
                'users/{user}',
                [
                    UserController::class,
                    'show',
                ]
            );

            Route::get(
                'buyers',
                [
                    BuyerController::class,
                    'index',
                ]
            );

            Route::patch(
                'buyers/{buyer}/status',
                [
                    BuyerController::class,
                    'updateStatus',
                ]
            );

            Route::get(
                'buyers/{buyer}',
                [
                    BuyerController::class,
                    'show',
                ]
            );

            /*
             * Admin disputes.
             */
            Route::get(
                'disputes',
                [
                    AdminDisputeController::class,
                    'index',
                ]
            );

            Route::get(
                'disputes/{dispute}',
                [
                    AdminDisputeController::class,
                    'show',
                ]
            );

            Route::patch(
                'disputes/{dispute}/read',
                [
                    AdminDisputeController::class,
                    'markRead',
                ]
            );

            Route::post(
                'disputes/{dispute}/messages',
                [
                    AdminDisputeController::class,
                    'storeMessage',
                ]
            );

            Route::get(
                'disputes/{dispute}/attachments/{attachment}',
                [
                    AdminDisputeController::class,
                    'downloadAttachment',
                ]
            )
                ->name(
                    'admin.disputes.attachments.download'
                );

            Route::patch(
                'disputes/{dispute}',
                [
                    AdminDisputeController::class,
                    'update',
                ]
            );
        });
});