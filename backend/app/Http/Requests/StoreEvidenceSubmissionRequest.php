<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvidenceSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('accreditation.max_upload_mb', 200) * 1024;

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'files' => ['required_without:file_asset_ids', 'nullable', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'max:' . $maxKb, 'mimes:' . implode(',', config('accreditation.allowed_extensions'))],
            'file_asset_ids' => ['required_without:files', 'nullable', 'array', 'min:1'],
            'file_asset_ids.*' => ['required', 'exists:file_assets,id'],
            'document_roles' => ['nullable', 'array'],
            'document_roles.*' => ['nullable', 'string', 'max:80'],
        ];
    }
}
