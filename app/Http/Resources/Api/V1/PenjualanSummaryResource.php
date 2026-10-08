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
            'user_id' => $this->user_id === null ? null : (string) $this->user_id,
            'created_by_superadmin_id' => $this->created_by_superadmin_id === null ? null : (string) $this->created_by_superadmin_id,
            'no_transaksi' => $this->no_transaksi,
            'nama_pelanggan' => $this->nama_pelanggan,
            'tanggal' => $this->tanggal?->utc()->toISOString(),
            'subtotal' => $this->subtotal,
            'diskon' => $this->diskon,
            'total' => $this->total,
            'bayar' => $this->bayar,
            'kembalian' => $this->kembalian,
            'metode_pembayaran' => $this->metode_pembayaran,
            'status_pembayaran' => $this->status_pembayaran,
            'dibayar_pada' => $this->dibayar_pada?->utc()->toISOString(),
            'pembayaran_user_id' => $this->pembayaran_user_id === null ? null : (string) $this->pembayaran_user_id,
            'pembayaran_superadmin_id' => $this->pembayaran_superadmin_id === null ? null : (string) $this->pembayaran_superadmin_id,
            'status' => $this->status,
            'catatan' => $this->catatan,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
