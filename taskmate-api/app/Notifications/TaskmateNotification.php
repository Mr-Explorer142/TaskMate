<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

abstract class TaskmateNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @param  array<string, int|string|null>  $links
     */
    public function __construct(
        public readonly string $eventType,
        public readonly string $title,
        public readonly string $body,
        public readonly array $links = [],
    ) {}

    /**
     * In-app notifications are only stored in the database.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The short type stored in the notifications table.
     */
    public function databaseType(): string
    {
        return $this->eventType;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    /**
     * The JSON payload persisted with the notification.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_filter([
            'type' => $this->eventType,
            'title' => $this->title,
            'body' => $this->body,
            ...$this->links,
        ], fn (mixed $value): bool => $value !== null);
    }
}
