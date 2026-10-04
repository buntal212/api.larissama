<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'warung_id' => (string) $this->warung_id,
            'kategori_menu_id' => (string) $this->kategori_menu_id,
            'kode' => $this->kode,
            'nama' => $this->nama,
            'harga' => (string) $this->harga,
            'deskripsi' => $this->deskripsi,
            'aktif' => $this->aktif,
            'created_at' => $this->created_at?->copy()->utc()->toISOString(),
            'updated_at' => $this->updated_at?->copy()->utc()->toISOString(),
        ];
    }
}
