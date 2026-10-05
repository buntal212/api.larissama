<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PembelianResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new PembelianSummaryResource($this->resource))->toArray($request),
            'rincian' => PembelianRinciResource::collection($this->whenLoaded('rincian'))->toArray($request),
            'riwayat_koreksi' => $this->resource->relationLoaded('koreksi')
                ? PembelianKoreksiResource::collection($this->resource->getRelation('koreksi'))->toArray($request)
                : [],
        ];
    }
}
