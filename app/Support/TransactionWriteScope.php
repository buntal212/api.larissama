<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class TransactionWriteScope
{
    /** @param array<string, mixed> $input */
    public function resolve(User $actor, array $input): int
    {
        if ($actor->role === 'superadmin' && $actor->warung_id === null) {
            $warungId = filter_var($input['warung_id'] ?? null, FILTER_VALIDATE_INT);

            if ($warungId === false || $warungId < 1) {
                throw ValidationException::withMessages(['warung_id' => ['Pilih warung yang menjadi target transaksi.']]);
            }

            return $warungId;
        }

        if ($actor->warung_id === null || array_key_exists('warung_id', $input)) {
            throw ValidationException::withMessages(['warung_id' => ['Field warung_id hanya boleh dikirim superadmin.']]);
        }

        return (int) $actor->warung_id;
    }

    /** @return array{warung_id: int, user_id: int|null, created_by_superadmin_id: int|null} */
    public function headerActor(User $actor, int $warungId): array
    {
        return [
            'warung_id' => $warungId,
            'user_id' => $actor->role === 'superadmin' ? null : (int) $actor->getKey(),
            'created_by_superadmin_id' => $actor->role === 'superadmin' ? (int) $actor->getKey() : null,
        ];
    }

    /** @return array{user_id: int|null, superadmin_id: int|null} */
    public function auditActor(User $actor): array
    {
        return [
            'user_id' => $actor->role === 'superadmin' ? null : (int) $actor->getKey(),
            'superadmin_id' => $actor->role === 'superadmin' ? (int) $actor->getKey() : null,
        ];
    }
}
