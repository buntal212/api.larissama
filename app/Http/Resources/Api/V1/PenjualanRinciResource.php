<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenjualanRinciResource extends JsonResource
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
            'penjualan_id' => (string) $this->penjualan_id,
            'menu_id' => (string) $this->menu_id,
            'nama_menu' => $this->nama_menu,
            'harga' => $this->harga,
            'qty' => $this->qty,
            'diskon' => $this->diskon,
            'subtotal' => $this->subtotal,
            'catatan' => $this->catatan,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
