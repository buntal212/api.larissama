<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;

#[FailOnUnknownFields]
class PembelianCancelRequest extends PembelianKoreksiRequest
{
    public function authorize(): bool
    {
        return $this->authorizePurchaseCorrection('cancel');
    }

    public function rules(): array
    {
        return [
            'warung_id' => $this->transactionWriteScopeRules(),
            'alasan' => $this->correctionReasonRules(),
        ];
    }
}
