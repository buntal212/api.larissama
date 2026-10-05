<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IdempotencyKeyWindow
{
    public const int RETENTION_DAYS = 7;

    public function expiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->addDays(self::RETENTION_DAYS);
    }

    public function hasExpired(?CarbonImmutable $expiresAt): bool
    {
        return $expiresAt === null || ! $expiresAt->isAfter(CarbonImmutable::now('UTC'));
    }

    public function release(Model $record): void
    {
        DB::table($record->getTable())
            ->where($record->getKeyName(), $record->getKey())
            ->update([
                'idempotency_key' => null,
                'payload_hash' => null,
                'idempotency_expires_at' => null,
            ]);

        $record->setAttribute('idempotency_key', null);
        $record->setAttribute('payload_hash', null);
        $record->setAttribute('idempotency_expires_at', null);
    }
}
