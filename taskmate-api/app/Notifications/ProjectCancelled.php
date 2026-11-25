<?php

namespace App\Notifications;

final class ProjectCancelled extends TaskmateNotification
{
    public function __construct(int $taskId, string $taskTitle)
    {
        parent::__construct(
            'project_cancelled',
            'Project cancelled',
            "\"{$taskTitle}\" was cancelled while in progress.",
            ['task_id' => $taskId],
        );
    }
}
