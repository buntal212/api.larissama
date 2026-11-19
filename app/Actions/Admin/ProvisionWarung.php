<?php

namespace App\Actions\Admin;

use App\Actions\Udev\EnsureWarungCustomerSync;
use App\Models\User;
use App\Models\Warung;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionWarung
{
    public function __construct(
        private readonly EnsureWarungCustomerSync $ensureSync = new EnsureWarungCustomerSync(),
    ) {}

    /**
     * @param  array{
     *     nama:string,
     *     timezone:string,
     *     alamat?:?string,
     *     telepon?:?string,
     *     owner:array{nama:string,username:string,email?:?string,password:string}
     * }  $attributes
     * @return array{warung: Warung, owner: User}
     */
    public function execute(array $attributes): array
    {
        $result = DB::transaction(function () use ($attributes): array {
            $ownerAttributes = $attributes['owner'];
            unset($attributes['owner']);

            $attributes['kode'] = 'WRG-'.Str::ulid();
            $attributes['tanggal_mulai'] = null;
            $attributes['tanggal_berakhir'] = null;
            $attributes['aktif'] = false;
            $attributes['pendaftaran_disetujui'] = false;
            $warung = Warung::query()->create($attributes);

            $ownerAttributes['role'] = 'owner';
            $ownerAttributes['aktif'] = true;
            $owner = $warung->users()->create($ownerAttributes);

            return [
                'warung' => $warung,
                'owner' => $owner,
            ];
        });

        $this->ensureSync->execute($result['warung'], auth()->user());

        return $result;
    }
}
