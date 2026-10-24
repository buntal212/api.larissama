<?php

namespace App\Actions\Admin;

use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ActivateWarungForThreeDays
{
    public function execute(Warung $warung): Warung
    {
        return DB::transaction(function () use ($warung): Warung {
            $lockedWarung = Warung::query()->lockForUpdate()->findOrFail($warung->getKey());
            $today = CarbonImmutable::now('UTC')->setTimezone($lockedWarung->timezone)->startOfDay();
            $endDate = $lockedWarung->tanggal_berakhir?->toDateString();

            if ($endDate === null && $lockedWarung->tanggal_mulai !== null) {
                throw new ConflictHttpException('Langganan tanpa tanggal akhir tidak dapat diberi tambahan masa aktif.');
            }

            if ($endDate !== null && $endDate >= $today->toDateString()) {
                $startDate = $lockedWarung->tanggal_mulai?->toDateString() ?? $today->toDateString();
                $newEndDate = CarbonImmutable::parse($endDate, $lockedWarung->timezone)->addDays(3);
            } else {
                $startDate = $today->toDateString();
                $newEndDate = $today->addDays(2);
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
