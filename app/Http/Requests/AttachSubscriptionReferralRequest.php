<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachSubscriptionReferralRequest extends FormRequest
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
            'referral_code' => ['required', 'string', 'max:40', 'regex:/^REF-[A-Z0-9]{6}$/'],
            'application_id' => ['prohibited'],
            'customer_price' => ['prohibited'],
            'discount_amount' => ['prohibited'],
            'idempotency_key' => ['nullable', 'prohibited'],
        ];
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->header('Idempotency-Key');

        return is_string($key) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/D', $key) === 1
            ? $key
            : null;
    }
}
