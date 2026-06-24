<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'comment' => $this->comment,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'reviewer' => $this->whenLoaded('reviewer'),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
