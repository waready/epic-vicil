<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_number' => $this->version_number,
            'change_summary' => $this->change_summary,
            'uploaded_by' => $this->whenLoaded('uploader'),
            'file' => new FileAssetResource($this->whenLoaded('file')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
