<?php

namespace App\Notifications;

final class ApplicationRejected extends TaskmateNotification
{
    public function __construct(int $taskId, int $applicationId, string $taskTitle)
    {
        parent::__construct(
            'application_rejected',
            'Application update',
            "Your application for \"{$taskTitle}\" was not accepted.",
            [
                'task_id' => $taskId,
                'application_id' => $applicationId,
            ],
        );
    }
}
