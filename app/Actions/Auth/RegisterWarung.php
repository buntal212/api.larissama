<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\Warung;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterWarung
{
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
        return DB::transaction(function () use ($attributes): array {
            $ownerAttributes = $attributes['owner'];
            unset($attributes['owner']);

            $warung = Warung::query()->create([
                ...$attributes,
                'kode' => 'WRG-'.Str::ulid(),
                'tanggal_mulai' => null,
                'tanggal_berakhir' => null,
                'aktif' => false,
                'pendaftaran_disetujui' => false,
            ]);

            $owner = $warung->users()->create([
                ...$ownerAttributes,
                'role' => 'owner',
                'aktif' => true,
            ]);

            return [
                'warung' => $warung,
                'owner' => $owner,
            ];
        });
    }
}
