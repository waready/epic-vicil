<?php

return [
    'storage_disk' => env('FILESYSTEM_DISK', env('ACCREDITATION_STORAGE_DISK', 'public')),
    'export_disk' => env('ACCREDITATION_EXPORT_DISK', 'local'),
    'max_upload_mb' => (int) env('MAX_EVIDENCE_FILE_MB', env('ACCREDITATION_MAX_UPLOAD_MB', 500)),
    'direct_upload_enabled' => (bool) env('EVIDENCE_DIRECT_UPLOAD_ENABLED', true),
    'direct_upload_disk' => env('EVIDENCE_DIRECT_UPLOAD_DISK', 's3'),
    'direct_upload_max_mb' => (int) env('EVIDENCE_DIRECT_UPLOAD_MAX_MB', 2048),
    'direct_upload_threshold_mb' => (int) env('EVIDENCE_DIRECT_UPLOAD_THRESHOLD_MB', 100),
    'direct_upload_expiration_minutes' => (int) env('EVIDENCE_DIRECT_UPLOAD_EXPIRATION_MINUTES', 15),
    'video_transcoding_enabled' => (bool) env('EVIDENCE_VIDEO_TRANSCODING_ENABLED', false),
    'ffmpeg_binary' => env('FFMPEG_BINARY', 'ffmpeg'),

    'allowed_extensions' => [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'jpg',
        'jpeg',
        'png',
        'mp4',
        'zip',
    ],

    'statuses' => [
        'pending',
        'assigned',
        'uploaded',
        'in_review',
        'observed',
        'corrected',
        'validated',
        'approved',
        'ready_to_export',
        'archived',
    ],

    'review_actions' => [
        'submit',
        'observe',
        'correct',
        'validate',
        'approve',
        'reject',
    ],

    'context_types' => [
        'program',
        'course_offering',
        'assessment_course',
        'teacher',
        'laboratory',
        'facility',
        'integrator_project',
        'improvement_plan',
        'graduate',
    ],
];
