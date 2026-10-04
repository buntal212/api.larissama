<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class MenuUpdateRequest extends FormRequest
{
    private ?Menu $targetMenu = null;

    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User || ! $actor->can('create', Menu::class)) {
            return false;
        }

        $this->targetMenu = Menu::query()
            ->where('warung_id', $actor->warung_id)
            ->whereHas('kategoriMenu', fn (Builder $category): Builder => $category->where('warung_id', $actor->warung_id))
            ->findOrFail($this->route('id'));

        return $actor->can('update', $this->targetMenu);
    }

    public function rules(): array
    {
        $actor = $this->user();
        $tenantId = $actor instanceof User ? $actor->warung_id : null;

        return [
            'kategori_menu_id' => [
                'sometimes', 'required', 'integer', 'min:1',
                Rule::exists('kategori_menus', 'id')->where('warung_id', $tenantId),
            ],
            'kode' => [
                'sometimes', 'required', 'string', 'min:1', 'max:30',
                Rule::unique('menus', 'kode')->where('warung_id', $tenantId)->ignore($this->targetMenu),
            ],
            'nama' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'harga' => ['sometimes', 'required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/'],
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
            if ($this->all() === []) {
                $validator->errors()->add('data', 'Minimal satu field harus dikirim.');
            }
        }];
    }

    public function targetMenu(): Menu
    {
        return $this->targetMenu ?? abort(404);
    }
}
