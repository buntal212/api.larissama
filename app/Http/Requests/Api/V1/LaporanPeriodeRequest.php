<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantReadScope;
use App\Rules\LocalPeriodUtcMysqlRange;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class LaporanPeriodeRequest extends FormRequest
{
    use ValidatesTenantReadScope;

    public function authorize(): bool
    {
        $actor = $this->user();

        return in_array($actor?->role, ['owner', 'manager', 'superadmin'], true)
            && ($actor?->role === 'superadmin' || $actor?->warung_id !== null);
    }

    public function rules(): array
    {
        $timezone = $this->tenantReadTimezoneForValidation();

        return [
            ...$this->tenantReadScopeRules(),
            'date_from' => ['required', 'date_format:Y-m-d', new LocalPeriodUtcMysqlRange($timezone, 'start')],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', new LocalPeriodUtcMysqlRange($timezone, 'end')],
        ];
    }
}
