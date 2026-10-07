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
        return ($user->warung_id !== null && in_array($user->role, ['owner', 'manager', 'kasir'], true))
            || ($user->role === 'superadmin' && $user->warung_id === null);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Penjualan $penjualan, ?string $selectedWarungId = null): bool
    {
        if ($user->role === 'superadmin' && $user->warung_id === null) {
            return $selectedWarungId !== null && (string) $penjualan->warung_id === $selectedWarungId;
        }

        return $this->viewAny($user) && (int) $user->warung_id === (int) $penjualan->warung_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['owner', 'manager', 'kasir'], true) && $user->warung_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Penjualan $penjualan): bool
    {
        if ($user->warung_id !== null
            && (int) $user->warung_id === (int) $penjualan->warung_id
            && $penjualan->status_pembayaran === 'belum_lunas'
            && in_array($user->role, ['owner', 'manager', 'kasir'], true)) {
            return true;
        }

        return $this->manageCorrections($user, $penjualan);
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

    public function manageCorrections(User $user, Penjualan $penjualan): bool
    {
        return in_array($user->role, ['owner', 'manager'], true)
            && $user->warung_id !== null
            && (int) $user->warung_id === (int) $penjualan->warung_id;
    }

    public function pay(User $user, Penjualan $penjualan): bool
    {
        return $user->warung_id !== null
            && (int) $user->warung_id === (int) $penjualan->warung_id
            && in_array($user->role, ['owner', 'manager', 'kasir'], true);
    }
}
