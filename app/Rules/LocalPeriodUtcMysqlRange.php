<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

class LocalPeriodUtcMysqlRange implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(
        private readonly ?string $timezone,
        private readonly string $bound,
    ) {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $dateFrom = $this->data['date_from'] ?? null;
        $dateTo = $this->data['date_to'] ?? null;

        if (
            $this->timezone === null
            || ! is_string($dateFrom)
            || ! is_string($dateTo)
            || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateFrom) !== 1
            || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateTo) !== 1
        ) {
            return;
        }

        try {
            $date = $this->bound === 'start' ? $dateFrom : $dateTo;
            $utcBound = CarbonImmutable::createFromFormat('!Y-m-d', $date, $this->timezone);

            if ($utcBound === false || $utcBound->format('Y-m-d') !== $date) {
                return;
            }

            if ($this->bound === 'end') {
                $utcBound = $utcBound->addDay();
            }

            $utcBound = $utcBound->utc();
        } catch (Throwable) {
            return;
        }

        $year = (int) $utcBound->format('Y');
        $mysqlDateTime = $utcBound->format('Y-m-d H:i:s');
        $withinMysqlRange = $year >= 1000
            && $year <= 9999
            && $mysqlDateTime >= '1000-01-01 00:00:00'
            && $mysqlDateTime <= '9999-12-31 23:59:59';

        if (! $withinMysqlRange) {
            $fail($this->bound === 'start'
                ? 'Awal periode lokal berada di luar rentang MySQL DATETIME setelah dikonversi ke UTC.'
                : 'Batas akhir periode lokal berada di luar rentang MySQL DATETIME setelah dikonversi ke UTC.');
        }
    }
}
