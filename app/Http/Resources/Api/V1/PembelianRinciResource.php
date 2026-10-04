<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PembelianRinciResource extends JsonResource
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
            'pembelian_id' => (string) $this->pembelian_id,
            'nama_item' => $this->nama_item,
            'qty' => $this->qty,
            'satuan' => $this->satuan,
            'harga_satuan' => $this->harga_satuan,
            'subtotal' => $this->subtotal,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
