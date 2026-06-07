<?php

namespace App\Notifications\Central\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPassword extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.url').'/api/v1/auth/reset-password?token='.$this->token.'&email='.$notifiable->getEmailForPasswordReset();

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', $url)
            ->line('This password reset link will expire in '.config('auth.passwords.central_users.expire').' minutes.')
            ->line('If you did not request a password reset, no further action is required.');
    }
}
