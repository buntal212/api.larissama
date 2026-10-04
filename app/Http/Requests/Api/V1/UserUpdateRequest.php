<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class UserUpdateRequest extends FormRequest
{
    private ?User $targetUser = null;

    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User || ! $actor->can('viewAny', User::class)) {
            return false;
        }

        $this->targetUser = User::query()
            ->where('warung_id', $actor->warung_id)
            ->findOrFail($this->route('id'));

        return $actor->can('update', $this->targetUser);
    }

    public function rules(): array
    {
        return [
            'nama' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:1',
                'max:100',
                Rule::unique('users', 'username')->ignore($this->targetUser),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($this->targetUser),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
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
            if ($this->all() === []) {
                $validator->errors()->add('data', 'Minimal satu field harus dikirim.');
            }
        }];
    }
}
