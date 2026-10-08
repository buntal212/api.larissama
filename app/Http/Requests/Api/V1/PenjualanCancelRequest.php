<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTransactionWriteScope;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class PenjualanCancelRequest extends FormRequest
{
    use ValidatesTransactionWriteScope;

    public function authorize(): bool
    {
        return $this->canWriteTransactions();
    }

    public function rules(): array
    {
        return ['warung_id' => $this->transactionWriteScopeRules(), 'alasan' => ['required', 'string', 'min:1', 'max:1000', 'regex:/\S/']];
    }
}
