<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportEvidenceZipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accreditation_model_id' => ['nullable', 'exists:accreditation_models,id'],
            'accreditation_cycle_id' => ['required', 'exists:accreditation_cycles,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => [Rule::in(['validated', 'approved', 'ready_to_export'])],
        ];
    }
}
