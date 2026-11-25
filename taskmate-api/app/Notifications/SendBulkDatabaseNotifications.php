<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SendBulkDatabaseNotifications implements ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $userIds
     */
    public function __construct(
        public readonly TaskmateNotification $notification,
        public readonly array $userIds,
    ) {}

    /**
     * Insert all recipient rows in a single query.
     */
    public function handle(): void
    {
        $now = now();
        $data = (string) json_encode($this->notification->payload());

        $rows = Collection::make($this->userIds)
            ->unique()
            ->filter(fn (mixed $id): bool => (int) $id > 0)
            ->map(fn (mixed $id): array => [
                'id' => (string) Str::uuid(),
                'type' => $this->notification->databaseType(),
                'notifiable_type' => User::class,
                'notifiable_id' => (int) $id,
                'data' => $data,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('notifications')->insert($rows);
        }
    }
}
