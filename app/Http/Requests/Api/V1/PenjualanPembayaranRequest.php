<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTransactionWriteScope;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class PenjualanPembayaranRequest extends FormRequest
{
    use ValidatesTransactionWriteScope;

    public function authorize(): bool
    {
        return $this->canWriteTransactions();
    }

    public function rules(): array
    {
        return [
            'warung_id' => $this->transactionWriteScopeRules(),
            'bayar' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/'],
            'metode_pembayaran' => ['required', Rule::in(['cash', 'qris', 'transfer'])],
        ];
    }
}
