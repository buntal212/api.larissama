<?php

namespace App\Actions\Admin;

use App\Actions\Udev\EnsureWarungCustomerSync;
use App\Models\Warung;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ApproveWarung
{
    public function __construct(
        private readonly EnsureWarungCustomerSync $ensureSync = new EnsureWarungCustomerSync(),
    ) {}

    public function execute(Warung $warung): Warung
    {
        $updatedWarung = DB::transaction(function () use ($warung): Warung {
            $lockedWarung = Warung::query()->lockForUpdate()->findOrFail($warung->getKey());

            if ($lockedWarung->pendaftaran_disetujui || $lockedWarung->tanggal_mulai !== null || $lockedWarung->tanggal_berakhir !== null) {
                throw new ConflictHttpException('Warung ini tidak sedang menunggu persetujuan.');
            }

            $today = CarbonImmutable::now('UTC')->setTimezone($lockedWarung->timezone)->startOfDay();
            $lockedWarung->forceFill([
                'tanggal_mulai' => $today->toDateString(),
                'tanggal_berakhir' => $today->addDays(29)->toDateString(),
                'aktif' => true,
                'pendaftaran_disetujui' => true,
            ])->save();

            return $lockedWarung;
        });

        $this->ensureSync->execute($updatedWarung, auth()->user());

        return $updatedWarung;
    }
}
