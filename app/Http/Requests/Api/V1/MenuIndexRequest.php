<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class MenuIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Menu::class) ?? false;
    }

    public function rules(): array
    {
        $actor = $this->user();
        $categoryRule = Rule::exists('kategori_menus', 'id')->where('warung_id', $actor?->warung_id);

        if ($actor instanceof User && $actor->role === 'kasir') {
            $categoryRule->where('aktif', true);
        }

        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in(['nama', '-nama'])],
            'q' => ['sometimes', 'nullable', 'string', 'max:150'],
            'aktif' => ['sometimes', 'nullable', 'string', Rule::in(['true', 'false', '1', '0'])],
            'kategori_menu_id' => ['sometimes', 'integer', 'min:1', $categoryRule],
        ];
    }
}
