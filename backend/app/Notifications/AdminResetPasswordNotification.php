<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->email,
        ]);

        return (new MailMessage)
            ->subject('Reset your FastSheba admin password')
            ->greeting('Hello '.$notifiable->name.',')
            ->line(
                'We received a request to reset the password for your '
                .'FastSheba admin account.'
            )
            ->action('Reset admin password', $url)
            ->line(
                'This link expires in '
                .config('auth.passwords.users.expire', 60)
                .' minutes.'
            )
            ->line(
                'Ignore this email when you did not request a password reset.'
            );
    }
}
