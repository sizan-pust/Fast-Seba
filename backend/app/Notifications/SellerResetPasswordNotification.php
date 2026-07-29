<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class SellerResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('seller.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage())
            ->subject('Reset your FastSheba seller password')
            ->line('You are receiving this email because a seller-panel password reset was requested.')
            ->action('Reset seller password', $url)
            ->line('This password reset link will expire automatically.');
    }
}
