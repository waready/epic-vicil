<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsolidateEvidenceRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_requirement_id' => ['required', 'integer', 'exists:evidence_requirements,id'],
            'target_name' => ['required', 'string', 'max:255'],
            'renumber_codes' => ['sometimes', 'boolean'],
        ];
    }
}
