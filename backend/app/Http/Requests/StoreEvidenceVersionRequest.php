<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvidenceVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('accreditation.max_upload_mb', 100) * 1024;

        return [
            'file' => ['required_without:file_asset_id', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', config('accreditation.allowed_extensions'))],
            'file_asset_id' => ['required_without:file', 'nullable', 'exists:file_assets,id'],
            'change_summary' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
