<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceSubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'version_number' => $this->version_number,
            'program_id' => $this->program_id,
            'accreditation_cycle_id' => $this->accreditation_cycle_id,
            'criterion_id' => $this->accreditation_criterion_id,
            'subcriterion_id' => $this->accreditation_subcriterion_id,
            'evidence_requirement_id' => $this->evidence_requirement_id,
            'evidence_task_id' => $this->evidence_task_id,
            'course_id' => $this->course_id,
            'teacher_id' => $this->teacher_id,
            'program' => $this->whenLoaded('program'),
            'cycle' => $this->whenLoaded('cycle'),
            'criterion' => $this->whenLoaded('criterion'),
            'subcriterion' => $this->whenLoaded('subcriterion'),
            'requirement' => $this->whenLoaded('requirement'),
            'task' => $this->whenLoaded('task'),
            'course' => $this->whenLoaded('course'),
            'teacher' => $this->whenLoaded('teacher'),
            'submitted_by' => $this->whenLoaded('submittedBy'),
            'current_file' => new FileAssetResource($this->whenLoaded('currentFile')),
            'versions' => EvidenceVersionResource::collection($this->whenLoaded('versions')),
            'reviews' => EvidenceReviewResource::collection($this->whenLoaded('reviews')),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'validated_at' => $this->validated_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
