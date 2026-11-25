<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

final class ResetPasswordNotification extends ResetPassword
{
    /**
     * Build the frontend password reset URL.
     */
    protected function resetUrl($notifiable): string
    {
        $frontendUrl = rtrim((string) config('taskmate.frontend_url'), '/');
        $email = rawurlencode((string) $notifiable->getEmailForPasswordReset());
        $token = rawurlencode($this->token);

        return "{$frontendUrl}/reset-password?token={$token}&email={$email}";
    }
}
