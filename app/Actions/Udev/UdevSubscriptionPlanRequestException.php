<?php

namespace App\Actions\Udev;

use RuntimeException;

class UdevSubscriptionPlanRequestException extends RuntimeException
{
    public function __construct(
        public readonly ?int $upstreamStatus = null,
        public readonly ?string $retryAfter = null,
    ) {
        parent::__construct('UDEV subscription plan request failed.');
    }
}
