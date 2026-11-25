<?php

namespace App\Notifications;

final class ReviewReceived extends TaskmateNotification
{
    public function __construct(
        int $taskId,
        int $reviewerId,
        string $reviewerName,
        string $taskTitle,
        int $rating,
    ) {
        parent::__construct(
            'review_received',
            'New review',
            "{$reviewerName} rated you {$rating}/5 for \"{$taskTitle}\".",
            [
                'task_id' => $taskId,
                'user_id' => $reviewerId,
            ],
        );
    }
}
