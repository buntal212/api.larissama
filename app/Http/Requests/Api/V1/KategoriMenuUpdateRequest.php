<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantWriteScope;
use App\Models\KategoriMenu;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class KategoriMenuUpdateRequest extends FormRequest
{
    use ValidatesTenantWriteScope;

    public function authorize(): bool
    {
        return $this->user()?->can('create', KategoriMenu::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'warung_id' => $this->tenantWriteScopeRules(),
            'nama' => ['sometimes', 'required', 'string', 'min:1', 'max:100'],
            'urutan' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
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
