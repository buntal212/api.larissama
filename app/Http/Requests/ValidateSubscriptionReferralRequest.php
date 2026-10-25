<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidateSubscriptionReferralRequest extends FormRequest
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
        ];
    }
}
