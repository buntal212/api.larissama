<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Warung;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class WarungStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Warung::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'min:1', 'max:30', Rule::unique('warungs', 'kode')],
            'nama' => ['required', 'string', 'min:1', 'max:150'],
            'timezone' => ['required', 'string', 'timezone'],
            'alamat' => ['sometimes', 'nullable', 'string'],
            'telepon' => ['sometimes', 'nullable', 'string', 'min:1', 'max:30'],
            'tanggal_mulai' => ['present', 'nullable', 'date_format:Y-m-d'],
            'tanggal_berakhir' => ['present', 'nullable', 'date_format:Y-m-d'],
            'aktif' => ['sometimes', 'boolean'],
            'owner' => ['required', 'array:nama,username,email,password'],
            'owner.nama' => ['required', 'string', 'min:1', 'max:150'],
            'owner.username' => ['required', 'string', 'min:1', 'max:100', 'lowercase', Rule::unique('users', 'username')],
            'owner.email' => ['sometimes', 'nullable', 'email', 'max:150', 'lowercase', Rule::unique('users', 'email')],
            'owner.password' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('tanggal_mulai') || $validator->errors()->has('tanggal_berakhir')) {
                return;
            }

            $startDate = $this->input('tanggal_mulai');
            $endDate = $this->input('tanggal_berakhir');

            if (is_string($startDate) && is_string($endDate) && $endDate < $startDate) {
                $validator->errors()->add('tanggal_berakhir', 'Tanggal akhir harus sama atau sesudah tanggal mulai.');
            }
        }];
    }
}
