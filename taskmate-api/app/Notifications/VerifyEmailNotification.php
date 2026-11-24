<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $code,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('taskmate.frontend_url'), '/');

        $verificationUrl = $frontendUrl.'/verify-email?'.http_build_query([
            'code' => $this->code,
            'email' => $notifiable->email,
        ]);

        return (new MailMessage)
            ->subject('Verify your TaskMate email')
            ->greeting('Welcome to TaskMate!')
            ->line('Use the code below to verify your email address. It expires in 15 minutes.')
            ->line('**'.$this->code.'**')
            ->action('Verify email', $verificationUrl)
            ->line('If you did not create a TaskMate account, you can safely ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
