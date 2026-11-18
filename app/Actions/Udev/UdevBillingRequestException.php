<?php

namespace App\Actions\Udev;

use RuntimeException;

class UdevBillingRequestException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $upstreamErrors
     */
    public function __construct(
        public readonly ?int $upstreamStatus = null,
        public readonly ?string $retryAfter = null,
        public readonly ?string $upstreamRequestId = null,
        public readonly ?string $upstreamMessage = null,
        public readonly ?array $upstreamErrors = null,
        public readonly ?string $upstreamErrorCode = null,
    ) {
        parent::__construct($upstreamMessage ?: 'UDEV billing request failed.');
    }
}
