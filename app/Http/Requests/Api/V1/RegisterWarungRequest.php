<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class RegisterWarungRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:1', 'max:150'],
            'timezone' => ['required', 'string', 'timezone'],
            'alamat' => ['sometimes', 'nullable', 'string'],
            'telepon' => ['sometimes', 'nullable', 'string', 'min:1', 'max:30'],
            'owner' => ['required', 'array:nama,username,email,password'],
            'owner.nama' => ['required', 'string', 'min:1', 'max:150'],
            'owner.username' => ['required', 'string', 'min:1', 'max:100', 'lowercase', Rule::unique('users', 'username')],
            'owner.email' => ['sometimes', 'nullable', 'email', 'max:150', 'lowercase', Rule::unique('users', 'email')],
            'owner.password' => ['required', 'string', 'min:8'],
        ];
    }
}
