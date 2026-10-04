<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:1', 'max:150'],
            'username' => ['required', 'string', 'min:1', 'max:100', Rule::unique('users', 'username')],
            'email' => ['sometimes', 'nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['owner', 'manager', 'kasir'])],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }
}
