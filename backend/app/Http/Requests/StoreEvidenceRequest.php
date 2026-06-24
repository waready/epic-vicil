<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('accreditation.max_upload_mb', 100) * 1024;
        $extensions = config('accreditation.allowed_extensions');

        return [
            'program_id' => ['required', 'exists:programs,id'],
            'accreditation_cycle_id' => ['required', 'exists:accreditation_cycles,id'],
            'criterion_id' => ['required', 'exists:accreditation_criteria,id'],
            'subcriterion_id' => ['nullable', 'exists:accreditation_subcriteria,id'],
            'evidence_requirement_id' => ['required', 'exists:evidence_requirements,id'],
            'evidence_task_id' => ['nullable', 'exists:evidence_tasks,id'],
            'course_id' => ['nullable', 'exists:courses,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['required_without:file_asset_id', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', $extensions)],
            'file_asset_id' => ['required_without:file', 'nullable', 'exists:file_assets,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'criterion_id' => $this->input('criterion_id', $this->input('accreditation_criterion_id')),
            'subcriterion_id' => $this->input('subcriterion_id', $this->input('accreditation_subcriterion_id')),
        ]);
    }
}
