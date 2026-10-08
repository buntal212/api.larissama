<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantWriteScope;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class MenuUpdateRequest extends FormRequest
{
    use ValidatesTenantWriteScope;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Menu::class) ?? false;
    }

    public function rules(): array
    {
        $actor = $this->user();
        $tenantId = $actor instanceof User && $actor->role === 'superadmin'
            ? $this->input('warung_id')
            : ($actor instanceof User ? $actor->warung_id : null);

        return [
            'warung_id' => $this->tenantWriteScopeRules(),
            'kategori_menu_id' => [
                'sometimes', 'required', 'integer', 'min:1',
                Rule::exists('kategori_menus', 'id')->where('warung_id', $tenantId),
            ],
            'kode' => [
                'sometimes', 'required', 'string', 'min:1', 'max:30',
                Rule::unique('menus', 'kode')->where('warung_id', $tenantId)->ignore($this->route('id')),
            ],
            'nama' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'harga' => ['sometimes', 'required', 'string', 'regex:/^(0\.(0[1-9]|[1-9][0-9])|[1-9][0-9]{0,12}\.[0-9]{2})$/'],
            'deskripsi' => ['sometimes', 'nullable', 'string'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->except('warung_id') === []) {
                $validator->errors()->add('data', 'Minimal satu field harus dikirim.');
            }
        }];
    }
}
