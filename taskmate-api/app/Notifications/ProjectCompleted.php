<?php

namespace App\Notifications;

final class ProjectCompleted extends TaskmateNotification
{
    public function __construct(int $taskId, string $taskTitle, ?int $releasedPoints = null)
    {
        $body = "\"{$taskTitle}\" was completed.";

        if ($releasedPoints !== null) {
            $body .= " You received {$releasedPoints} points.";
        }

        parent::__construct(
            'project_completed',
            'Project completed',
            $body,
            ['task_id' => $taskId],
        );
    }
}
