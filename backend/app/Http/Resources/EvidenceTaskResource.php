<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EvidenceTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->toDateString(),
            'context_type' => $this->context_type,
            'context_id' => $this->context_id,
            'context_label' => $this->contextLabel(),
            'program' => $this->whenLoaded('program'),
            'cycle' => $this->whenLoaded('cycle'),
            'criterion' => $this->whenLoaded('criterion'),
            'subcriterion' => $this->whenLoaded('subcriterion'),
            'requirement' => $this->whenLoaded('requirement'),
            'assignee' => $this->whenLoaded('assignee'),
            'course_offering' => $this->when(
                in_array($this->context_type, ['course_offering', 'assessment_course'], true),
                fn () => $this->whenLoaded('courseOfferingContext')
            ),
            'teacher_context' => $this->when(
                $this->context_type === 'teacher',
                fn () => $this->whenLoaded('teacherContext')
            ),
            'current_submission' => new EvidenceSubmissionResource($this->whenLoaded('currentSubmission')),
            'submissions' => EvidenceSubmissionResource::collection($this->whenLoaded('submissions')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function contextLabel(): ?string
    {
        if (in_array($this->context_type, ['course_offering', 'assessment_course'], true)) {
            if ($this->relationLoaded('courseOfferingContext') && $this->courseOfferingContext) {
                $course = $this->courseOfferingContext->course;
                $term = $this->courseOfferingContext->term;
                $assessment = $this->context_type === 'assessment_course'
                    ? trim(($this->courseOfferingContext->assessment_result_code ?: '').' '.$this->courseOfferingContext->assessment_result_name)
                    : '';

                return trim(($course ? $course->code.' - '.$course->name : 'Curso').' / '.($term ? $term->code : '').' / '.$this->courseOfferingContext->section.($assessment ? ' / '.$assessment : ''));
            }
        }

        if ($this->context_type === 'teacher') {
            if ($this->relationLoaded('teacherContext') && $this->teacherContext) {
                return trim($this->teacherContext->last_name.', '.$this->teacherContext->first_name);
            }
        }

        return null;
    }
}
