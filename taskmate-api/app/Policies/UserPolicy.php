<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user may view the target's CV.
     */
    public function viewCv(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }

        return $user->postedTasks()
            ->whereHas('applications', fn ($query) => $query->where('user_id', $target->id))
            ->exists();
    }
}
