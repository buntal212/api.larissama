<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTransactionWriteScope;
use App\Models\Pembelian;
use App\Rules\NotFutureTransactionTimestamp;
use App\Rules\UtcMysqlDateTimeRange;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[FailOnUnknownFields]
class PembelianStoreRequest extends FormRequest
{
    use ValidatesTransactionWriteScope;

    public function authorize(): bool
    {
        return $this->canWriteTransactions() && ($this->user()?->can('create', Pembelian::class) ?? false);
    }

    public function rules(): array
    {
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
            'catatan' => ['sometimes', 'nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.nama_item' => ['required', 'string', 'min:1', 'max:150'],
            'rincian.*.qty' => ['sometimes', 'nullable', 'string', $quantity],
            'rincian.*.satuan' => ['sometimes', 'nullable', 'string', 'min:1', 'max:30'],
            'rincian.*.harga_satuan' => ['sometimes', 'nullable', 'string', $money],
            'rincian.*.subtotal' => ['sometimes', 'string', $money],
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
            $lines = $this->input('rincian', []);

            if (! is_array($lines)) {
                return;
            }

            foreach ($lines as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $hasQuantity = isset($line['qty']);
                $hasUnitPrice = isset($line['harga_satuan']);

                if ($hasQuantity !== $hasUnitPrice) {
                    $validator->errors()->add("rincian.$index.qty", 'Kuantitas dan harga satuan harus diisi bersama.');
                    $validator->errors()->add("rincian.$index.harga_satuan", 'Kuantitas dan harga satuan harus diisi bersama.');
                }

                if (! $hasQuantity && ! $hasUnitPrice && ! isset($line['subtotal'])) {
                    $validator->errors()->add("rincian.$index.subtotal", 'Subtotal wajib untuk rincian nominal.');
                }
            }
        }];
    }
}
