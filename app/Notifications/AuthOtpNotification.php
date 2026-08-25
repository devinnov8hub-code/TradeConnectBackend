<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AuthOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $purpose,
        public readonly int $expiresInMinutes = 10
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isVerification =
            $this->purpose
            === 'email_verification';

        return (new MailMessage())
            ->subject(
                $isVerification
                    ? 'Verify your TradeConnect email'
                    : 'Reset your TradeConnect password'
            )
            ->greeting(
                'Hello '.$notifiable->name.','
            )
            ->line(
                $isVerification
                    ? 'Use this verification code to verify your email address:'
                    : 'Use this verification code to continue resetting your password:'
            )
            ->line($this->code)
            ->line(
                "This code expires in {$this->expiresInMinutes} minutes."
            )
            ->line(
                'If you did not request this code, you can ignore this message.'
            );
    }
}
