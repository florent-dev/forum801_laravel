<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->isAdmin();
    }

    public function manage(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->id !== $target->id;
    }
}
