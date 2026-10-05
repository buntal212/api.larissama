<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Penjualan;
use App\Models\User;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class PenjualanStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Penjualan::class) ?? false;
    }

    public function rules(): array
    {
        $actor = $this->user();
        $money = 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/';
        $quantity = 'regex:/^(0\.(0[1-9]|[1-9][0-9])|[1-9][0-9]{0,7}\.[0-9]{2})$/';

        return [
            'tanggal' => [
                'required',
                'date',
                'regex:/\\A\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-](?:[01]\\d|2[0-3]):[0-5]\\d)\\z/',
            ],
            'diskon' => ['sometimes', 'string', $money],
            'bayar' => ['required', 'string', $money],
            'metode_pembayaran' => ['required', Rule::in(['cash', 'qris', 'transfer'])],
            'catatan' => ['sometimes', 'nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.menu_id' => [
                'required', 'integer', 'min:1',
                Rule::exists('menus', 'id')->where('warung_id', $actor instanceof User ? $actor->warung_id : null),
            ],
            'rincian.*.qty' => ['required', 'string', $quantity],
            'rincian.*.diskon' => ['sometimes', 'string', $money],
            'rincian.*.catatan' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'tanggal.regex' => 'Tanggal harus mengikuti format RFC3339 dengan zona waktu.',
        ];
    }
}
