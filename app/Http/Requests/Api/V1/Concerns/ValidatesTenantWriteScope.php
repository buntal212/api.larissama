<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

trait ValidatesTenantWriteScope
{
    /** @return array<int, mixed> */
    protected function tenantWriteScopeRules(): array
    {
        $actor = $this->user();

        if ($actor instanceof User && $actor->role === 'superadmin' && $actor->warung_id === null) {
            return ['required', 'integer', 'min:1', Rule::exists('warungs', 'id')];
        }

        return ['missing'];
    }

    /** @param array<int, string> $tenantRoles */
    protected function canWriteTenantData(array $tenantRoles): bool
    {
        $actor = $this->user();

        return $actor instanceof User
            && (($actor->role === 'superadmin' && $actor->warung_id === null)
                || ($actor->warung_id !== null && in_array($actor->role, $tenantRoles, true)));
    }
}
