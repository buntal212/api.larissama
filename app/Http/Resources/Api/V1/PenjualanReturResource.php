<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenjualanReturResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'penjualan_id' => (string) $this->penjualan_id,
            'user_id' => (string) $this->user_id,
            'nominal' => $this->nominal,
            'alasan' => $this->alasan,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
