<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'warung_id' => $this->warung_id === null ? null : (string) $this->warung_id,
            'nama' => $this->nama,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
            'aktif' => $this->aktif,
            'created_at' => $this->created_at?->copy()->utc()->toISOString(),
            'updated_at' => $this->updated_at?->copy()->utc()->toISOString(),
        ];
    }
}
