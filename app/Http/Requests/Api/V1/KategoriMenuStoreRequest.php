<?php

namespace App\Http\Requests\Api\V1;

use App\Models\KategoriMenu;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class KategoriMenuStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', KategoriMenu::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'min:1', 'max:100'],
            'urutan' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'aktif' => ['sometimes', 'boolean'],
        ];
    }
}
