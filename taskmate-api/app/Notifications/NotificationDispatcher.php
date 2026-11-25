<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

final class NotificationDispatcher
{
    /**
     * Notify the given recipients, excluding the actor from the recipient list.
     *
     * @param  array<int, int>|int  $recipientIds
     */
    public function send(array|int $recipientIds, TaskmateNotification $notification, ?int $actorId = null): void
    {
        $userIds = $this->normalizeRecipients($recipientIds, $actorId);

        if ($userIds === []) {
            return;
        }

        if (count($userIds) === 1) {
            User::query()->findOrFail($userIds[0])->notify($notification->afterCommit());

            return;
        }

        Bus::dispatch((new SendBulkDatabaseNotifications($notification, $userIds))->afterCommit());
    }

    /**
     * @param  array<int, int>|int  $recipientIds
     * @return array<int, int>
     */
    private function normalizeRecipients(array|int $recipientIds, ?int $actorId): array
    {
        return Collection::wrap($recipientIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== $actorId)
            ->unique()
            ->values()
            ->all();
    }
}
