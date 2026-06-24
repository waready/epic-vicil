<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    protected $fillable = [
        'requested_by',
        'accreditation_cycle_id',
        'program_id',
        'export_type',
        'status',
        'disk',
        'path',
        'filters',
        'stats',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'stats' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
