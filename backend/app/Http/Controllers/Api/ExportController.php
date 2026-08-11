<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportEvidenceZipRequest;
use App\Http\Requests\ExportSyllabiZipRequest;
use App\Models\ExportJob;
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

    public function syllabiZip(ExportSyllabiZipRequest $request, ExportService $service)
    {
        $job = $service->syllabiZip($request->validated(), $request);

        return response()->json([
            'message' => 'ZIP de silabos generado correctamente.',
            'data' => $job,
            'download_url' => $job->disk === 'public' ? Storage::disk($job->disk)->url($job->path) : null,
        ], 201);
    }

    public function download(ExportJob $exportJob)
    {
        abort_unless($exportJob->path && Storage::disk($exportJob->disk)->exists($exportJob->path), 404, 'La exportacion no esta disponible.');

        return Storage::disk($exportJob->disk)->download($exportJob->path);
    }
}
