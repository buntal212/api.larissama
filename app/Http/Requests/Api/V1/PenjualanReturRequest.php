<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTransactionWriteScope;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class PenjualanReturRequest extends FormRequest
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
            'alasan' => ['required', 'string', 'min:1', 'max:1000', 'regex:/\S/'],
            'nominal' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/', 'gt:0'],
        ];
    }
}
