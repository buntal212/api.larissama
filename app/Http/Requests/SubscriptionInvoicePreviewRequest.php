<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionInvoicePreviewRequest extends FormRequest
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
            'application_id' => ['prohibited'],
        ];
    }
}
