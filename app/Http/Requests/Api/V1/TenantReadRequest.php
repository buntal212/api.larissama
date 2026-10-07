<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantReadScope;
use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class TenantReadRequest extends FormRequest
{
    use ValidatesTenantReadScope;

    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User
            && ($actor->role === 'superadmin' || $actor->warung_id !== null);
    }

    public function rules(): array
    {
        return $this->tenantReadScopeRules();
    }
}
