<?php

namespace Tests\Support;

final readonly class OpenApiIntegerLiteral
{
    public function __construct(public string $value)
    {
        if (preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value) !== 1) {
            throw new \InvalidArgumentException('The value must be a canonical nonnegative integer.');
        }
    }

    public function compareTo(int $bound): int
    {
        if ($bound < 0) {
            return 1;
        }

        $right = (string) $bound;
        $lengthComparison = strlen($this->value) <=> strlen($right);

        return $lengthComparison !== 0 ? $lengthComparison : strcmp($this->value, $right);
    }
}
