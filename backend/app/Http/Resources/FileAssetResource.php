<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $previewUrl = $this->fileUrl('inline');
        $downloadUrl = $this->fileUrl('attachment');

        return [
            'id' => $this->id,
            'disk' => $this->disk,
            'path' => $this->path,
            'original_name' => $this->original_name,
            'stored_name' => $this->stored_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'checksum' => $this->checksum,
            'url' => $previewUrl,
            'preview_url' => $previewUrl,
            'download_url' => $downloadUrl,
            'file_type' => $this->fileType(),
            'can_preview' => in_array($this->fileType(), ['pdf', 'image', 'video'], true),
            'temporary_url_expires_in_minutes' => $this->disk === 's3' ? 20 : null,
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
        ];
    }

    private function fileUrl(string $disposition): ?string
    {
        if (! $this->path || ! $this->disk) {
            return null;
        }

        try {
            if ($this->disk === 'public') {
                return Storage::disk($this->disk)->url($this->path);
            }

            if ($this->disk === 's3') {
                return Storage::disk($this->disk)->temporaryUrl(
                    $this->path,
                    now()->addMinutes(20),
                    [
                        'ResponseContentType' => $this->mime_type ?: 'application/octet-stream',
                        'ResponseContentDisposition' => $this->contentDisposition($disposition),
                    ]
                );
            }
        } catch (\Throwable $exception) {
            return null;
        }

        return null;
    }

    private function contentDisposition(string $disposition): string
    {
        $name = str_replace(['"', '\\'], '', $this->original_name ?: $this->stored_name ?: 'evidencia');

        return $disposition.'; filename="'.$name.'"';
    }

    private function fileType(): string
    {
        $mime = strtolower((string) $this->mime_type);
        $extension = strtolower((string) $this->extension);

        if ($extension === 'pdf' || $mime === 'application/pdf') {
            return 'pdf';
        }

        if (Str::startsWith($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return 'image';
        }

        if (Str::startsWith($mime, 'video/') || in_array($extension, ['mp4', 'mov', 'm4v'], true)) {
            return 'video';
        }

        if (in_array($extension, ['doc', 'docx'], true)) {
            return 'document';
        }

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            return 'spreadsheet';
        }

        if (in_array($extension, ['ppt', 'pptx'], true)) {
            return 'presentation';
        }

        if ($extension === 'zip') {
            return 'archive';
        }

        return 'file';
    }
}
