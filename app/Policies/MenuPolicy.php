<?php

namespace App\Policies;

use App\Models\Menu;
use App\Models\User;

class MenuPolicy
{
    public function viewAny(User $actor): bool
    {
        return ($actor->warung_id !== null && in_array($actor->role, ['owner', 'manager', 'kasir'], true))
            || ($actor->role === 'superadmin' && $actor->warung_id === null);
    }

    public function create(User $actor): bool
    {
        return ($actor->warung_id !== null && in_array($actor->role, ['owner', 'manager'], true))
            || ($actor->role === 'superadmin' && $actor->warung_id === null);
    }

    public function view(User $actor, Menu $menu, ?string $selectedWarungId = null): bool
    {
        if ($actor->role === 'superadmin' && $actor->warung_id === null) {
            return $selectedWarungId !== null && (string) $menu->warung_id === $selectedWarungId;
        }

        return $this->viewAny($actor) && (int) $actor->warung_id === (int) $menu->warung_id;
    }

    public function update(User $actor, Menu $menu, ?string $selectedWarungId = null): bool
    {
        if ($actor->role === 'superadmin' && $actor->warung_id === null) {
            return $selectedWarungId !== null && (string) $menu->warung_id === $selectedWarungId;
        }

        return $this->create($actor) && (int) $actor->warung_id === (int) $menu->warung_id;
    }
}
