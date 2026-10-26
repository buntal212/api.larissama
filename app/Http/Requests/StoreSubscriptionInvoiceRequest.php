<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionInvoiceRequest extends FormRequest
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
            'subscription_plan_code' => ['required', 'string', 'max:100'],
            'period_count' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'amount' => ['prohibited'],
            'currency' => ['prohibited'],
            'due_at' => ['prohibited'],
            'application_id' => ['prohibited'],
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
