<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyEmailOtpRequest;
use App\Http\Requests\Auth\VerifyPasswordOtpRequest;
use App\Models\User;
use App\Services\AuthOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthOtpController extends Controller
{
    public function __construct(
        private readonly AuthOtpService $otpService
    ) {
    }

    public function sendEmailVerification(
        Request $request
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' =>
                    'Email is already verified.',
            ]);
        }

        $this->otpService->issue(
            $user,
            AuthOtpService::EMAIL_VERIFICATION
        );

        return response()->json([
            'message' =>
                'Verification code sent.',
            'expires_in' => 600,
        ]);
    }

    public function verifyEmail(
        VerifyEmailOtpRequest $request
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        if ($user->email_verified_at !== null) {
            return response()->json([
                'message' =>
                    'Email is already verified.',
            ]);
        }

        if (
            ! $this->otpService->verifyEmail(
                $user,
                (string) $request->validated('code')
            )
        ) {
            return $this->invalidCodeResponse();
        }

        return response()->json([
            'message' =>
                'Email verified successfully.',
        ]);
    }

    public function forgotPassword(
        ForgotPasswordRequest $request
    ): JsonResponse {
        $user = User::query()
            ->where(
                'email',
                $request->validated('email')
            )
            ->first();

        if ($user !== null) {
            $this->otpService->issue(
                $user,
                AuthOtpService::PASSWORD_RESET
            );
        }

        return response()->json([
            'message' =>
                'If that email is registered, a password reset code has been sent.',
            'expires_in' => 600,
        ]);
    }

    public function verifyPasswordCode(
        VerifyPasswordOtpRequest $request
    ): JsonResponse {
        $resetToken =
            $this->otpService
                ->verifyPasswordCode(
                    (string) $request->validated('email'),
                    (string) $request->validated('code')
                );

        if ($resetToken === null) {
            return $this->invalidCodeResponse();
        }

        return response()->json([
            'message' =>
                'Verification code accepted.',
            'reset_token' => $resetToken,
            'expires_in' => 600,
        ]);
    }

    public function resetPassword(
        ResetPasswordRequest $request
    ): JsonResponse {
        $reset =
            $this->otpService
                ->resetPassword(
                    (string) $request->validated('email'),
                    (string) $request->validated('reset_token'),
                    (string) $request->validated('password')
                );

        if (! $reset) {
            return response()->json([
                'message' =>
                    'Reset token is invalid or has expired.',
                'errors' => [
                    'reset_token' => [
                        'Reset token is invalid or has expired.',
                    ],
                ],
            ], 422);
        }

        return response()->json([
            'message' =>
                'Password reset successfully.',
        ]);
    }

    private function invalidCodeResponse(): JsonResponse
    {
        return response()->json([
            'message' =>
                'Verification code is invalid or has expired.',
            'errors' => [
                'code' => [
                    'Verification code is invalid or has expired.',
                ],
            ],
        ], 422);
    }
}
