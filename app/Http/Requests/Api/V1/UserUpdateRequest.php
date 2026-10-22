<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantWriteScope;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UserUpdateRequest extends FormRequest
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
            'nama' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:1',
                'max:100',
                'lowercase',
                Rule::unique('users', 'username')->ignore($this->route('id')),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:150',
                'lowercase',
                Rule::unique('users', 'email')->ignore($this->route('id')),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['sometimes', 'required_with:password', 'string'],
            'role' => ['sometimes', 'required', 'string', Rule::in(['owner', 'manager', 'kasir'])],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_key_exists('password_confirmation', $this->all()) && ! array_key_exists('password', $this->all())) {
                $validator->errors()->add('password_confirmation', 'Konfirmasi hanya boleh dikirim bersama password baru.');
            }

            if ($this->except(['warung_id', 'password_confirmation']) === []) {
                $validator->errors()->add('data', 'Minimal satu field harus dikirim.');
            }
        }];
    }
}
