<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartSubscriptionCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['prohibited'],
            'currency' => ['prohibited'],
            'merchant' => ['prohibited'],
            'provider' => ['sometimes', 'string', 'in:dana'],
            'idempotency_key' => ['nullable', 'prohibited'],
            'payment_method' => ['sometimes', 'string', 'in:qris,dana_wallet,va_bri,va_cimb,va_btpn,va_pani'],
            'bank' => ['sometimes', 'nullable', 'string', 'in:bri,cimb,btpn,pani'],
            'warung_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return is_string($key) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $key) === 1
            ? $key
            : null;
    }
}
