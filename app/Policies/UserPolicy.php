<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return ($actor->role === 'owner' && $actor->warung_id !== null)
            || ($actor->role === 'superadmin' && $actor->warung_id === null);
    }

    public function create(User $actor): bool
    {
        return $actor->role === 'owner' && $actor->warung_id !== null;
    }

    public function view(User $actor, User $target, ?string $selectedWarungId = null): bool
    {
        if ($actor->role === 'superadmin' && $actor->warung_id === null) {
            return $selectedWarungId !== null
                && (string) $target->warung_id === $selectedWarungId
                && $target->role !== 'superadmin';
        }

        return $this->viewAny($actor)
            && $actor->warung_id === $target->warung_id
            && $target->role !== 'superadmin';
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->role === 'owner'
            && $actor->warung_id !== null
            && $actor->warung_id === $target->warung_id
            && $target->role !== 'superadmin'
            && $actor->getKey() !== $target->getKey();
    }
}
