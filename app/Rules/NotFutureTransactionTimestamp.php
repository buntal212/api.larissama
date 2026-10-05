<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

class NotFutureTransactionTimestamp implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            $transactionAt = CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return;
        }

        if ($transactionAt->isAfter(CarbonImmutable::now('UTC'))) {
            $fail('Tanggal transaksi tidak boleh di masa depan.');
        }
    }
}
