<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenjualanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new PenjualanSummaryResource($this->resource))->toArray($request),
            'rincian' => PenjualanRinciResource::collection($this->whenLoaded('rincian'))->toArray($request),
        ];
    }
}
