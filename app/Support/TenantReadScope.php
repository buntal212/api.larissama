<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Validation\ValidationException;

class TenantReadScope
{
    /** @param array<string, mixed> $validated */
    public function resolve(User $actor, array $validated): string
    {
        if ($actor->role === 'superadmin') {
            $warungId = $validated['warung_id'] ?? null;

            if (is_string($warungId) || is_numeric($warungId)) {
                return (string) $warungId;
            }

            throw ValidationException::withMessages([
                'warung_id' => ['Superadmin wajib memilih warung untuk membaca data tenant.'],
            ]);
        }

        if ($actor->warung_id === null) {
            abort(403);
        }

        return (string) $actor->warung_id;
    }

    public function timezone(string $warungId): string
    {
        return (string) (Warung::query()->whereKey($warungId)->value('timezone') ?? '');
    }
}
