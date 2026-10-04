<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenjualanSummaryResource extends JsonResource
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
            'user_id' => (string) $this->user_id,
            'no_transaksi' => $this->no_transaksi,
            'tanggal' => $this->tanggal?->utc()->toISOString(),
            'subtotal' => $this->subtotal,
            'diskon' => $this->diskon,
            'total' => $this->total,
            'bayar' => $this->bayar,
            'kembalian' => $this->kembalian,
            'metode_pembayaran' => $this->metode_pembayaran,
            'status' => $this->status,
            'catatan' => $this->catatan,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
