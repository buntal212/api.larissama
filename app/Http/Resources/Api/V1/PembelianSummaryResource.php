<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PembelianSummaryResource extends JsonResource
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
            'warung_id' => (string) $this->warung_id,
            'user_id' => $this->user_id === null ? null : (string) $this->user_id,
            'created_by_superadmin_id' => $this->created_by_superadmin_id === null ? null : (string) $this->created_by_superadmin_id,
            'no_transaksi' => $this->no_transaksi,
            'tanggal' => $this->tanggal?->utc()->toISOString(),
            'total' => $this->total,
            'status' => $this->status,
            'catatan' => $this->catatan,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
