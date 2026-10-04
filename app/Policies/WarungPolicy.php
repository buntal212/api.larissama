<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warung;

class WarungPolicy
{
    public function viewAny(User $actor): bool
    {
        return $this->isSuperadmin($actor);
    }

    public function create(User $actor): bool
    {
        return $this->isSuperadmin($actor);
    }

    public function view(User $actor, Warung $warung): bool
    {
        return $this->isSuperadmin($actor);
    }

    public function update(User $actor, Warung $warung): bool
    {
        return $this->isSuperadmin($actor);
    }

    public function viewCurrent(User $actor): bool
    {
        return in_array($actor->role, ['owner', 'manager', 'kasir'], true)
            && $actor->warung_id !== null;
    }

    private function isSuperadmin(User $actor): bool
    {
        return $actor->role === 'superadmin' && $actor->warung_id === null;
    }
}
