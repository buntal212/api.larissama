<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantWriteScope;
use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UserStoreRequest extends FormRequest
{
    use ValidatesTenantWriteScope;

    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'warung_id' => $this->tenantWriteScopeRules(),
            'nama' => ['required', 'string', 'min:1', 'max:150'],
            'username' => ['required', 'string', 'min:1', 'max:100', 'lowercase', Rule::unique('users', 'username')],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', 'lowercase', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['owner', 'manager', 'kasir'])],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }
}
