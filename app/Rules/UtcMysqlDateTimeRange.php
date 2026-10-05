<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

class UtcMysqlDateTimeRange implements ValidationRule
{
    private const MINIMUM = '1000-01-01 00:00:00';

    private const MAXIMUM = '9999-12-31 23:59:59';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            $instant = CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return;
        }

        $year = (int) $instant->format('Y');

        if ($year < 1000 || $year > 9999) {
            $fail('Tanggal setelah dikonversi ke UTC harus berada dalam rentang MySQL DATETIME.');

            return;
        }

        $utc = $instant->format('Y-m-d H:i:s');

        if ($utc < self::MINIMUM || $utc > self::MAXIMUM) {
            $fail('Tanggal setelah dikonversi ke UTC harus berada dalam rentang MySQL DATETIME.');
        }
    }
}
