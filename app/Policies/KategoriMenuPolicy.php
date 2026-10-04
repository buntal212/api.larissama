<?php

namespace App\Policies;

use App\Models\KategoriMenu;
use App\Models\User;

class KategoriMenuPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->warung_id !== null && in_array($actor->role, ['manager', 'kasir'], true);
    }

    public function create(User $actor): bool
    {
        return $actor->role === 'manager' && $actor->warung_id !== null;
    }

    public function view(User $actor, KategoriMenu $kategoriMenu): bool
    {
        return $this->viewAny($actor) && (int) $actor->warung_id === (int) $kategoriMenu->warung_id;
    }

    public function update(User $actor, KategoriMenu $kategoriMenu): bool
    {
        return $this->create($actor) && (int) $actor->warung_id === (int) $kategoriMenu->warung_id;
    }
}
