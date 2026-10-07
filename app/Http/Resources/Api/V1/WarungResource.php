<?php

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarungResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'kode' => $this->kode,
            'nama' => $this->nama,
            'alamat' => $this->alamat,
            'telepon' => $this->telepon,
            'logo' => $this->logo,
            'timezone' => $this->timezone,
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_berakhir' => $this->tanggal_berakhir?->toDateString(),
            'aktif' => $this->aktif,
            'status_langganan' => $this->subscriptionStatusAt(CarbonImmutable::now('UTC')),
            'created_at' => $this->created_at?->copy()->utc()->toISOString(),
            'updated_at' => $this->updated_at?->copy()->utc()->toISOString(),
        ];
    }
}
