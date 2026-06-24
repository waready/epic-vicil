<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportEvidenceZipRequest;
use App\Services\ExportService;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function evidencesZip(ExportEvidenceZipRequest $request, ExportService $service)
    {
        $job = $service->evidencesZip($request->validated(), $request);

        return response()->json([
            'message' => 'Exportacion generada correctamente.',
            'data' => $job,
            'download_url' => $job->disk === 'public' ? Storage::disk($job->disk)->url($job->path) : null,
        ], 201);
    }
}
