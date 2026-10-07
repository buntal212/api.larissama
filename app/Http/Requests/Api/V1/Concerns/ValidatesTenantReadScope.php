<?php

namespace App\Http\Requests\Api\V1\Concerns;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Validation\Rule;

trait ValidatesTenantReadScope
{
    /** @return array<string, array<int, mixed>> */
    protected function tenantReadScopeRules(): array
    {
        if ($this->user()?->role === 'superadmin') {
            return [
                'warung_id' => ['required', 'string', 'regex:/^[1-9][0-9]*$/', Rule::exists('warungs', 'id')],
            ];
        }

        return ['warung_id' => [Rule::prohibitedIf($this->user()?->role !== 'superadmin')]];
    }

    protected function tenantReadWarungIdForValidation(): ?string
    {
        $actor = $this->user();

        if (! $actor instanceof User) {
            return null;
        }

        if ($actor->role === 'superadmin') {
            $warungId = $this->query('warung_id');

            return is_string($warungId) || is_numeric($warungId) ? (string) $warungId : null;
        }

        return $actor->warung_id === null ? null : (string) $actor->warung_id;
    }

    protected function tenantReadTimezoneForValidation(): string
    {
        $actor = $this->user();

        if ($actor instanceof User && $actor->role !== 'superadmin') {
            return (string) ($actor->warung?->timezone ?? '');
        }

        $warungId = $this->tenantReadWarungIdForValidation();

        return $warungId === null
            ? ''
            : (string) (Warung::query()->whereKey($warungId)->value('timezone') ?? '');
    }
}
