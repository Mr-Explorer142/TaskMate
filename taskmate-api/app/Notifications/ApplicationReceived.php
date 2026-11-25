<?php

namespace App\Notifications;

final class ApplicationReceived extends TaskmateNotification
{
    public function __construct(
        int $taskId,
        int $applicationId,
        int $applicantId,
        string $applicantName,
        string $taskTitle,
    ) {
        parent::__construct(
            'application_received',
            'New application',
            "{$applicantName} applied to \"{$taskTitle}\".",
            [
                'task_id' => $taskId,
                'application_id' => $applicationId,
                'user_id' => $applicantId,
            ],
        );
    }
}
