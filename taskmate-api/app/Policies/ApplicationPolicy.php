<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\Task;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Determine whether the user can apply to the task.
     */
    public function create(User $user, Task $task): bool
    {
        return $user->id !== $task->uploader_id;
    }

    /**
     * Determine whether the user can accept the application.
     */
    public function accept(User $user, Application $application): bool
    {
        return $user->id === $application->task->uploader_id;
    }

    /**
     * Determine whether the user can reject the application.
     */
    public function reject(User $user, Application $application): bool
    {
        return $user->id === $application->task->uploader_id;
    }

    /**
     * Determine whether the user can withdraw the application.
     */
    public function withdraw(User $user, Application $application): bool
    {
        return $user->id === $application->user_id;
    }
}
