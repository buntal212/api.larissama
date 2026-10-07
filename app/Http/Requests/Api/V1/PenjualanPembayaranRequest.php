<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class PenjualanPembayaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['owner', 'manager', 'kasir'], true)
            && $this->user()?->warung_id !== null;
    }

    public function rules(): array
    {
        return [
            'bayar' => ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,12})\.[0-9]{2}$/'],
            'metode_pembayaran' => ['required', Rule::in(['cash', 'qris', 'transfer'])],
        ];
    }
}
