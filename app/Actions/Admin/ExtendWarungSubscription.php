<?php

namespace App\Actions\Admin;

use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ExtendWarungSubscription
{
    public function execute(Warung $warung): Warung
    {
        return DB::transaction(function () use ($warung): Warung {
            $lockedWarung = Warung::query()->lockForUpdate()->findOrFail($warung->getKey());
            $today = CarbonImmutable::now('UTC')->setTimezone($lockedWarung->timezone)->startOfDay();
            $endDate = $lockedWarung->tanggal_berakhir?->toDateString();

            if (! $lockedWarung->pendaftaran_disetujui || ($lockedWarung->tanggal_mulai === null && $endDate === null)) {
                throw new ConflictHttpException('Langganan tanpa tanggal akhir tidak dapat diperpanjang.');
            }

            if ($endDate !== null && $endDate >= $today->toDateString()) {
                $newEndDate = CarbonImmutable::parse($endDate, $lockedWarung->timezone)->addDays(30);
                $startDate = $lockedWarung->tanggal_mulai?->toDateString() ?? $today->toDateString();
            } else {
                $startDate = $today->toDateString();
                $newEndDate = $today->addDays(29);
            }

            $lockedWarung->forceFill([
                'tanggal_mulai' => $startDate,
                'tanggal_berakhir' => $newEndDate->toDateString(),
                'aktif' => true,
                'pendaftaran_disetujui' => true,
            ])->save();

            return $lockedWarung;
        });
    }
}
