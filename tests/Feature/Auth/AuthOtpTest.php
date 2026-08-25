<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\AuthOtp;
use App\Models\User;
use App\Notifications\AuthOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_verify_email_with_otp(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => UserRole::User,
            'email_verified_at' => null,
        ]);

        $token = auth('api')->login($user);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/email/verification/send'
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Verification code sent.'
            );

        $code = null;

        Notification::assertSentTo(
            $user,
            AuthOtpNotification::class,
            function (
                AuthOtpNotification $notification
            ) use (&$code): bool {
                $code = $notification->code;

                return $notification->purpose
                    === 'email_verification';
            }
        );

        $this->assertNotNull($code);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/email/verification/verify',
                [
                    'code' => $code,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Email verified successfully.'
            );

        $this->assertNotNull(
            $user->fresh()->email_verified_at
        );
    }

    public function test_forgot_password_otp_can_issue_single_use_reset_token(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'role' => UserRole::User,
            'password' => 'old-password',
        ]);

        $this
            ->postJson(
                '/api/v1/password/forgot',
                [
                    'email' => $user->email,
                ]
            )
            ->assertOk();

        $code = null;

        Notification::assertSentTo(
            $user,
            AuthOtpNotification::class,
            function (
                AuthOtpNotification $notification
            ) use (&$code): bool {
                if (
                    $notification->purpose
                    !== 'password_reset'
                ) {
                    return false;
                }

                $code = $notification->code;

                return true;
            }
        );

        $verify = $this->postJson(
            '/api/v1/password/verify-code',
            [
                'email' => $user->email,
                'code' => $code,
            ]
        );

        $verify
            ->assertOk()
            ->assertJsonStructure([
                'reset_token',
                'expires_in',
            ]);

        $resetToken = (string) $verify->json(
            'reset_token'
        );

        $this
            ->postJson(
                '/api/v1/password/reset',
                [
                    'email' => $user->email,
                    'reset_token' => $resetToken,
                    'password' => 'new-password',
                ]
            )
            ->assertOk();

        $this->assertTrue(
            Hash::check(
                'new-password',
                $user->fresh()->password
            )
        );

        $this->assertNotNull(
            AuthOtp::query()
                ->where('email', $user->email)
                ->where('purpose', 'password_reset')
                ->firstOrFail()
                ->consumed_at
        );

        $this
            ->postJson(
                '/api/v1/password/reset',
                [
                    'email' => $user->email,
                    'reset_token' => $resetToken,
                    'password' => 'another-password',
                ]
            )
            ->assertUnprocessable();
    }

    public function test_forgot_password_does_not_reveal_unknown_email(): void
    {
        Notification::fake();

        $this
            ->postJson(
                '/api/v1/password/forgot',
                [
                    'email' =>
                        'missing@example.com',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If that email is registered, a password reset code has been sent.'
            );

        Notification::assertNothingSent();
    }
}
