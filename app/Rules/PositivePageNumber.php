<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PositivePageNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail("The {$attribute} field must be an integer of at least 1.");

            return;
        }

        $digits = (string) $value;

        if (preg_match('/\A[0-9]+\z/', $digits) !== 1 || trim($digits, '0') === '') {
            $fail("The {$attribute} field must be an integer of at least 1.");
        }
    }
}
