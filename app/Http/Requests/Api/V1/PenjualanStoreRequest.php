<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTransactionWriteScope;
use App\Models\Penjualan;
use App\Models\User;
use App\Rules\NotFutureTransactionTimestamp;
use App\Rules\UtcMysqlDateTimeRange;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class PenjualanStoreRequest extends FormRequest
{
    use ValidatesTransactionWriteScope;

    public function authorize(): bool
    {
        return $this->canWriteTransactions() && ($this->user()?->can('create', Penjualan::class) ?? false);
    }

    public function rules(): array
    {
        $actor = $this->user();
        $money = 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/';
        $quantity = 'regex:/^(0\.(0[1-9]|[1-9][0-9])|[1-9][0-9]{0,7}\.[0-9]{2})$/';

        return [
            'warung_id' => $this->transactionWriteScopeRules(),
            'tanggal' => [
                'required',
                'date',
                'regex:/\\A[1-9]\\d{3}-\\d{2}-\\d{2}T(?:[01]\\d|2[0-3]):[0-5]\\d:[0-5]\\d(?:\\.\\d+)?(?:Z|[+-](?:[01]\\d|2[0-3]):[0-5]\\d)\\z/',
                new UtcMysqlDateTimeRange,
                new NotFutureTransactionTimestamp,
            ],
            'diskon' => ['sometimes', 'string', $money],
            'bayar' => ['sometimes', 'string', $money],
            'metode_pembayaran' => ['sometimes', Rule::in(['cash', 'qris', 'transfer'])],
            'nama_pelanggan' => ['sometimes', 'nullable', 'string', 'max:150'],
            'catatan' => ['sometimes', 'nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.menu_id' => [
                'required', 'integer', 'min:1',
                Rule::exists('menus', 'id')->where('warung_id', $actor instanceof User && $actor->role === 'superadmin' ? $this->input('warung_id') : ($actor instanceof User ? $actor->warung_id : null)),
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

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (array_key_exists('bayar', $this->all()) !== array_key_exists('metode_pembayaran', $this->all())) {
                $validator->errors()->add('bayar', 'Bayar dan metode_pembayaran harus dikirim bersama jika transaksi langsung dilunasi.');
                $validator->errors()->add('metode_pembayaran', 'Bayar dan metode_pembayaran harus dikirim bersama jika transaksi langsung dilunasi.');
            }
        }];
    }
}
