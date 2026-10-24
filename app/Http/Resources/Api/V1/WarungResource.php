<?php

namespace App\Http\Resources\Api\V1;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'logo' => $this->logoUrl(),
            'timezone' => $this->timezone,
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_berakhir' => $this->tanggal_berakhir?->toDateString(),
            'aktif' => $this->aktif,
            'status_langganan' => $this->subscriptionStatusAt(CarbonImmutable::now('UTC')),
            'owners' => $this->whenLoaded('owners', fn (): array => $this->owners
                ->map(fn ($owner): array => [
                    'id' => (string) $owner->getKey(),
                    'nama' => $owner->nama,
                ])
                ->all()),
            'udev_customer_sync' => $this->whenLoaded('customerSync', function (): ?array {
                if ($this->customerSync === null) {
                    return null;
                }

                return [
                    'status' => $this->customerSync->status,
                    'attempt_count' => $this->customerSync->attempt_count,
                    'last_http_status' => $this->customerSync->last_http_status,
                    'last_error_code' => $this->customerSync->last_error_code,
                    'last_attempt_at' => $this->customerSync->last_attempt_at?->toISOString(),
                    'last_success_at' => $this->customerSync->last_success_at?->toISOString(),
                    'next_attempt_at' => $this->customerSync->next_attempt_at?->toISOString(),
                ];
            }),
            'created_at' => $this->created_at?->copy()->utc()->toISOString(),
            'updated_at' => $this->updated_at?->copy()->utc()->toISOString(),
        ];
    }

    private function logoUrl(): ?string
    {
        if ($this->logo === null) {
            return null;
        }

        if (in_array(strtolower((string) parse_url($this->logo, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $this->logo;
        }

        return Storage::disk('public')->url($this->logo);
    }
}
