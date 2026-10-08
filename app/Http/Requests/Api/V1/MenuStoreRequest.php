<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantWriteScope;
use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class MenuStoreRequest extends FormRequest
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
                'required',
                'integer',
                'min:1',
                Rule::exists('kategori_menus', 'id')->where('warung_id', $tenantId),
            ],
            'nama' => ['required', 'string', 'min:1', 'max:150'],
            'harga' => ['required', 'string', 'regex:/^(0\.(0[1-9]|[1-9][0-9])|[1-9][0-9]{0,12}\.[0-9]{2})$/'],
            'deskripsi' => ['sometimes', 'nullable', 'string'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }
}
