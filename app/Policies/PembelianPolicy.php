<?php

namespace App\Policies;

use App\Models\Pembelian;
use App\Models\User;

class PembelianPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return (in_array($user->role, ['owner', 'manager'], true) && $user->warung_id !== null)
            || ($user->role === 'superadmin' && $user->warung_id === null);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Pembelian $pembelian, ?string $selectedWarungId = null): bool
    {
        if ($user->role === 'superadmin' && $user->warung_id === null) {
            return $selectedWarungId !== null && (string) $pembelian->warung_id === $selectedWarungId;
        }

        return $this->viewAny($user) && (int) $user->warung_id === (int) $pembelian->warung_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return ($user->role === 'superadmin' && $user->warung_id === null)
            || (in_array($user->role, ['owner', 'manager'], true) && $user->warung_id !== null);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Pembelian $pembelian, ?string $selectedWarungId = null): bool
    {
        return $this->view($user, $pembelian, $selectedWarungId);
    }

    public function cancel(User $user, Pembelian $pembelian, ?string $selectedWarungId = null): bool
    {
        return $this->view($user, $pembelian, $selectedWarungId);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Pembelian $pembelian): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Pembelian $pembelian): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Pembelian $pembelian): bool
    {
        return false;
    }
}
