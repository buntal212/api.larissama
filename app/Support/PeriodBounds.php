<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class PeriodBounds
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function utcBounds(string $dateFrom, string $dateTo, string $timezone): array
    {
        $startUtc = CarbonImmutable::parse($dateFrom, $timezone)->startOfDay()->utc();
        $endExclusiveUtc = CarbonImmutable::parse($dateTo, $timezone)->startOfDay()->addDay()->utc();

        return [$startUtc, $endExclusiveUtc];
    }
}
