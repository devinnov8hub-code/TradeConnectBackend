<?php

namespace App\Services;

use App\Models\AuthOtp;
use App\Models\User;
use App\Notifications\AuthOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthOtpService
{
    public const EMAIL_VERIFICATION =
        'email_verification';

    public const PASSWORD_RESET =
        'password_reset';

    public function issue(
        User $user,
        string $purpose
    ): void {
        $code = (string) random_int(
            100000,
            999999
        );

        AuthOtp::query()->updateOrCreate(
            [
                'email' => $user->email,
                'purpose' => $purpose,
            ],
            [
                'code_hash' => Hash::make($code),
                'reset_token_hash' => null,
                'expires_at' => now()->addMinutes(10),
                'verified_at' => null,
                'consumed_at' => null,
            ]
        );

        $user->notify(
            new AuthOtpNotification(
                code: $code,
                purpose: $purpose,
                expiresInMinutes: 10
            )
        );
    }

    public function verifyEmail(
        User $user,
        string $code
    ): bool {
        $otp = $this->validOtp(
            $user->email,
            self::EMAIL_VERIFICATION,
            $code
        );

        if ($otp === null) {
            return false;
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $otp->update([
            'verified_at' => now(),
            'consumed_at' => now(),
        ]);

        return true;
    }

    public function verifyPasswordCode(
        string $email,
        string $code
    ): ?string {
        $otp = $this->validOtp(
            $email,
            self::PASSWORD_RESET,
            $code
        );

        if ($otp === null) {
            return null;
        }

        $resetToken = Str::random(64);

        $otp->update([
            'verified_at' => now(),
            'reset_token_hash' => hash(
                'sha256',
                $resetToken
            ),
        ]);

        return $resetToken;
    }

    public function resetPassword(
        string $email,
        string $resetToken,
        string $password
    ): bool {
        $otp = AuthOtp::query()
            ->where('email', $email)
            ->where(
                'purpose',
                self::PASSWORD_RESET
            )
            ->whereNull('consumed_at')
            ->whereNotNull('verified_at')
            ->where(
                'expires_at',
                '>',
                now()
            )
            ->first();

        if (
            $otp === null
            || $otp->reset_token_hash === null
            || ! hash_equals(
                $otp->reset_token_hash,
                hash('sha256', $resetToken)
            )
        ) {
            return false;
        }

        $user = User::query()
            ->where('email', $email)
            ->first();

        if ($user === null) {
            return false;
        }

        $user->update([
            'password' => $password,
        ]);

        $otp->update([
            'consumed_at' => now(),
            'reset_token_hash' => null,
        ]);

        return true;
    }

    private function validOtp(
        string $email,
        string $purpose,
        string $code
    ): ?AuthOtp {
        $otp = AuthOtp::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where(
                'expires_at',
                '>',
                now()
            )
            ->first();

        if (
            $otp === null
            || ! Hash::check(
                $code,
                $otp->code_hash
            )
        ) {
            return null;
        }

        return $otp;
    }
}
