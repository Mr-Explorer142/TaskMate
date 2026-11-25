<?php

namespace App\Notifications;

final class ProjectStarted extends TaskmateNotification
{
    public function __construct(int $taskId, string $taskTitle)
    {
        parent::__construct(
            'project_started',
            'Project started',
            "\"{$taskTitle}\" is now in progress.",
            ['task_id' => $taskId],
        );
    }
}
