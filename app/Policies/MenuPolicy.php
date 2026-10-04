<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->warung_id !== null && in_array($actor->role, ['manager', 'kasir'], true);
    }

    public function create(User $actor): bool
    {
        return $actor->role === 'manager' && $actor->warung_id !== null;
    }

    public function view(User $actor, Menu $menu): bool
    {
        return $this->viewAny($actor) && (int) $actor->warung_id === (int) $menu->warung_id;
    }

    public function update(User $actor, Menu $menu): bool
    {
        return $this->create($actor) && (int) $actor->warung_id === (int) $menu->warung_id;
    }
}
