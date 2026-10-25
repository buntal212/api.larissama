<?php

namespace App\Actions\Udev;

use RuntimeException;

class UdevBillingRequestException extends RuntimeException
{
    public function __construct(
        public readonly ?int $upstreamStatus = null,
        public readonly ?string $retryAfter = null,
        public readonly ?string $upstreamRequestId = null,
    ) {
        parent::__construct('UDEV billing request failed.');
    }
}
