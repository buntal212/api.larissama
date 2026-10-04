<?php

namespace App\Actions\Admin;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Support\Facades\DB;

class ProvisionWarung
{
    /**
     * @param  array{
     *     kode:string,
     *     nama:string,
     *     timezone:string,
     *     alamat?:?string,
     *     telepon?:?string,
     *     tanggal_mulai:?string,
     *     tanggal_berakhir:?string,
     *     aktif?:bool,
     *     owner:array{nama:string,username:string,email?:?string,password:string}
     * }  $attributes
     * @return array{warung: Warung, owner: User}
     */
    public function execute(array $attributes): array
    {
        return DB::transaction(function () use ($attributes): array {
            $ownerAttributes = $attributes['owner'];
            unset($attributes['owner']);

            $attributes['aktif'] ??= true;
            $warung = Warung::query()->create($attributes);

            $ownerAttributes['role'] = 'owner';
            $ownerAttributes['aktif'] = true;
            $owner = $warung->users()->create($ownerAttributes);

            return [
                'warung' => $warung,
                'owner' => $owner,
            ];
        });
    }
}
