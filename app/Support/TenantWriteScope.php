<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class TenantWriteScope
{
    /** @param array<string, mixed> $input */
    public function resolve(User $actor, array $input): int
    {
        if ($actor->role === 'superadmin' && $actor->warung_id === null) {
            $warungId = filter_var($input['warung_id'] ?? null, FILTER_VALIDATE_INT);

            if ($warungId === false || $warungId < 1) {
                throw ValidationException::withMessages(['warung_id' => ['Pilih warung yang menjadi target data.']]);
            }

            return $warungId;
        }

        if ($actor->warung_id === null || array_key_exists('warung_id', $input)) {
            throw ValidationException::withMessages(['warung_id' => ['Field warung_id hanya boleh dikirim superadmin.']]);
        }

        return (int) $actor->warung_id;
    }
}
