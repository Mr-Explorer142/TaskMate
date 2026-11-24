<?php

namespace App\Policies;

use App\Models\ProfileLink;
use App\Models\User;

class ProfileLinkPolicy
{
    /**
     * Determine whether the user can update the link.
     */
    public function update(User $user, ProfileLink $link): bool
    {
        return $user->id === $link->user_id;
    }

    /**
     * Determine whether the user can delete the link.
     */
    public function delete(User $user, ProfileLink $link): bool
    {
        return $user->id === $link->user_id;
    }
}
