<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantWriteScope;
use App\Models\User;
use App\Rules\NotFutureTransactionTimestamp;
use App\Rules\UtcMysqlDateTimeRange;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class PenjualanKoreksiRequest extends FormRequest
{
    use ValidatesTenantWriteScope;

    public function authorize(): bool
    {
        return $this->canWriteTenantData(['owner', 'manager', 'kasir']);
    }

    public function rules(): array
    {
        $actor = $this->user();
        $money = 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/';
        $positiveMoney = 'regex:/^(0\.(0[1-9]|[1-9][0-9])|[1-9][0-9]{0,12}\.[0-9]{2})$/';
        $quantity = 'regex:/^(0\.(0[1-9]|[1-9][0-9])|[1-9][0-9]{0,7}\.[0-9]{2})$/';

        return [
            'warung_id' => $this->tenantWriteScopeRules(),
            'alasan' => ['required', 'string', 'min:1', 'max:1000', 'regex:/\S/'],
            'tanggal' => [
                'sometimes', 'date',
                'regex:/\A[1-9]\d{3}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d+)?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)\z/',
                new UtcMysqlDateTimeRange,
                new NotFutureTransactionTimestamp,
            ],
            'catatan' => ['sometimes', 'nullable', 'string'],
            'nama_pelanggan' => ['sometimes', 'nullable', 'string', 'max:150'],
            'diskon' => ['sometimes', 'string', $money],
            'bayar' => ['sometimes', 'string', $money],
            'metode_pembayaran' => ['sometimes', Rule::in(['cash', 'qris', 'transfer'])],
            'rincian' => ['sometimes', 'array', 'min:1'],
            'rincian.*.menu_id' => [
                'sometimes', 'integer', 'min:1',
                Rule::exists('menus', 'id')->where('warung_id', $actor instanceof User && $actor->role === 'superadmin' ? $this->input('warung_id') : ($actor instanceof User ? $actor->warung_id : null)),
            ],
            'rincian.*.nama_menu' => ['sometimes', 'required', 'string', 'max:150', 'regex:/\S/'],
            'rincian.*.harga' => ['sometimes', 'required', 'string', $positiveMoney],
            'rincian.*.qty' => ['required_with:rincian', 'string', $quantity],
            'rincian.*.diskon' => ['sometimes', 'string', $money],
            'rincian.*.catatan' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('rincian', []) as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $hasMenu = array_key_exists('menu_id', $line);
                $hasName = array_key_exists('nama_menu', $line);
                $hasPrice = array_key_exists('harga', $line);

                if ($hasMenu && ($hasName || $hasPrice)) {
                    $validator->errors()->add("rincian.$index.menu_id", 'Baris katalog tidak boleh mengirim nama_menu atau harga; backend mengambil snapshot dari menu.');
                } elseif (! $hasMenu) {
                    if (! $hasName) {
                        $validator->errors()->add("rincian.$index.nama_menu", 'Nama item wajib untuk baris item bebas.');
                    }
                    if (! $hasPrice) {
                        $validator->errors()->add("rincian.$index.harga", 'Harga wajib untuk baris item bebas.');
                    }
                }
            }

            if (! array_intersect(['tanggal', 'catatan', 'nama_pelanggan', 'diskon', 'bayar', 'metode_pembayaran', 'rincian'], array_keys($this->all()))) {
                $validator->errors()->add('alasan', 'Sertakan setidaknya satu data yang akan diubah.');
            }

            if (array_key_exists('bayar', $this->all()) !== array_key_exists('metode_pembayaran', $this->all())) {
                $validator->errors()->add('bayar', 'Bayar dan metode_pembayaran harus diubah bersama.');
                $validator->errors()->add('metode_pembayaran', 'Bayar dan metode_pembayaran harus diubah bersama.');
            }
        }];
    }
}
