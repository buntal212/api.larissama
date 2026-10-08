<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantWriteScope;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class PenjualanPembayaranRequest extends FormRequest
{
    use ValidatesTenantWriteScope;

    public function authorize(): bool
    {
        return $this->canWriteTenantData(['owner', 'manager', 'kasir']);
    }

    public function rules(): array
    {
        return [
            'warung_id' => $this->tenantWriteScopeRules(),
            'bayar' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/'],
            'metode_pembayaran' => ['required', Rule::in(['cash', 'qris', 'transfer'])],
        ];
    }
}
