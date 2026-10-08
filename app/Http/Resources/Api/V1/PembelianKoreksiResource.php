<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PembelianKoreksiResource extends JsonResource
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
            'user_id' => $this->user_id === null ? null : (string) $this->user_id,
            'superadmin_id' => $this->superadmin_id === null ? null : (string) $this->superadmin_id,
            'jenis' => $this->jenis,
            'alasan' => $this->alasan,
            'sebelum' => $this->sebelum,
            'sesudah' => $this->sesudah,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
