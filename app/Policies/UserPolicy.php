<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === 'owner' && $actor->warung_id !== null;
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->viewAny($actor)
            && $actor->warung_id === $target->warung_id
            && $target->role !== 'superadmin';
    }

    public function update(User $actor, User $target): bool
    {
        return $this->view($actor, $target)
            && $actor->getKey() !== $target->getKey();
    }
}
