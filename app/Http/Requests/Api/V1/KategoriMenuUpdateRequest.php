<?php

namespace App\Http\Requests\Api\V1;

use App\Models\KategoriMenu;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class KategoriMenuUpdateRequest extends FormRequest
{
    private ?KategoriMenu $targetCategory = null;

    public function authorize(): bool
    {
        $actor = $this->user();

        if (! $actor instanceof User || ! $actor->can('create', KategoriMenu::class)) {
            return false;
        }

        $this->targetCategory = KategoriMenu::query()
            ->where('warung_id', $actor->warung_id)
            ->findOrFail($this->route('id'));

        return $actor->can('update', $this->targetCategory);
    }

    public function rules(): array
    {
        return [
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
            if ($this->all() === []) {
                $validator->errors()->add('data', 'Minimal satu field harus dikirim.');
            }
        }];
    }

    public function targetCategory(): KategoriMenu
    {
        return $this->targetCategory ?? abort(404);
    }
}
