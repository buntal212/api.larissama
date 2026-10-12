<?php

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class CurrentWarungUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && $actor->can('updateCurrent', Warung::class);
    }

    public function rules(): array
    {
        return [
            'nama' => ['sometimes', 'required', 'string', 'min:1', 'max:150'],
            'alamat' => ['sometimes', 'nullable', 'string'],
            'telepon' => ['sometimes', 'nullable', 'string', 'min:1', 'max:30'],
            'timezone' => ['sometimes', 'required', 'string', 'timezone'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->all() === []) {
                $validator->errors()->add('data', 'Minimal satu field harus dikirim.');
            }
        }];
    }
}
