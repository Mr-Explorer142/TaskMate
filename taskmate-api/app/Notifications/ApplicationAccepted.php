<?php

namespace App\Notifications;

final class ApplicationAccepted extends TaskmateNotification
{
    public function __construct(int $taskId, int $applicationId, string $taskTitle)
    {
        parent::__construct(
            'application_accepted',
            'Application accepted',
            "You joined the team for \"{$taskTitle}\".",
            [
                'task_id' => $taskId,
                'application_id' => $applicationId,
            ],
        );
    }
}
