<?php

namespace App\Notifications;

final class MemberLeft extends TaskmateNotification
{
    public function __construct(int $taskId, int $memberId, string $memberName, string $taskTitle)
    {
        parent::__construct(
            'member_left',
            'Team member left',
            "{$memberName} left the team for \"{$taskTitle}\".",
            [
                'task_id' => $taskId,
                'user_id' => $memberId,
            ],
        );
    }
}
