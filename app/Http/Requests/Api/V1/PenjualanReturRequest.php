<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesTenantWriteScope;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class PenjualanReturRequest extends FormRequest
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
            'alasan' => ['required', 'string', 'min:1', 'max:1000', 'regex:/\S/'],
            'nominal' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/', 'gt:0'],
        ];
    }
}
