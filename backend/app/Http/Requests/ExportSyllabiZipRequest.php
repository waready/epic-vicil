<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportSyllabiZipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accreditation_cycle_id' => ['required', 'exists:accreditation_cycles,id'],
            'program_id' => ['required', 'exists:programs,id'],
            'requirement_code' => ['nullable', 'string', 'max:80'],
            'statuses' => ['nullable', 'array'],
            'statuses.*' => [Rule::in([
                'uploaded',
                'in_review',
                'observed',
                'corrected',
                'validated',
                'approved',
                'ready_to_export',
            ])],
        ];
    }
}
