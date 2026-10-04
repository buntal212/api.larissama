<?php

namespace App\Policies;

use App\Models\Penjualan;
use App\Models\User;

class PenjualanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->warung_id !== null && in_array($user->role, ['manager', 'kasir'], true);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Penjualan $penjualan): bool
    {
        if (! $this->viewAny($user) || (int) $user->warung_id !== (int) $penjualan->warung_id) {
            return false;
        }

        return $user->role === 'manager' || (int) $user->getKey() === (int) $penjualan->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'kasir' && $user->warung_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Penjualan $penjualan): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Penjualan $penjualan): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Penjualan $penjualan): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Penjualan $penjualan): bool
    {
        return false;
    }
}
